<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mikrotik\MikrotikPushRequest;
use App\Services\Mikrotik\MikrotikPushIngestionService;
use Illuminate\Http\JsonResponse;

class MikrotikPushController extends Controller
{
    public function __invoke(
        MikrotikPushRequest $request,
        MikrotikPushIngestionService $ingestionService,
    ): JsonResponse {
        $ingestionService->logAccessAttempt($request);

        if (! $ingestionService->hasValidToken($request)) {
            $ingestionService->logUnauthorizedAttempt($request);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized MikroTik push request.',
            ], 403);
        }

        return response()->json($ingestionService->ingest($request->validated()));
    }
}
