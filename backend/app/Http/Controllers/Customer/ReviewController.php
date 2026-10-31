<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ReviewResource;
use App\Models\Book;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function indexForBook(Book $book): JsonResponse
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        $reviews = Review::query()
            ->where('book_id', $book->id)
            ->where('status', ReviewStatus::Approved)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ReviewResource::collection($reviews)->response();
    }

    public function mineForBook(Request $request, Book $book): JsonResponse
    {
        $review = Review::query()
            ->where('book_id', $book->id)
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'data' => $review ? new ReviewResource($review) : null,
        ]);
    }

    public function store(Request $request, Book $book): JsonResponse
    {
        if ($book->status !== BookStatus::Published) {
            abort(404);
        }

        if (! $this->userPurchasedBook($request->user()->id, $book->id)) {
            return response()->json([
                'message' => 'Chỉ khách đã mua sách mới được đánh giá.',
                'code' => 'REVIEW_NOT_ELIGIBLE',
                'errors' => (object) [],
            ], 403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $review = Review::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'book_id' => $book->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'status' => ReviewStatus::Pending,
            ],
        );

        return response()->json([
            'data' => new ReviewResource($review),
        ], 201);
    }

    public function update(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $review->fill($validated);
        $review->status = ReviewStatus::Pending;
        $review->save();

        return response()->json([
            'data' => new ReviewResource($review),
        ]);
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        if ($review->user_id !== $request->user()->id) {
            abort(403);
        }

        $review->delete();

        return response()->json(null, 204);
    }

    private function userPurchasedBook(int $userId, int $bookId): bool
    {
        return OrderItem::query()
            ->where('book_id', $bookId)
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }
}
