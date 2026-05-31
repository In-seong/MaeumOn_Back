<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HospitalAccount;
use App\Models\HospitalReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class HospitalPortalController extends Controller
{
    /**
     * 병원 포털 로그인
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $account = HospitalAccount::where('username', $validated['username'])
            ->where('is_active', true)
            ->first();

        if (!$account || !Hash::check($validated['password'], $account->password)) {
            return response()->json([
                'success' => false,
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // 간단한 토큰 생성 (병원 포털 전용)
        $token = bin2hex(random_bytes(32));
        cache()->put("hospital_portal_token:{$token}", [
            'account_id' => $account->account_id,
            'hospital_id' => $account->hospital_id,
            'center_id' => $account->center_id,
        ], now()->addHours(12));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'account_name' => $account->account_name,
                'hospital_id' => $account->hospital_id,
                'center_id' => $account->center_id,
            ],
            'message' => '로그인 성공',
        ]);
    }

    /**
     * 오늘의 예약 목록
     */
    public function reservations(Request $request): JsonResponse
    {
        $portalAuth = $this->getPortalAuth($request);
        if (!$portalAuth) {
            return response()->json(['success' => false, 'message' => '인증이 필요합니다.'], 401);
        }

        $date = $request->input('date', now()->format('Y-m-d'));

        $query = HospitalReservation::where('reservation_date', $date);

        if ($portalAuth['hospital_id']) {
            $query->where('hospital_id', $portalAuth['hospital_id']);
        } elseif ($portalAuth['center_id']) {
            $query->where('center_id', $portalAuth['center_id']);
        }

        $reservations = $query->orderBy('reservation_time')->get();

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    /**
     * 예약 상태 변경
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $portalAuth = $this->getPortalAuth($request);
        if (!$portalAuth) {
            return response()->json(['success' => false, 'message' => '인증이 필요합니다.'], 401);
        }

        $validated = $request->validate([
            'status' => 'required|in:confirmed,cancelled,completed',
        ]);

        $query = HospitalReservation::where('reservation_id', $id);

        if ($portalAuth['hospital_id']) {
            $query->where('hospital_id', $portalAuth['hospital_id']);
        } elseif ($portalAuth['center_id']) {
            $query->where('center_id', $portalAuth['center_id']);
        }

        $reservation = $query->firstOrFail();
        $reservation->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'data' => $reservation,
            'message' => '예약 상태가 변경되었습니다.',
        ]);
    }

    /**
     * 포털 인증 정보 가져오기
     */
    private function getPortalAuth(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        return cache()->get("hospital_portal_token:{$token}");
    }
}
