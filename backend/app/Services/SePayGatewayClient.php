<?php

namespace App\Services;

use Composer\CaBundle\CaBundle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SePayGatewayClient
{
    /**
     * Order detail keyed by merchant invoice number.
     * Official SDK: GET /v1/order/detail/{order_invoice_number}.
     *
     * @return array<string, mixed>|null
     */
    public function retrieveOrderByInvoice(string $invoice): ?array
    {
        $merchant = config('sepay.merchant_id');
        $secret = config('sepay.secret_key');
        $base = rtrim((string) config('sepay.api_base_url'), '/');

        if (! is_string($merchant) || trim($merchant) === '' || ! is_string($secret) || trim($secret) === '' || $base === '') {
            return null;
        }

        try {
            $response = Http::withBasicAuth($merchant, $secret)
                ->acceptJson()
                ->withOptions(['verify' => CaBundle::getBundledCaBundlePath()])
                ->connectTimeout(5)
                ->timeout(8)
                ->get($base.'/v1/order/detail/'.rawurlencode($invoice));
        } catch (Throwable $e) {
            Log::warning('SePay order lookup failed', [
                'invoice' => $invoice,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('SePay order lookup not confirmed', [
                'invoice' => $invoice,
                'http_status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }
}
