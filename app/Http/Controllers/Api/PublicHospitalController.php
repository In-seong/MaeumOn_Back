<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerHospital;
use App\Models\HospitalReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicHospitalController extends Controller
{
    /**
     * 병원 목록 (검색, 위치 기반)
     */
    public function index(Request $request): JsonResponse
    {
        $query = PartnerHospital::where('is_active', true)
            ->where('is_deleted', true);

        // 키워드 검색
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('hospital_name', 'like', "%{$search}%")
                  ->orWhere('specialties', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $hospitals = $query->orderBy('hospital_name')->get();

        return response()->json([
            'success' => true,
            'data' => $hospitals,
        ]);
    }

    /**
     * 병원 상세
     */
    public function show(int $id): JsonResponse
    {
        $hospital = PartnerHospital::with('images')
            ->where('is_active', true)
            ->where('is_deleted', true)
            ->where('hospital_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $hospital,
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

        $hospital = PartnerHospital::where('hospital_id', $id)
            ->where('is_active', true)
            ->where('is_deleted', true)
            ->firstOrFail();

        // schedule_config 기반 시간 슬롯 생성
        $allTimes = $hospital->generateTimeSlots($date);

        // 이미 예약된 시간 조회
        $bookedSlots = HospitalReservation::where('hospital_id', $id)
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
