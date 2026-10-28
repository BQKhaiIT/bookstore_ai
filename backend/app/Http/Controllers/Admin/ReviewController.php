<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Http\Resources\Admin\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Review::query()->with(['user', 'book']);

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($bookId = $request->integer('book_id')) {
            $query->where('book_id', $bookId);
        }

        $query->orderByDesc('created_at');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return ReviewResource::collection($query->paginate($perPage))->response();
    }

    public function update(UpdateReviewRequest $request, Review $review): JsonResponse
    {
        $status = ReviewStatus::from($request->validated('status'));

        if (! in_array($status, [ReviewStatus::Approved, ReviewStatus::Rejected], true)) {
            return response()->json([
                'message' => 'Chỉ được duyệt hoặc từ chối đánh giá.',
                'code' => 'VALIDATION_ERROR',
                'errors' => ['status' => ['Trạng thái không hợp lệ.']],
            ], 422);
        }

        $review->status = $status;
        $review->save();

        return response()->json([
            'data' => new ReviewResource($review->fresh()->load(['user', 'book'])),
        ]);
    }
}
