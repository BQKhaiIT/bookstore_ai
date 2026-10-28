<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Admin\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderStatusService $orderStatus) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()->with(['user', 'payment']);

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('order_code', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', '%'.$search.'%'));
            });
        }

        $query->orderByDesc('created_at');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return OrderResource::collection($query->paginate($perPage))->response();
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load(['user', 'items', 'payment'])),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $status = OrderStatus::from($request->validated('status'));
        $order = $this->orderStatus->transition($order, $status);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }
}
