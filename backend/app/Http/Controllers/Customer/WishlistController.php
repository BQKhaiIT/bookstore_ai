<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\BookResource;
use App\Models\Book;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with(['book.publisher', 'book.authors', 'book.categories'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Wishlist $w) => $w->book)
            ->filter(fn (?Book $book) => $book && $book->status === BookStatus::Published);

        return response()->json([
            'data' => BookResource::collection($items->values()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $book = Book::query()->findOrFail($validated['book_id']);
        if ($book->status !== BookStatus::Published) {
            return response()->json([
                'message' => 'Sách không khả dụng.',
                'code' => 'BOOK_UNAVAILABLE',
                'errors' => (object) [],
            ], 409);
        }

        Wishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
        ]);

        return response()->json(['data' => ['book_id' => $book->id]], 201);
    }

    public function destroy(Request $request, Book $book): JsonResponse
    {
        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->delete();

        return response()->json(null, 204);
    }
}
