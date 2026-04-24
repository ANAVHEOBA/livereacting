<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;
use App\Models\Scene;
use App\Models\SceneLayer;

class StudioPayloadService
{
    public function validateProjectStudio(Project $project): array
    {
        $project = $this->loadStudioRelations($project);
        $errors = [];

        if ($project->scenes->isEmpty()) {
            $errors[] = 'Project has no scenes';
        }

        if (!$project->active_scene_id || !$project->activeScene) {
            $errors[] = 'Project has no active scene';
        }

        if ($project->activeScene && $project->activeScene->layers->where('is_visible', true)->isEmpty()) {
            $errors[] = 'Active scene has no visible layers';
        }

        foreach ($project->scenes as $scene) {
            foreach ($scene->layers as $layer) {
                $layerErrors = $this->validateLayer($scene, $layer);

                foreach ($layerErrors as $layerError) {
                    $errors[] = $layerError;
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function buildStudioPayload(Project $project): array
    {
        $project = $this->loadStudioRelations($project);
        $scenes = $project->scenes->map(function (Scene $scene) use ($project) {
            return [
                'id' => $scene->id,
                'name' => $scene->name,
                'transition' => $scene->transition,
                'duration' => $scene->duration,
                'sort_order' => $scene->sort_order,
                'settings' => $scene->settings ?? [],
                'is_active' => $project->active_scene_id === $scene->id,
                'layers' => $scene->layers->map(function (SceneLayer $layer) {
                    return [
                        'id' => $layer->id,
                        'type' => $layer->type,
                        'name' => $layer->name,
                        'content' => $layer->content,
                        'sort_order' => $layer->sort_order,
                        'is_visible' => $layer->is_visible,
                        'position' => $layer->position ?? [],
                        'settings' => $layer->settings ?? [],
                        'asset' => $layer->file ? [
                            'id' => $layer->file->id,
                            'name' => $layer->file->name,
                            'type' => $layer->file->type,
                            'status' => $layer->file->status,
                            'storage_path' => $layer->file->storage_path,
                            'source_url' => $layer->file->source_url,
                            'duration_seconds' => $layer->file->duration_seconds,
                            'resolution' => $layer->file->resolution,
                            'format' => $layer->file->format,
                        ] : null,
                    ];
                })->values()->all(),
            ];
        })->values();

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'auto_sync' => $project->auto_sync,
                'status' => $project->status,
                'active_scene_id' => $project->active_scene_id,
            ],
            'active_scene' => $scenes->firstWhere('is_active', true),
            'scene_count' => $scenes->count(),
            'layer_count' => $scenes->sum(fn (array $scene) => count($scene['layers'])),
            'scenes' => $scenes->all(),
            'destinations' => $project->destinations->map(function ($destination) {
                return [
                    'id' => $destination->id,
                    'type' => $destination->type,
                    'name' => $destination->name,
                    'platform_id' => $destination->platform_id,
                    'rtmp_url' => $destination->rtmp_url,
                    'is_valid' => $destination->is_valid,
                ];
            })->values()->all(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    protected function loadStudioRelations(Project $project): Project
    {
        return $project->loadMissing([
            'destinations',
            'scenes.layers.file',
            'activeScene.layers.file',
        ]);
    }

    protected function validateLayer(Scene $scene, SceneLayer $layer): array
    {
        $errors = [];

        if (in_array($layer->type, ['video', 'audio', 'image'], true)) {
            if (!$layer->file) {
                $errors[] = "Scene '{$scene->name}' has a {$layer->type} layer without an asset";
            } elseif (!$layer->file->isReady()) {
                $errors[] = "Scene '{$scene->name}' has a {$layer->type} layer with an asset that is not ready";
            } elseif ($layer->file->type !== $layer->type) {
                $errors[] = "Scene '{$scene->name}' has a {$layer->type} layer with a mismatched asset type";
            }
        }

        if (in_array($layer->type, ['text', 'overlay'], true) && blank($layer->content)) {
            $errors[] = "Scene '{$scene->name}' has a {$layer->type} layer without content";
        }

        if ($layer->type === 'countdown' && blank($layer->settings['ends_at'] ?? null)) {
            $errors[] = "Scene '{$scene->name}' has a countdown layer without settings.ends_at";
        }

        return $errors;
    }
}
