<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ([
            [
                'code' => 'starter',
                'name' => 'Starter',
                'tier' => 'small',
                'price_monthly_cents' => 2900,
                'credits_included' => 250,
                'max_storage_gb' => 25,
                'max_video_size_mb' => 1024,
                'max_destinations' => 3,
                'max_guests' => 2,
                'max_stream_hours' => 4,
                'max_scenes' => 12,
                'max_interactive_elements' => 8,
                'features' => ['rtmp', 'polls', 'countdowns', 'basic guest rooms'],
                'is_active' => true,
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'tier' => 'medium',
                'price_monthly_cents' => 7900,
                'credits_included' => 1000,
                'max_storage_gb' => 200,
                'max_video_size_mb' => 4096,
                'max_destinations' => 8,
                'max_guests' => 6,
                'max_stream_hours' => 12,
                'max_scenes' => 40,
                'max_interactive_elements' => 30,
                'features' => ['all starter features', 'playlists', 'templates', 'multi-guest'],
                'is_active' => true,
            ],
            [
                'code' => 'studio',
                'name' => 'Studio',
                'tier' => 'enterprise',
                'price_monthly_cents' => 14900,
                'credits_included' => 2500,
                'max_storage_gb' => 1000,
                'max_video_size_mb' => 16384,
                'max_destinations' => 20,
                'max_guests' => 12,
                'max_stream_hours' => 24,
                'max_scenes' => 100,
                'max_interactive_elements' => 100,
                'features' => ['advanced guest rooms', 'priority routing', 'white-label workflows'],
                'is_active' => true,
            ],
        ] as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
