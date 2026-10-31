<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SePayCheckoutReturnController extends Controller
{
    public function __invoke(Request $request, Order $order): RedirectResponse
    {
        $outcome = $request->string('outcome')->toString();
        if (! in_array($outcome, ['success', 'error', 'cancel'], true)) {
            $outcome = 'success';
        }

        $frontend = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away($frontend.'/orders/'.$order->id.'/payment?outcome='.$outcome);
    }
}
