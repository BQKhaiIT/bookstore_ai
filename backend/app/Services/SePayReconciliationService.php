<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\SePayAmount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SePayReconciliationService
{
    public function __construct(
        private SePayGatewayClient $client,
        private SePayIpnService $ipn,
    ) {}

    public function reconcilePending(Payment $payment): Payment
    {
        if (! $this->canReconcile($payment)) {
            return $payment;
        }

        $invoice = (string) $payment->invoice_number;
        $cacheKey = 'sepay-reconcile:'.$payment->id;

        if (! Cache::add($cacheKey, 1, now()->addSeconds(4))) {
            return $payment->fresh() ?? $payment;
        }

        $remote = $this->client->retrieveOrderByInvoice($invoice);
        if ($remote === null || ! $this->isTrustedCapture($payment, $invoice, $remote)) {
            return $payment->fresh() ?? $payment;
        }

        $reference = $this->transactionReference($remote, (int) $payment->amount);
        if ($reference === null) {
            return $payment->fresh() ?? $payment;
        }

        try {
            $this->ipn->recordCapturedPayment($invoice, (int) $payment->amount, $reference, [
                'sepay_order_id' => is_string($remote['order_id'] ?? null) ? $remote['order_id'] : null,
                'sepay_notification_type' => 'ORDER_RECONCILED',
                'reconciled_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::warning('SePay reconcile could not record payment', [
                'invoice' => $invoice,
            ]);
        }

        return $payment->fresh() ?? $payment;
    }

    private function canReconcile(Payment $payment): bool
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return false;
        }

        if ($payment->method !== PaymentMethod::SandboxOnline) {
            return false;
        }

        if ($payment->provider !== null && $payment->provider !== 'sepay') {
            return false;
        }

        if ($payment->environment !== null && $payment->environment !== 'sandbox') {
            return false;
        }

        if (config('sepay.env') !== 'sandbox') {
            return false;
        }

        return is_string($payment->invoice_number) && $payment->invoice_number !== '';
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function isTrustedCapture(Payment $payment, string $invoice, array $remote): bool
    {
        if (($remote['order_invoice_number'] ?? null) !== $invoice) {
            return false;
        }

        if (($remote['order_status'] ?? null) !== 'CAPTURED') {
            return false;
        }

        if (($remote['order_currency'] ?? null) !== 'VND') {
            return false;
        }

        $amount = SePayAmount::toVnd($remote['order_amount'] ?? null);
        if ($amount === null || $amount !== (int) $payment->amount) {
            return false;
        }

        $payment->loadMissing('order');
        $customerId = $remote['customer_id'] ?? null;
        $ownerId = $payment->order?->user_id;
        if (is_string($customerId) && $customerId !== '' && $ownerId !== null && $customerId !== 'user_'.$ownerId) {
            return false;
        }

        $transactions = $remote['transactions'] ?? [];
        if (! is_array($transactions) || $transactions === []) {
            return is_string($remote['order_id'] ?? null) && $remote['order_id'] !== '';
        }

        foreach ($transactions as $transaction) {
            if ($this->approvedPaymentTransaction($transaction, (int) $payment->amount) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function transactionReference(array $remote, int $expectedAmount): ?string
    {
        $transactions = $remote['transactions'] ?? [];
        if (is_array($transactions)) {
            foreach ($transactions as $transaction) {
                $reference = $this->approvedPaymentTransaction($transaction, $expectedAmount);
                if ($reference !== null) {
                    return $reference;
                }
            }
        }

        $orderId = $remote['order_id'] ?? null;

        return is_string($orderId) && $orderId !== '' ? $orderId : null;
    }

    private function approvedPaymentTransaction(mixed $transaction, ?int $expectedAmount): ?string
    {
        if (! is_array($transaction)) {
            return null;
        }

        if (($transaction['transaction_status'] ?? null) !== 'APPROVED') {
            return null;
        }

        $type = $transaction['transaction_type'] ?? 'PAYMENT';
        if ($type !== 'PAYMENT') {
            return null;
        }

        if (($transaction['transaction_currency'] ?? 'VND') !== 'VND') {
            return null;
        }

        if ($expectedAmount !== null) {
            $amount = SePayAmount::toVnd($transaction['transaction_amount'] ?? null);
            if ($amount !== $expectedAmount) {
                return null;
            }
        }

        $reference = $transaction['transaction_id'] ?? $transaction['id'] ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
