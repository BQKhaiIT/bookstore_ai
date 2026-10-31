<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\SePayIpnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SePayWebhookController extends Controller
{
    public function __construct(private SePayIpnService $ipn) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $this->ipn->handle($payload, $request->header('X-Secret-Key'));

        return response()->json(['success' => true]);
    }
}
