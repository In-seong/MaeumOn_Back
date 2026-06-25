<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthCenter;
use App\Models\HospitalReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHealthCenterController extends Controller
{
    /**
     * 건강검진 센터 목록
     */
    public function index(Request $request): JsonResponse
    {
        $query = HealthCenter::where('is_active', true)->where('is_deleted', true);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('center_name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $centers = $query->orderBy('center_name')->get();

        return response()->json([
            'success' => true,
            'data' => $centers,
        ]);
    }

    /**
     * 센터 상세
     */
    public function show(int $id): JsonResponse
    {
        $center = HealthCenter::with('images')
            ->where('is_active', true)
            ->where('is_deleted', true)
            ->where('center_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $center,
        ]);
    }

    /**
     * 예약 가능 시간 조회
     */
    public function availableSlots(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
        ]);

        $date = $request->input('date');

        $center = HealthCenter::where('center_id', $id)
            ->where('is_active', true)
            ->where('is_deleted', true)
            ->firstOrFail();

        // schedule_config 기반 시간 슬롯 생성
        $allTimes = $center->generateTimeSlots($date);

        $bookedSlots = HospitalReservation::where('center_id', $id)
            ->where('reservation_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('reservation_time')
            ->toArray();

        $slots = array_map(function ($time) use ($bookedSlots) {
            return [
                'time' => $time,
                'available' => !in_array($time, $bookedSlots),
            ];
        }, $allTimes);

        return response()->json([
            'success' => true,
            'data' => $slots,
        ]);
    }
}
