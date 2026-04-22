<?php

namespace App\Modules\Projects\Services;

use App\Models\Project;

class AnalyticsService
{
    public function getProjectAnalytics(Project $project): array
    {
        // Get all live streams for this project
        $liveStreams = $project->liveStreams;

        // Calculate total streams
        $totalStreams = $liveStreams->count();

        // Calculate completed streams
        $completedStreams = $liveStreams->where('status', 'stopped')->count();

        // Calculate total streaming time (in seconds)
        $totalStreamingTime = $liveStreams
            ->where('status', 'stopped')
            ->sum(function ($stream) {
                if ($stream->started_at && $stream->ended_at) {
                    return $stream->ended_at->diffInSeconds($stream->started_at);
                }
                return 0;
            });

        // Get average stream duration
        $avgStreamDuration = $completedStreams > 0 
            ? round($totalStreamingTime / $completedStreams) 
            : 0;

        // Get last stream info
        $lastStream = $liveStreams->sortByDesc('created_at')->first();

        // Get scheduled streams count
        $scheduledStreams = $project->schedules()
            ->where('status', 'scheduled')
            ->where('start_at', '>', now())
            ->count();

        // Get destinations count
        $destinationsCount = $project->destinations()->count();

        return [
            'total_streams' => $totalStreams,
            'completed_streams' => $completedStreams,
            'active_streams' => $liveStreams->whereIn('status', ['preparing', 'live'])->count(),
            'failed_streams' => $liveStreams->where('status', 'failed')->count(),
            'scheduled_streams' => $scheduledStreams,
            'total_streaming_time_seconds' => $totalStreamingTime,
            'total_streaming_time_hours' => round($totalStreamingTime / 3600, 2),
            'average_stream_duration_seconds' => $avgStreamDuration,
            'average_stream_duration_minutes' => round($avgStreamDuration / 60, 2),
            'destinations_count' => $destinationsCount,
            'last_stream' => $lastStream ? [
                'id' => $lastStream->id,
                'status' => $lastStream->status,
                'started_at' => $lastStream->started_at,
                'ended_at' => $lastStream->ended_at,
            ] : null,
            'project_created_at' => $project->created_at,
            'project_status' => $project->status,
        ];
    }
}
