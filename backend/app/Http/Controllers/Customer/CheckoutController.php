<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\AddressResource;
use App\Http\Resources\Customer\OrderResource;
use App\Models\UserAddress;
use App\Services\CheckoutService;
use App\Services\SePayCheckoutService;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private SePayCheckoutService $sepayCheckout,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['sometimes', 'integer', 'exists:user_addresses,id'],
        ]);

        $address = null;
        if (! empty($validated['address_id'])) {
            $address = UserAddress::query()
                ->where('user_id', $request->user()->id)
                ->whereKey($validated['address_id'])
                ->firstOrFail();
        }

        $preview = $this->checkout->preview($request->user(), $address);

        return response()->json([
            'data' => [
                'subtotal_amount' => $preview['subtotal_amount'],
                'shipping_fee' => $preview['shipping_fee'],
                'total_amount' => $preview['total_amount'],
                'items' => $preview['items'],
                'shipping_address' => $address ? new AddressResource($address) : null,
                'payment_methods' => $this->availablePaymentMethods(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer', 'exists:user_addresses,id'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['sometimes', 'string', Rule::in([PaymentMethod::Cod->value, PaymentMethod::SandboxOnline->value])],
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || strlen(trim($idempotencyKey)) < 8) {
            return response()->json([
                'message' => 'Thiếu hoặc Idempotency-Key không hợp lệ.',
                'code' => 'IDEMPOTENCY_KEY_REQUIRED',
                'errors' => (object) [],
            ], 422);
        }

        $address = UserAddress::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($validated['address_id'])
            ->firstOrFail();

        $paymentMethod = PaymentMethod::tryFrom($validated['payment_method'] ?? PaymentMethod::Cod->value)
            ?? PaymentMethod::Cod;

        if ($paymentMethod === PaymentMethod::SandboxOnline) {
            $this->sepayCheckout->assertSandboxOnly();
        }

        $order = $this->checkout->placeOrder(
            $request->user(),
            $address,
            trim($idempotencyKey),
            $validated['customer_note'] ?? null,
            $paymentMethod,
        );

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function availablePaymentMethods(): array
    {
        $methods = [
            ['code' => PaymentMethod::Cod->value, 'label' => 'Thanh toán khi nhận hàng (COD)'],
        ];

        if (config('sepay.env') === 'sandbox'
            && is_string(config('sepay.merchant_id'))
            && config('sepay.merchant_id') !== ''
            && is_string(config('sepay.secret_key'))
            && config('sepay.secret_key') !== '') {
            $methods[] = [
                'code' => PaymentMethod::SandboxOnline->value,
                'label' => 'Thanh toán QR — Môi trường thử nghiệm',
            ];
        }

        return $methods;
    }
}
