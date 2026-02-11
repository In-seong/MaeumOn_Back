<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 로그인
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $account = Account::where('username', $request->username)->first();

        if (!$account || !Hash::check($request->password, $account->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['아이디 또는 비밀번호가 올바르지 않습니다.'],
            ]);
        }

        // 고객은 ID/PW 로그인 불가 (OTP + PIN 방식 사용)
        if ($account->isCustomer()) {
            throw ValidationException::withMessages([
                'username' => ['고객 계정은 휴대폰 인증으로 로그인해주세요.'],
            ]);
        }

        if (!$account->is_active) {
            throw ValidationException::withMessages([
                'username' => ['This account has been deactivated.'],
            ]);
        }

        // 기존 토큰 삭제 후 새 토큰 발급
        $account->tokens()->delete();
        $token = $account->createToken('auth-token')->plainTextToken;

        // last_login_at 업데이트
        $account->update(['last_login_at' => now()]);

        // customer 관계 로드
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
            ],
            'message' => '로그인 성공',
        ]);
    }

    /**
     * 로그아웃
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '로그아웃 성공',
        ]);
    }

    /**
     * 현재 사용자 정보
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => $account,
        ]);
    }

    /**
     * 회원가입 (테스트용)
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:account,username',
            'password' => 'required|min:6|confirmed',
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ]);

        $account = Account::create([
            'username' => $request->username,
            'password_hash' => Hash::make($request->password),
            'role' => Account::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // customer_id 생성 (8자리 문자열)
        $customerId = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        while (Customer::where('customer_id', $customerId)->exists()) {
            $customerId = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        }

        Customer::create([
            'customer_id' => $customerId,
            'account_id' => $account->account_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'is_active' => true,
        ]);

        $token = $account->createToken('auth-token')->plainTextToken;

        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
            ],
            'message' => '회원가입 성공',
        ], 201);
    }
}
