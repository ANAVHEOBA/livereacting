<?php

namespace App\Modules\Integrations\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Destinations\Resources\DestinationResource;
use App\Modules\Integrations\Requests\ImportConnectedAssetRequest;
use App\Modules\Integrations\Requests\ListProviderAssetsRequest;
use App\Modules\Integrations\Requests\ResolveConnectedAccountRequest;
use App\Modules\Integrations\Requests\SlackNotifyTestRequest;
use App\Modules\Integrations\Requests\StoreIntegrationDestinationRequest;
use App\Modules\Integrations\Resources\ConnectedAccountResource;
use App\Modules\Integrations\Services\IntegrationService;
use App\Modules\Integrations\Services\ProviderValidationService;
use App\Modules\Videos\Resources\FileImportResource;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected IntegrationService $integrationService,
        protected ProviderValidationService $providerValidationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->integrationService->listConnectedAccounts(
            $request->user(),
            $request->query('provider')
        );

        return $this->success([
            'providers' => $this->integrationService->getProviderCatalog(),
            'accounts' => ConnectedAccountResource::collection($accounts),
            'total' => $accounts->count(),
        ]);
    }

    public function authorize(Request $request, string $provider): JsonResponse
    {
        try {
            return $this->success(
                $this->integrationService->buildAuthorizationUrl($request->user(), $provider)
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function callback(Request $request, string $provider): JsonResponse|RedirectResponse
    {
        try {
            $account = $this->integrationService->handleCallback($provider, $request->all());

            if ($request->expectsJson()) {
                return $this->success(
                    new ConnectedAccountResource($account),
                    'Provider connected successfully'
                );
            }

            return redirect()->to($this->integrationService->buildFrontendCallbackUrl(
                $provider,
                true,
                ['account_id' => $account->id]
            ));
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return $this->error($e->getMessage(), 400);
            }

            return redirect()->to($this->integrationService->buildFrontendCallbackUrl(
                $provider,
                false,
                ['message' => $e->getMessage()]
            ));
        }
    }

    public function assets(ListProviderAssetsRequest $request, string $provider): JsonResponse
    {
        try {
            $assets = $this->integrationService->listAssets(
                $request->user(),
                $provider,
                $request->validated()['connected_account_id'],
                $request->safe()->only(['search', 'limit'])
            );

            return $this->success([
                'assets' => $assets,
                'total' => count($assets),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destinations(ResolveConnectedAccountRequest $request, string $provider): JsonResponse
    {
        try {
            $destinations = $this->integrationService->listDestinationOptions(
                $request->user(),
                $provider,
                $request->validated()['connected_account_id']
            );

            return $this->success([
                'destinations' => $destinations,
                'total' => count($destinations),
            ]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function validate(ResolveConnectedAccountRequest $request, string $provider): JsonResponse
    {
        try {
            $account = $this->integrationService->refreshConnectedAccount(
                $this->integrationService->getConnectedAccount(
                    $request->user(),
                    $provider,
                    $request->validated()['connected_account_id']
                )
            );

            return $this->success(
                $this->providerValidationService->validateConnectedAccount($account),
                'Connected account validated successfully'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function storeDestination(StoreIntegrationDestinationRequest $request, string $provider): JsonResponse
    {
        try {
            $destination = $this->integrationService->createDestination(
                $request->user(),
                $provider,
                $request->validated()
            );

            return $this->success(
                new DestinationResource($destination),
                'Destination created successfully',
                201
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function importAsset(ImportConnectedAssetRequest $request, string $provider): JsonResponse
    {
        try {
            $import = $this->integrationService->importAsset(
                $request->user(),
                $provider,
                $request->validated()
            );

            return $this->success(
                new FileImportResource($import),
                'Provider asset import started successfully',
                201
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function notifySlack(SlackNotifyTestRequest $request): JsonResponse
    {
        try {
            return $this->success(
                $this->integrationService->sendSlackTest($request->user(), $request->validated()),
                'Slack notification sent successfully'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function destroy(Request $request, string $provider, int $id): JsonResponse
    {
        try {
            $this->integrationService->disconnectAccount($request->user(), $provider, $id);

            return $this->success(null, 'Connected account removed successfully');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
