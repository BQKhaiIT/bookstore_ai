<?php

return [
    'env' => env('SEPAY_ENV', 'sandbox'),

    'merchant_id' => env('SEPAY_MERCHANT_ID'),

    'secret_key' => env('SEPAY_SECRET_KEY'),

    /*
     * Public HTTPS base URL reachable by SePay (Cloudflare tunnel to Laravel, etc.).
     * Used for checkout return URLs (success/error/cancel) and IPN endpoint construction.
     */
    'public_base_url' => rtrim((string) env('SEPAY_PUBLIC_BASE_URL', env('APP_URL', 'http://localhost')), '/'),

    'checkout_init_url' => env('SEPAY_ENV', 'sandbox') === 'production'
        ? 'https://pay.sepay.vn/v1/checkout/init'
        : 'https://pay-sandbox.sepay.vn/v1/checkout/init',

    /*
     * REST API for order lookup. Detail is GET {api_base_url}/v1/order/detail/{invoice_number}
     * with Basic auth (merchant_id:secret_key). Sandbox only until production is enabled.
     */
    'api_base_url' => env('SEPAY_ENV', 'sandbox') === 'production'
        ? 'https://pgapi.sepay.vn'
        : 'https://pgapi-sandbox.sepay.vn',
];
