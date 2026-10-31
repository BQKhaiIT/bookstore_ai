<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\OrderResource;
use App\Models\Order;
use App\Services\OrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderStatusService $orderStatus) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['payment']);

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        $query->orderByDesc('created_at');
        $perPage = min(max($request->integer('per_page', 10), 1), 50);

        return OrderResource::collection($query->paginate($perPage))->response();
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->assertOwnership($request, $order);

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment'])),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->assertOwnership($request, $order);

        if ($order->status !== OrderStatus::Pending) {
            return response()->json([
                'message' => 'Không thể hủy đơn ở trạng thái hiện tại.',
                'code' => 'ORDER_CANCEL_NOT_ALLOWED',
                'errors' => (object) [],
            ], 409);
        }

        $order = $this->orderStatus->transition($order, OrderStatus::Cancelled);

        return response()->json([
            'data' => new OrderResource($order->load(['items', 'payment'])),
        ]);
    }

    private function assertOwnership(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
