<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with('order');

        if ($orderId = $request->integer('order_id')) {
            $query->where('order_id', $orderId);
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        $query->orderByDesc('created_at');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return PaymentResource::collection($query->paginate($perPage))->response();
    }

    public function confirmCod(Payment $payment): JsonResponse
    {
        $payment = $this->payments->confirmCod($payment);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }
}
