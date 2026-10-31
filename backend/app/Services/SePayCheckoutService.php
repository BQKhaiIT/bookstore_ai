<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\SePayCheckoutSigner;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SePayCheckoutService
{
    public function __construct(private SePayReconciliationService $reconciliation) {}

    public function assertSandboxOnly(): void
    {
        if (config('sepay.env') !== 'sandbox') {
            throw new HttpResponseException(response()->json([
                'message' => 'Chỉ hỗ trợ SePay Sandbox trong giai đoạn hiện tại.',
                'code' => 'SEPAY_ENV_NOT_ALLOWED',
                'errors' => (object) [],
            ], 503));
        }
    }

    /**
     * @return array{action_url: string, method: string, fields: array<string, string>, field_order: list<string>}
     */
    public function createCheckoutSession(Order $order, User $user): array
    {
        $this->assertConfigured();
        $this->assertSandboxOnly();

        $existing = Payment::query()->where('order_id', $order->id)->latest('id')->first();
        if ($existing !== null) {
            $this->reconciliation->reconcilePending($existing);
        }

        $payment = DB::transaction(function () use ($order) {
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($payment === null) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Không tìm thấy thanh toán cho đơn hàng.',
                    'code' => 'PAYMENT_NOT_FOUND',
                    'errors' => (object) [],
                ], 404));
            }

            if ($payment->method !== PaymentMethod::SandboxOnline) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Đơn hàng không dùng thanh toán SePay Sandbox.',
                    'code' => 'PAYMENT_METHOD_INVALID',
                    'errors' => (object) [],
                ], 422));
            }

            if ($payment->status === PaymentStatus::Paid) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Đơn hàng đã thanh toán.',
                    'code' => 'PAYMENT_ALREADY_PAID',
                    'errors' => (object) [],
                ], 409));
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Trạng thái thanh toán không hợp lệ.',
                    'code' => 'PAYMENT_STATUS_INVALID',
                    'errors' => (object) [],
                ], 409));
            }

            if ($payment->invoice_number === null) {
                $payment->invoice_number = $this->generateInvoiceNumber($order);
                $payment->provider = 'sepay';
                $payment->environment = 'sandbox';
                $payment->save();
            }

            return $payment->fresh();
        });

        $publicBase = rtrim((string) config('sepay.public_base_url'), '/');
        $returnPath = '/payment/sepay/return/'.$order->id;

        $fields = [
            'merchant' => (string) config('sepay.merchant_id'),
            'currency' => 'VND',
            'order_amount' => (string) $payment->amount,
            'operation' => 'PURCHASE',
            'order_description' => 'Thanh toan don '.$order->order_code,
            'payment_method' => 'BANK_TRANSFER',
            'order_invoice_number' => (string) $payment->invoice_number,
            'customer_id' => 'user_'.$user->id,
            'success_url' => $publicBase.$returnPath.'?outcome=success',
            'error_url' => $publicBase.$returnPath.'?outcome=error',
            'cancel_url' => $publicBase.$returnPath.'?outcome=cancel',
        ];

        $secret = (string) config('sepay.secret_key');
        $signature = SePayCheckoutSigner::sign($fields, $secret);

        $stringFields = array_map(strval(...), $fields);
        $stringFields['signature'] = $signature;

        return [
            'action_url' => (string) config('sepay.checkout_init_url'),
            'method' => 'POST',
            'fields' => $stringFields,
            'field_order' => SePayCheckoutSigner::FORM_FIELD_ORDER,
        ];
    }

    private function generateInvoiceNumber(Order $order): string
    {
        do {
            $invoice = 'BS-'.$order->id.'-'.Str::upper(Str::random(10));
        } while (Payment::query()->where('invoice_number', $invoice)->exists());

        return $invoice;
    }

    private function assertConfigured(): void
    {
        $merchant = config('sepay.merchant_id');
        $secret = config('sepay.secret_key');

        if (! is_string($merchant) || trim($merchant) === '' || ! is_string($secret) || trim($secret) === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'SePay chưa được cấu hình.',
                'code' => 'SEPAY_NOT_CONFIGURED',
                'errors' => (object) [],
            ], 503));
        }
    }
}
