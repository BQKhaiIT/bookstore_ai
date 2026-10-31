<?php

namespace App\Services;

use App\Enums\BookStatus;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class CartService
{
    public function forUser(User $user): Cart
    {
        return Cart::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.book' => fn ($q) => $q->with(['publisher', 'authors', 'categories']),
        ]);
    }

    public function addItem(User $user, Book $book, int $quantity): Cart
    {
        if ($book->status !== BookStatus::Published) {
            throw new HttpResponseException(response()->json([
                'message' => 'Sách không khả dụng.',
                'code' => 'BOOK_UNAVAILABLE',
                'errors' => (object) [],
            ], 409));
        }

        if ($book->stock_quantity < 1) {
            throw new HttpResponseException(response()->json([
                'message' => 'Sách đã hết hàng.',
                'code' => 'OUT_OF_STOCK',
                'errors' => (object) [],
            ], 409));
        }

        $quantity = max(1, min($quantity, $book->stock_quantity));

        $cart = $this->forUser($user);
        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'book_id' => $book->id,
        ]);

        $newQty = ($item->exists ? $item->quantity : 0) + $quantity;
        if ($newQty > $book->stock_quantity) {
            throw new HttpResponseException(response()->json([
                'message' => 'Số lượng vượt quá tồn kho.',
                'code' => 'INSUFFICIENT_STOCK',
                'errors' => (object) [],
            ], 409));
        }

        $item->quantity = $newQty;
        $item->save();

        return $this->loadCart($cart);
    }

    public function updateQuantity(User $user, CartItem $item, int $quantity): Cart
    {
        $this->assertItemOwnership($user, $item);

        $book = $item->book()->firstOrFail();

        if ($book->status !== BookStatus::Published) {
            throw new HttpResponseException(response()->json([
                'message' => 'Sách không khả dụng.',
                'code' => 'BOOK_UNAVAILABLE',
                'errors' => (object) [],
            ], 409));
        }

        if ($quantity < 1) {
            $item->delete();

            return $this->loadCart($item->cart);
        }

        if ($quantity > $book->stock_quantity) {
            throw new HttpResponseException(response()->json([
                'message' => 'Số lượng vượt quá tồn kho.',
                'code' => 'INSUFFICIENT_STOCK',
                'errors' => (object) [],
            ], 409));
        }

        $item->quantity = $quantity;
        $item->save();

        return $this->loadCart($item->cart);
    }

    public function removeItem(User $user, CartItem $item): Cart
    {
        $this->assertItemOwnership($user, $item);
        $cart = $item->cart;
        $item->delete();

        return $this->loadCart($cart);
    }

    private function assertItemOwnership(User $user, CartItem $item): void
    {
        $item->loadMissing('cart');
        if ($item->cart->user_id !== $user->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Bạn không có quyền thực hiện thao tác này.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403));
        }
    }
}
