<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function confirmCod(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return $payment->load('order');
            }

            if ($payment->method !== PaymentMethod::Cod) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Chỉ xác nhận thanh toán COD.',
                    'code' => 'PAYMENT_METHOD_INVALID',
                    'errors' => (object) [],
                ], 422));
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Trạng thái thanh toán không hợp lệ.',
                    'code' => 'PAYMENT_STATUS_INVALID',
                    'errors' => (object) [],
                ], 409));
            }

            $existingPaid = Payment::query()
                ->where('order_id', $payment->order_id)
                ->where('status', PaymentStatus::Paid)
                ->where('id', '!=', $payment->id)
                ->exists();

            if ($existingPaid) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Đơn hàng đã có thanh toán thành công.',
                    'code' => 'PAYMENT_ALREADY_PAID',
                    'errors' => (object) [],
                ], 409));
            }

            $payment->status = PaymentStatus::Paid;
            $payment->paid_at = now();
            $payment->save();

            return $payment->fresh('order');
        });
    }
}
