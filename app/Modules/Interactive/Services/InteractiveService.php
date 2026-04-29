<?php

namespace App\Modules\Interactive\Services;

use App\Models\InteractiveElement;
use App\Models\InteractiveResponse;
use App\Models\Project;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Projects\Services\HistoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InteractiveService
{
    public function __construct(
        protected BillingService $billingService,
        protected HistoryService $historyService
    ) {}

    public function getProjectElements(Project $project): Collection
    {
        return $project->interactiveElements()->with(['scene', 'responses'])->get();
    }

    public function getElement(Project $project, int $elementId): ?InteractiveElement
    {
        return $project->interactiveElements()
            ->with(['scene', 'responses'])
            ->where('id', $elementId)
            ->first();
    }

    public function createElement(Project $project, int $userId, array $data): InteractiveElement
    {
        $this->billingService->assertCanCreateInteractive($project);

        [$settings, $results] = $this->normalizeConfiguration($data['type'], $data['settings'] ?? []);

        $element = InteractiveElement::create([
            'project_id' => $project->id,
            'scene_id' => $data['scene_id'] ?? null,
            'user_id' => $userId,
            'type' => $data['type'],
            'name' => $data['name'] ?? Str::headline(str_replace('_', ' ', $data['type'])),
            'prompt' => $data['prompt'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'is_visible' => $data['is_visible'] ?? false,
            'sort_order' => (int) $project->interactiveElements()->max('sort_order') + 1,
            'settings' => $settings,
            'results' => $results,
        ]);

        $this->historyService->logAction(
            $project,
            'interactive_created',
            'Interactive element created',
            ['interactive_element_id' => $element->id, 'type' => $element->type]
        );

        return $element->fresh(['scene', 'responses']);
    }

    public function updateElement(Project $project, InteractiveElement $element, array $data): InteractiveElement
    {
        $type = $data['type'] ?? $element->type;
        [$settings, $results] = $this->normalizeConfiguration(
            $type,
            array_key_exists('settings', $data) ? ($data['settings'] ?? []) : ($element->settings ?? []),
            $element->results ?? []
        );

        $element->update([
            'scene_id' => $data['scene_id'] ?? $element->scene_id,
            'type' => $type,
            'name' => $data['name'] ?? $element->name,
            'prompt' => array_key_exists('prompt', $data) ? $data['prompt'] : $element->prompt,
            'status' => $data['status'] ?? $element->status,
            'is_visible' => $data['is_visible'] ?? $element->is_visible,
            'settings' => $settings,
            'results' => $results,
        ]);

        $this->historyService->logAction(
            $project,
            'interactive_updated',
            'Interactive element updated',
            ['interactive_element_id' => $element->id, 'fields' => array_keys($data)]
        );

        return $element->fresh(['scene', 'responses']);
    }

    public function activateElement(Project $project, InteractiveElement $element): InteractiveElement
    {
        $element->update([
            'status' => 'live',
            'is_visible' => true,
        ]);

        $this->historyService->logAction(
            $project,
            'interactive_activated',
            'Interactive element activated',
            ['interactive_element_id' => $element->id]
        );

        return $element->fresh(['scene', 'responses']);
    }

    public function deleteElement(Project $project, InteractiveElement $element): void
    {
        $element->delete();

        $this->historyService->logAction(
            $project,
            'interactive_deleted',
            'Interactive element deleted',
            ['interactive_element_id' => $element->id]
        );
    }

    public function submitResponse(InteractiveElement $element, array $data): InteractiveResponse
    {
        if ($element->type === 'countdown') {
            throw new \Exception('Countdown elements do not accept responses');
        }

        return DB::transaction(function () use ($element, $data) {
            $responseKey = $data['response_key'] ?? null;
            $message = $data['message'] ?? null;
            $settings = $element->settings ?? [];
            $results = $element->results ?? [];

            if (in_array($element->type, ['poll', 'trivia'], true)) {
                $options = collect($settings['options'] ?? [])->pluck('id')->all();

                if (! $responseKey || ! in_array($responseKey, $options, true)) {
                    throw new \Exception('Invalid response option submitted');
                }
            }

            if (in_array($element->type, ['chat_overlay', 'last_comment', 'featured_comment'], true) && blank($message)) {
                throw new \Exception('This interactive element requires a message');
            }

            $isCorrect = null;

            if ($element->type === 'trivia') {
                $isCorrect = ($settings['correct_option'] ?? null) === $responseKey;
            }

            $response = InteractiveResponse::create([
                'interactive_element_id' => $element->id,
                'participant_name' => $data['participant_name'] ?? null,
                'response_key' => $responseKey,
                'message' => $message,
                'is_correct' => $isCorrect,
                'payload' => $data['payload'] ?? [],
            ]);

            $results['total_responses'] = ($results['total_responses'] ?? 0) + 1;
            $results['last_response_at'] = now()->toIso8601String();

            if ($responseKey) {
                $results['responses'][$responseKey] = ($results['responses'][$responseKey] ?? 0) + 1;
            }

            if ($message) {
                $results['last_message'] = [
                    'participant_name' => $response->participant_name,
                    'message' => $response->message,
                    'created_at' => $response->created_at?->toIso8601String(),
                ];
            }

            if ($element->type === 'trivia') {
                $results['correct_responses'] = ($results['correct_responses'] ?? 0) + ($isCorrect ? 1 : 0);
            }

            $element->update(['results' => $results]);

            return $response;
        });
    }

    public function featureResponse(InteractiveElement $element, InteractiveResponse $response): InteractiveElement
    {
        $results = $element->results ?? [];
        $results['featured_response'] = [
            'id' => $response->id,
            'participant_name' => $response->participant_name,
            'message' => $response->message,
            'response_key' => $response->response_key,
            'created_at' => $response->created_at?->toIso8601String(),
        ];

        $element->update([
            'results' => $results,
            'status' => $element->status === 'draft' ? 'armed' : $element->status,
        ]);

        return $element->fresh(['scene', 'responses']);
    }

    protected function normalizeConfiguration(string $type, array $settings, array $existingResults = []): array
    {
        $supported = ['poll', 'trivia', 'countdown', 'chat_overlay', 'word_search', 'last_comment', 'hidden_object', 'featured_comment'];

        if (! in_array($type, $supported, true)) {
            throw new \Exception('Unsupported interactive element type');
        }

        if (in_array($type, ['poll', 'trivia'], true)) {
            $options = collect($settings['options'] ?? [])
                ->map(function ($option, int $index) {
                    if (is_string($option)) {
                        return ['id' => 'option_'.($index + 1), 'label' => $option];
                    }

                    return [
                        'id' => $option['id'] ?? 'option_'.($index + 1),
                        'label' => $option['label'] ?? null,
                    ];
                })
                ->filter(fn (array $option) => filled($option['label']))
                ->values()
                ->all();

            if (count($options) < 2) {
                throw new \Exception('Polls and trivia require at least two options');
            }

            $settings['options'] = $options;

            if ($type === 'trivia' && blank($settings['correct_option'] ?? null)) {
                throw new \Exception('Trivia requires a correct option');
            }
        }

        if ($type === 'countdown' && blank($settings['ends_at'] ?? null)) {
            throw new \Exception('Countdown interactive elements require settings.ends_at');
        }

        if ($type === 'word_search' && blank($settings['puzzle'] ?? null)) {
            throw new \Exception('Word search interactive elements require a puzzle payload');
        }

        if ($type === 'hidden_object' && blank($settings['objects'] ?? null)) {
            throw new \Exception('Hidden object interactive elements require objects metadata');
        }

        return [
            $settings,
            array_merge([
                'total_responses' => 0,
                'responses' => [],
            ], $existingResults),
        ];
    }
}
