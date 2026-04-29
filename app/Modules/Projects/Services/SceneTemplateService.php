<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\Scene;
use App\Models\SceneTemplate;

class SceneTemplateService
{
    public function __construct(
        protected SceneService $sceneService,
        protected SceneLayerService $sceneLayerService
    ) {}

    public function getTemplatesForUser(int $userId)
    {
        return SceneTemplate::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function getTemplate(int $templateId, int $userId): ?SceneTemplate
    {
        return SceneTemplate::query()
            ->where('user_id', $userId)
            ->where('id', $templateId)
            ->first();
    }

    public function createTemplateFromScene(Scene $scene, int $userId, array $data): SceneTemplate
    {
        $scene->loadMissing('layers.file');

        return SceneTemplate::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'payload' => [
                'scene' => [
                    'name' => $scene->name,
                    'transition' => $scene->transition,
                    'duration' => $scene->duration,
                    'settings' => $scene->settings ?? [],
                ],
                'layers' => $scene->layers->map(fn ($layer) => [
                    'type' => $layer->type,
                    'file_id' => $layer->file_id,
                    'name' => $layer->name,
                    'content' => $layer->content,
                    'position' => $layer->position ?? [],
                    'settings' => $layer->settings ?? [],
                    'is_visible' => $layer->is_visible,
                ])->values()->all(),
            ],
        ]);
    }

    public function applyTemplate(Project $project, SceneTemplate $template, int $userId, array $data = []): Scene
    {
        $payload = $template->payload ?? [];
        $scenePayload = $payload['scene'] ?? [];
        $layers = $payload['layers'] ?? [];

        $scene = $this->sceneService->createScene($project, [
            'name' => $data['name'] ?? ($scenePayload['name'] ?? $template->name),
            'transition' => $scenePayload['transition'] ?? 'cut',
            'duration' => $scenePayload['duration'] ?? null,
            'settings' => $scenePayload['settings'] ?? [],
        ]);

        foreach ($layers as $layer) {
            $this->sceneLayerService->createLayer($project, $scene, $userId, $layer);
        }

        return $scene->fresh(['project', 'layers.file']);
    }
}
