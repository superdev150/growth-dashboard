<?php

namespace App\Http\Controllers;

use App\Growth\GrowthReportFactory;
use App\Growth\Period;
use App\Http\Requests\GrowthRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class GrowthController extends Controller
{
    /** GET /api/growth?days=7|30|90&refresh=0|1 */
    public function __invoke(GrowthRequest $request, GrowthReportFactory $reports): JsonResponse
    {
        $period = Period::lastDays($request->days(), CarbonImmutable::now('UTC'));

        return response()
            ->json($reports->make($request->fresh())->build($period))
            ->header('Cache-Control', 'no-store');
    }
}
