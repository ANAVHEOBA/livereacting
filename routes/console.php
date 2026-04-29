<?php

use App\Models\ConnectedAccount;
use App\Models\LiveStream;
use App\Modules\Integrations\Services\IntegrationService;
use App\Modules\Integrations\Services\ProviderValidationService;
use App\Modules\Projects\Services\ScheduleService;
use App\Modules\Projects\Services\StreamWorkerService;
use App\Modules\Projects\Services\StudioHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('streams:process-schedules', function () {
    $result = app(ScheduleService::class)->processDueSchedules();

    $this->info(sprintf(
        'Processed due schedules. Started: %d, Failed: %d',
        $result['started'],
        $result['failed'],
    ));
})->purpose('Start any scheduled streams that are due');

Schedule::command('streams:process-schedules')->everyMinute();

Artisan::command('streams:run-worker {liveStreamId}', function (int $liveStreamId) {
    $liveStream = LiveStream::query()->findOrFail($liveStreamId);
    $exitCode = app(StreamWorkerService::class)->run($liveStream);

    $this->info(sprintf(
        'Worker finished for live stream %d with exit code %d',
        $liveStreamId,
        $exitCode,
    ));

    return $exitCode;
})->purpose('Run the local FFmpeg worker for a live stream');

Artisan::command('integrations:validate {accountId?}', function (?int $accountId = null) {
    $accounts = ConnectedAccount::query()
        ->when($accountId, fn ($query) => $query->whereKey($accountId))
        ->orderBy('provider')
        ->orderBy('id')
        ->get();

    if ($accounts->isEmpty()) {
        $this->warn('No connected accounts matched the request.');

        return 0;
    }

    $integrationService = app(IntegrationService::class);
    $validator = app(ProviderValidationService::class);

    foreach ($accounts as $account) {
        try {
            $account = $integrationService->refreshConnectedAccount($account);
        } catch (Throwable) {
            // Keep the original account payload so the validation result can report the failure cleanly.
        }

        $result = $validator->validateConnectedAccount($account);

        $this->line(sprintf(
            '[%s #%d] reachable=%s%s',
            $account->provider,
            $account->id,
            ($result['reachable'] ?? false) ? 'yes' : 'no',
            isset($result['error']) ? ' error='.$result['error'] : '',
        ));
    }

    return 0;
})->purpose('Validate connected provider accounts against their upstream APIs');

Artisan::command('studio:health', function () {
    $report = app(StudioHealthService::class)->report();

    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return 0;
})->purpose('Report backend runtime readiness for FFmpeg, storage, mediasoup, and providers');
