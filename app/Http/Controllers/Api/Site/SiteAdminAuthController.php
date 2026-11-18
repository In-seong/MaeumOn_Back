<?php

namespace App\Http\Controllers\Api\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SiteAdminAuthController extends Controller
{
    /**
     * 관리자 로그인
     * POST /api/admin/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = SiteAdmin::where('username', $data['username'])->first();

        // 타이밍 공격 방지: 계정이 없어도 bcrypt 비교 한 번 수행
        $dummyHash = '$2y$12$dummyhashForTimingAttackPreventionXXXXXXXXXXXXXX';
        $hashToCheck = $admin ? $admin->password : $dummyHash;
        $valid = Hash::check($data['password'], $hashToCheck);

        if (!$admin || !$valid) {
            return response()->json(['error' => '아이디 또는 비밀번호가 올바르지 않습니다.'], 401);
        }

        if (!$admin->is_active) {
            return response()->json(['error' => '아이디 또는 비밀번호가 올바르지 않습니다.'], 401);
        }

        $admin->update(['last_login_at' => now()]);

        return response()->json([
            'id' => $admin->id,
            'username' => $admin->username,
            'name' => $admin->name,
        ]);
    }
}
