<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private AdminDashboardService $dashboard) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->stats(),
        ]);
    }
}
