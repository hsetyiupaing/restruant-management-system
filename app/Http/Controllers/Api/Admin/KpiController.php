<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\KpiRequest;
use App\Models\Restaurant;
use App\Services\KpiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class KpiController extends Controller
{
    public function show(KpiRequest $request, Restaurant $restaurant, KpiService $kpis): JsonResponse
    {
        Gate::authorize('viewKpis', $restaurant);

        $validated = $request->validated();

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        return response()->json([
            'data' => $kpis->summary($restaurant, $from, $to),
        ]);
    }
}
