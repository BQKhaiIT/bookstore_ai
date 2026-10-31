<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class VerifyCsrfToken extends PreventRequestForgery
{
    /**
     * @var array<int, string>
     */
    protected $except = [
        'api/v1/payments/webhooks/sepay',
    ];

    protected function runningUnitTests(): bool
    {
        return false;
    }
}
