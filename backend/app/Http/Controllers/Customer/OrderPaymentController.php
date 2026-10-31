<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Order;
use App\Services\SePayCheckoutService;
use App\Services\SePayReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function __construct(
        private SePayCheckoutService $sepayCheckout,
        private SePayReconciliationService $reconciliation,
    ) {}

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->assertOwnership($request, $order);

        $payment = $order->payment;
        if ($payment === null) {
            return response()->json([
                'message' => 'Không tìm thấy thanh toán.',
                'code' => 'PAYMENT_NOT_FOUND',
                'errors' => (object) [],
            ], 404);
        }

        $payment = $this->reconciliation->reconcilePending($payment);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function storeSession(Request $request, Order $order): JsonResponse
    {
        $this->assertOwnership($request, $order);

        $checkout = $this->sepayCheckout->createCheckoutSession($order, $request->user());

        return response()->json([
            'data' => [
                'checkout' => $checkout,
                'payment' => new PaymentResource($order->fresh('payment')->payment),
            ],
        ]);
    }

    private function assertOwnership(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
