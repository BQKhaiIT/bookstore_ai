<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\SePayAmount;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SePayIpnService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, ?string $secretHeader): void
    {
        $this->verifySecret($secretHeader);

        $notificationType = $payload['notification_type'] ?? null;
        if ($notificationType !== 'ORDER_PAID') {
            throw new HttpResponseException(response()->json([
                'message' => 'Loại thông báo không được hỗ trợ.',
                'code' => 'IPN_NOTIFICATION_UNSUPPORTED',
                'errors' => (object) [],
            ], 422));
        }

        $orderPayload = $payload['order'] ?? null;
        $transactionPayload = $payload['transaction'] ?? null;

        if (! is_array($orderPayload) || ! is_array($transactionPayload)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Payload IPN không hợp lệ.',
                'code' => 'IPN_PAYLOAD_INVALID',
                'errors' => (object) [],
            ], 422));
        }

        $invoice = $orderPayload['order_invoice_number'] ?? null;
        if (! is_string($invoice) || $invoice === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Thiếu order_invoice_number.',
                'code' => 'IPN_INVOICE_MISSING',
                'errors' => (object) [],
            ], 422));
        }

        $orderStatus = $orderPayload['order_status'] ?? null;
        $transactionStatus = $transactionPayload['transaction_status'] ?? null;
        if ($orderStatus !== 'CAPTURED' || $transactionStatus !== 'APPROVED') {
            throw new HttpResponseException(response()->json([
                'message' => 'Trạng thái giao dịch không hợp lệ.',
                'code' => 'IPN_STATUS_INVALID',
                'errors' => (object) [],
            ], 422));
        }

        $currency = $orderPayload['order_currency'] ?? null;
        if ($currency !== 'VND') {
            throw new HttpResponseException(response()->json([
                'message' => 'Currency không hợp lệ.',
                'code' => 'IPN_CURRENCY_INVALID',
                'errors' => (object) [],
            ], 422));
        }

        $expectedAmount = SePayAmount::toVnd($orderPayload['order_amount'] ?? null);
        $transactionAmount = SePayAmount::toVnd($transactionPayload['transaction_amount'] ?? null);

        if ($expectedAmount === null || $transactionAmount === null || $expectedAmount !== $transactionAmount) {
            throw new HttpResponseException(response()->json([
                'message' => 'Số tiền giao dịch không khớp.',
                'code' => 'IPN_AMOUNT_MISMATCH',
                'errors' => (object) [],
            ], 422));
        }

        $transactionId = $transactionPayload['transaction_id'] ?? null;
        if (! is_string($transactionId) || $transactionId === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'Thiếu transaction_id.',
                'code' => 'IPN_TRANSACTION_MISSING',
                'errors' => (object) [],
            ], 422));
        }

        $this->recordCapturedPayment($invoice, $expectedAmount, $transactionId, [
            'sepay_order_id' => is_string($orderPayload['order_id'] ?? null) ? $orderPayload['order_id'] : null,
            'sepay_notification_type' => $payload['notification_type'] ?? null,
            'ipn_received_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Mark one pending payment paid. A repeated confirmation for the same invoice is a no-op.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordCapturedPayment(string $invoice, int $amount, string $reference, array $metadata): void
    {
        DB::transaction(function () use ($invoice, $amount, $reference, $metadata): void {
            $payment = Payment::query()
                ->where('invoice_number', $invoice)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                Log::warning('SePay IPN: invoice not found', ['invoice' => $invoice]);

                throw new HttpResponseException(response()->json([
                    'message' => 'Không tìm thấy thanh toán.',
                    'code' => 'IPN_PAYMENT_NOT_FOUND',
                    'errors' => (object) [],
                ], 404));
            }

            if ((int) $payment->amount !== $amount) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Số tiền không khớp đơn hàng.',
                    'code' => 'IPN_AMOUNT_ORDER_MISMATCH',
                    'errors' => (object) [],
                ], 422));
            }

            if ($payment->status === PaymentStatus::Paid) {
                return;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Trạng thái thanh toán không hợp lệ.',
                    'code' => 'IPN_PAYMENT_STATUS_INVALID',
                    'errors' => (object) [],
                ], 409));
            }

            $otherPaid = Payment::query()
                ->where('order_id', $payment->order_id)
                ->where('status', PaymentStatus::Paid)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($otherPaid) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Đơn hàng đã có thanh toán thành công.',
                    'code' => 'IPN_ORDER_ALREADY_PAID',
                    'errors' => (object) [],
                ], 409));
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->transaction_reference = $reference;
            $payment->provider = $payment->provider ?? 'sepay';
            $payment->environment = $payment->environment ?? 'sandbox';
            $payment->metadata = array_merge($payment->metadata ?? [], $metadata);
            $payment->save();
        });
    }

    private function verifySecret(?string $secretHeader): void
    {
        $configured = config('sepay.secret_key');
        if (! is_string($configured) || $configured === '') {
            throw new HttpResponseException(response()->json([
                'message' => 'SePay chưa được cấu hình.',
                'code' => 'SEPAY_NOT_CONFIGURED',
                'errors' => (object) [],
            ], 503));
        }

        if (! is_string($secretHeader) || ! hash_equals($configured, $secretHeader)) {
            throw new HttpResponseException(response()->json([
                'message' => 'X-Secret-Key không hợp lệ.',
                'code' => 'IPN_UNAUTHORIZED',
                'errors' => (object) [],
            ], 401));
        }
    }
}

