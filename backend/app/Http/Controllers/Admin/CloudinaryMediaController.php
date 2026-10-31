<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\CloudinaryImageService;
use App\Exceptions\CloudinaryServiceException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CloudinaryMediaController extends Controller
{
    public function index(Request $request, CloudinaryImageService $cloudinary): JsonResponse
    {
        $user = $request->user();
        if ($user === null
            || (! $user->hasAdminPermission('books') && ! $user->hasAdminPermission('banners'))) {
            return response()->json([
                'message' => 'Bạn không có quyền thao tác trên module này.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:128'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Tham số không hợp lệ.',
                'code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxResults = min(max($request->integer('per_page', 30), 1), 100);
        $cursor = $request->string('cursor')->trim()->toString() ?: null;
        $search = $request->string('search')->trim()->toString() ?: null;

        try {
            $result = $cloudinary->listImages($maxResults, $cursor, $search);
        } catch (CloudinaryServiceException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $e->httpStatus());
        }

        return response()->json([
            'data' => $result['items'],
            'meta' => [
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
                'search' => $search,
            ],
        ]);
    }
}
