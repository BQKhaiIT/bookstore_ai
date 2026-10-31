<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CartResource;
use App\Models\Book;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->loadCart(
            $this->cartService->forUser($request->user()),
        );

        return response()->json([
            'data' => new CartResource($cart),
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
        ]);

        $book = Book::query()->findOrFail($validated['book_id']);
        $cart = $this->cartService->addItem(
            $request->user(),
            $book,
            (int) ($validated['quantity'] ?? 1),
        );

        return response()->json([
            'data' => new CartResource($cart),
        ]);
    }

    public function updateItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $cart = $this->cartService->updateQuantity(
            $request->user(),
            $cartItem,
            (int) $validated['quantity'],
        );

        return response()->json([
            'data' => new CartResource($cart),
        ]);
    }

    public function destroyItem(Request $request, CartItem $cartItem): JsonResponse
    {
        $cart = $this->cartService->removeItem($request->user(), $cartItem);

        return response()->json([
            'data' => new CartResource($cart),
        ]);
    }
}
