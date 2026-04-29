<?php

namespace App\Modules\Billing\Services;

use App\Models\BillingEvent;
use App\Models\CreditTransaction;
use App\Models\CreditWallet;
use App\Models\Project;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function getPlans(): Collection
    {
        $this->seedDefaultPlans();

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price_monthly_cents')
            ->get();
    }

    public function getOverview(User $user): array
    {
        [$subscription, $wallet] = $this->ensureAccountState($user);
        $plan = $subscription->plan;

        $storageBytes = (int) $user->files()->sum('size_bytes');
        $liveStreams = $user->projects()->withCount('liveStreams')->get();

        return [
            'subscription' => $subscription->load('plan'),
            'wallet' => $wallet->load(['transactions' => fn ($query) => $query->latest()->limit(10)]),
            'usage' => [
                'project_count' => $user->projects()->count(),
                'storage_bytes' => $storageBytes,
                'storage_gb_used' => round($storageBytes / 1073741824, 2),
                'storage_gb_limit' => $plan->max_storage_gb,
                'destination_count' => $user->projects()->get()->sum(fn (Project $project) => $project->destinations()->count()),
                'guest_room_count' => $user->guestRooms()->count(),
                'interactive_elements_count' => $user->interactiveElements()->count(),
                'scene_template_count' => $user->sceneTemplates()->count(),
                'stream_count' => $liveStreams->sum('live_streams_count'),
            ],
            'history' => BillingEvent::query()
                ->where('user_id', $user->id)
                ->latest('occurred_at')
                ->latest()
                ->limit(20)
                ->get(),
        ];
    }

    public function switchPlan(User $user, string $planCode, ?string $provider = null): UserSubscription
    {
        $plan = $this->getPlanByCode($planCode);
        [$subscription, $wallet] = $this->ensureAccountState($user);

        return DB::transaction(function () use ($subscription, $wallet, $plan, $provider, $user) {
            $subscription->update([
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'billing_provider' => $provider ?: $subscription->billing_provider,
                'renews_at' => now()->addMonth(),
                'cancelled_at' => null,
            ]);

            $this->addCredits(
                $wallet,
                $user->id,
                $plan->credits_included,
                'grant',
                'Subscription credits granted',
                'subscription_plan',
                $plan->id,
                ['plan_code' => $plan->code]
            );

            $this->recordBillingEvent($user->id, [
                'provider' => $provider,
                'type' => 'subscription_changed',
                'status' => 'recorded',
                'amount_cents' => $plan->price_monthly_cents,
                'reference' => $plan->code,
                'metadata' => ['plan' => $plan->name],
            ]);

            return $subscription->fresh('plan');
        });
    }

    public function purchaseCredits(User $user, int $credits, ?string $provider = null, ?int $amountCents = null): CreditWallet
    {
        if ($credits <= 0) {
            throw new \Exception('Credits purchase amount must be greater than zero');
        }

        [, $wallet] = $this->ensureAccountState($user);

        DB::transaction(function () use ($wallet, $user, $credits, $provider, $amountCents) {
            $this->addCredits(
                $wallet,
                $user->id,
                $credits,
                'purchase',
                'Credits purchased',
                'credit_pack',
                null,
                ['provider' => $provider]
            );

            $this->recordBillingEvent($user->id, [
                'provider' => $provider,
                'type' => 'credits_purchased',
                'status' => 'recorded',
                'amount_cents' => $amountCents ?? ($credits * 100),
                'reference' => $credits.'_credits',
                'metadata' => ['credits' => $credits],
            ]);
        });

        return $wallet->fresh(['transactions' => fn ($query) => $query->latest()]);
    }

    public function consumeCredits(
        User $user,
        int $credits,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = []
    ): CreditWallet {
        if ($credits <= 0) {
            throw new \Exception('Credits usage amount must be greater than zero');
        }

        [, $wallet] = $this->ensureAccountState($user);

        if ($wallet->balance < $credits) {
            throw new \Exception('Insufficient credits available for this action');
        }

        DB::transaction(function () use ($wallet, $user, $credits, $description, $referenceType, $referenceId, $metadata) {
            $balanceAfter = $wallet->balance - $credits;

            $wallet->update([
                'balance' => $balanceAfter,
                'lifetime_spent' => $wallet->lifetime_spent + $credits,
            ]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'credit_wallet_id' => $wallet->id,
                'type' => 'usage',
                'amount' => -$credits,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });

        return $wallet->fresh();
    }

    public function getLimitsForUser(User $user): SubscriptionPlan
    {
        [$subscription] = $this->ensureAccountState($user);

        return $subscription->plan;
    }

    public function assertCanImportAsset(User $user, int $sizeBytes, string $type): void
    {
        $plan = $this->getLimitsForUser($user);
        $maxBytes = $plan->max_video_size_mb * 1024 * 1024;

        if ($type === 'video' && $sizeBytes > $maxBytes) {
            throw new \Exception("This asset exceeds your {$plan->name} plan video size limit");
        }

        $currentStorage = (int) $user->files()->sum('size_bytes');
        $storageLimitBytes = $plan->max_storage_gb * 1024 * 1024 * 1024;

        if (($currentStorage + $sizeBytes) > $storageLimitBytes) {
            throw new \Exception("This import would exceed your {$plan->name} plan storage limit");
        }
    }

    public function assertProjectCanGoLive(Project $project, ?int $durationSeconds = null): void
    {
        $plan = $this->getLimitsForUser($project->user);
        $durationSeconds ??= 0;
        $maxDuration = $plan->max_stream_hours * 3600;

        if ($durationSeconds > $maxDuration) {
            throw new \Exception("Requested stream duration exceeds your {$plan->name} plan limit");
        }

        if ($project->destinations()->count() > $plan->max_destinations) {
            throw new \Exception("Project destinations exceed your {$plan->name} plan limit");
        }

        if ($project->scenes()->count() > $plan->max_scenes) {
            throw new \Exception("Project scenes exceed your {$plan->name} plan limit");
        }
    }

    public function assertCanCreateInteractive(Project $project): void
    {
        $plan = $this->getLimitsForUser($project->user);

        if ($project->interactiveElements()->count() >= $plan->max_interactive_elements) {
            throw new \Exception("Interactive element limit reached for your {$plan->name} plan");
        }
    }

    public function assertCanManageGuests(Project $project, int $requestedGuests): void
    {
        $plan = $this->getLimitsForUser($project->user);

        if ($requestedGuests > $plan->max_guests) {
            throw new \Exception("Guest limit exceeds your {$plan->name} plan");
        }
    }

    public function ensureAccountState(User $user): array
    {
        $this->seedDefaultPlans();

        $subscription = UserSubscription::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $wallet = CreditWallet::query()
            ->where('user_id', $user->id)
            ->first();

        if ($subscription && $wallet) {
            return [$subscription, $wallet];
        }

        return DB::transaction(function () use ($user, $subscription, $wallet) {
            $starterPlan = $this->getPlanByCode('starter');

            if (! $subscription) {
                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $starterPlan->id,
                    'status' => 'active',
                    'billing_provider' => 'internal',
                    'billing_cycle' => 'monthly',
                    'renews_at' => now()->addMonth(),
                    'metadata' => ['seeded' => true],
                ]);
            }

            if (! $wallet) {
                $wallet = CreditWallet::create([
                    'user_id' => $user->id,
                    'balance' => 0,
                    'lifetime_earned' => 0,
                    'lifetime_spent' => 0,
                ]);

                $this->addCredits(
                    $wallet,
                    $user->id,
                    $starterPlan->credits_included,
                    'grant',
                    'Starter plan welcome credits',
                    'subscription_plan',
                    $starterPlan->id,
                    ['plan_code' => $starterPlan->code]
                );
            }

            return [$subscription->fresh('plan'), $wallet->fresh()];
        });
    }

    protected function addCredits(
        CreditWallet $wallet,
        int $userId,
        int $amount,
        string $type,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = []
    ): void {
        $balanceAfter = $wallet->balance + $amount;

        $wallet->update([
            'balance' => $balanceAfter,
            'lifetime_earned' => $wallet->lifetime_earned + $amount,
        ]);

        CreditTransaction::create([
            'user_id' => $userId,
            'credit_wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    protected function seedDefaultPlans(): void
    {
        foreach ($this->defaultPlans() as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan
            );
        }
    }

    protected function defaultPlans(): array
    {
        return [
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
        ];
    }

    protected function getPlanByCode(string $planCode): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->where('code', $planCode)->first();

        if (! $plan) {
            throw new \Exception('Subscription plan not found');
        }

        return $plan;
    }

    protected function recordBillingEvent(int $userId, array $data): void
    {
        BillingEvent::create([
            'user_id' => $userId,
            'provider' => $data['provider'] ?? null,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'recorded',
            'amount_cents' => $data['amount_cents'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'reference' => $data['reference'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'occurred_at' => now(),
        ]);
    }
}
