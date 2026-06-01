<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HospitalReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HospitalReservationController extends Controller
{
    /**
     * 내 예약 조회 (전화번호 기반)
     */
    public function myReservations(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));

        $reservations = HospitalReservation::whereRaw("REPLACE(REPLACE(patient_phone, '-', ''), ' ', '') = ?", [$phone])
            ->with(['hospital:hospital_id,hospital_name,address,contact_phone', 'healthCenter:center_id,center_name,address,contact_phone'])
            ->orderByDesc('reservation_date')
            ->orderByDesc('reservation_time')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * 예약 취소 (본인 전화번호 확인)
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));

        $reservation = HospitalReservation::where('reservation_id', $id)
            ->whereRaw("REPLACE(REPLACE(patient_phone, '-', ''), ' ', '') = ?", [$phone])
            ->whereIn('status', ['pending', 'confirmed'])
            ->firstOrFail();

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => '예약이 취소되었습니다.',
        ]);
    }

    /**
     * 예약 신청 (공개 API)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hospital_id' => 'nullable|integer|exists:partner_hospital,hospital_id',
            'center_id' => 'nullable|integer|exists:health_center,center_id',
            'reservation_type' => 'required|in:hospital,health_center',
            'patient_name' => 'required|string|max:50',
            'patient_phone' => 'required|string|max:20',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required|string|max:10',
            'memo' => 'nullable|string|max:2000',
        ]);

        // hospital_id 또는 center_id 중 하나 필수
        if ($validated['reservation_type'] === 'hospital' && empty($validated['hospital_id'])) {
            return response()->json([
                'success' => false,
                'message' => '병원을 선택해주세요.',
            ], 422);
        }

        if ($validated['reservation_type'] === 'health_center' && empty($validated['center_id'])) {
            return response()->json([
                'success' => false,
                'message' => '건강검진 센터를 선택해주세요.',
            ], 422);
        }

        // 중복 예약 확인
        $existingQuery = HospitalReservation::where('reservation_date', $validated['reservation_date'])
            ->where('reservation_time', $validated['reservation_time'])
            ->whereIn('status', ['pending', 'confirmed']);

        if ($validated['reservation_type'] === 'hospital') {
            $existingQuery->where('hospital_id', $validated['hospital_id']);
        } else {
            $existingQuery->where('center_id', $validated['center_id']);
        }

        if ($existingQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => '해당 시간에 이미 예약이 있습니다. 다른 시간을 선택해주세요.',
            ], 422);
        }

        $reservation = HospitalReservation::create($validated);

        return response()->json([
            'success' => true,
            'data' => $reservation,
            'message' => '예약이 접수되었습니다.',
        ], 201);
    }
}
