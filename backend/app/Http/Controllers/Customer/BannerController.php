<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()->activeForDisplay();

        if ($type = $request->string('type')->trim()->toString()) {
            $query->where('type', $type);
        }

        if ($position = $request->string('position')->trim()->toString()) {
            $query->where('position', $position);
        }

        return response()->json([
            'data' => BannerResource::collection($query->get()),
        ]);
    }
}
