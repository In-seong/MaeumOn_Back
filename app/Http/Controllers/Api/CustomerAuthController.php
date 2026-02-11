<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerAuthController extends Controller
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
     * Step 1: OTP 발송
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
        ]);

        $result = $this->otpService->generate($request->phone);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 429);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'debug_otp' => $result['debug_otp'] ?? null,
            ],
            'message' => $result['message'],
        ]);
    }

    /**
     * Step 2: OTP 검증
     * - 기존 회원: { is_new: false, has_pin: true/false }
     * - 신규 회원: { is_new: true }
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'otp' => 'required|string|size:6',
        ]);

        if (!$this->otpService->verify($request->phone, $request->otp)) {
            return response()->json([
                'success' => false,
                'message' => '인증번호가 올바르지 않거나 만료되었습니다.',
            ], 422);
        }

        // 고객 조회
        $customer = Customer::where('phone', $request->phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_new' => true,
                    'has_pin' => false,
                    'phone' => $request->phone,
                ],
                'message' => '인증 성공. 신규 회원입니다.',
            ]);
        }

        $account = $customer->account;
        $hasPin = !empty($account->pin_hash);

        return response()->json([
            'success' => true,
            'data' => [
                'is_new' => false,
                'has_pin' => $hasPin,
                'phone' => $request->phone,
                'customer_name' => $customer->name,
            ],
            'message' => '인증 성공.',
        ]);
    }

    /**
     * Step 3-A: 신규 회원 등록 + PIN 설정 + 디바이스 등록
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'name' => 'required|string|max:100',
            'pin' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'device_uuid' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:100',
        ]);

        // OTP 인증 완료 여부 확인
        if (!$this->otpService->isVerified($request->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP 인증이 필요합니다.',
            ], 403);
        }

        // 이미 등록된 번호인지 확인
        if (Customer::where('phone', $request->phone)->exists()) {
            return response()->json([
                'success' => false,
                'message' => '이미 등록된 전화번호입니다.',
            ], 409);
        }

        // Account 생성 (username = phone)
        $account = Account::create([
            'username' => $request->phone,
            'password_hash' => Hash::make(Str::random(32)), // 임의 비밀번호 (사용 안 함)
            'pin_hash' => Hash::make($request->pin),
            'role' => Account::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // Customer ID 생성 (C + 7자리)
        $lastCustomer = Customer::where('customer_id', 'like', 'C%')
            ->orderByRaw('CAST(SUBSTRING(customer_id, 2) AS UNSIGNED) DESC')
            ->first();
        $nextNum = $lastCustomer
            ? (int) substr($lastCustomer->customer_id, 1) + 1
            : 1;
        $customerId = 'C' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

        // Customer 생성
        Customer::create([
            'customer_id' => $customerId,
            'account_id' => $account->account_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'is_active' => true,
        ]);

        // 디바이스 토큰 등록
        $deviceTokenPlain = Str::random(64);
        DeviceToken::create([
            'account_id' => $account->account_id,
            'device_uuid' => $request->device_uuid,
            'token_hash' => Hash::make($deviceTokenPlain),
            'device_name' => $request->device_name,
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        // OTP 인증 플래그 소모
        $this->otpService->consumeVerification($request->phone);

        // Sanctum 토큰 발급
        $token = $account->createToken('customer-auth')->plainTextToken;
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
                'device_token' => $deviceTokenPlain,
            ],
            'message' => '회원가입이 완료되었습니다.',
        ], 201);
    }

    /**
     * Step 3-B: 기존 회원 PIN 설정 (최초 1회)
     */
    public function setPin(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'pin' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'device_uuid' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:100',
        ]);

        // OTP 인증 완료 여부 확인
        if (!$this->otpService->isVerified($request->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP 인증이 필요합니다.',
            ], 403);
        }

        $customer = Customer::where('phone', $request->phone)->first();
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => '등록된 회원이 아닙니다.',
            ], 404);
        }

        $account = $customer->account;

        // PIN 설정
        $account->update(['pin_hash' => Hash::make($request->pin)]);

        // 디바이스 토큰 등록/갱신
        $deviceTokenPlain = Str::random(64);
        DeviceToken::updateOrCreate(
            [
                'account_id' => $account->account_id,
                'device_uuid' => $request->device_uuid,
            ],
            [
                'token_hash' => Hash::make($deviceTokenPlain),
                'device_name' => $request->device_name,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        // OTP 인증 플래그 소모
        $this->otpService->consumeVerification($request->phone);

        // Sanctum 토큰 발급
        $account->tokens()->delete();
        $token = $account->createToken('customer-auth')->plainTextToken;
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
                'device_token' => $deviceTokenPlain,
            ],
            'message' => 'PIN이 설정되었습니다.',
        ]);
    }

    /**
     * Step 4: PIN 로그인 (디바이스 인증 후)
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'pin' => 'required|string|size:6',
            'device_uuid' => 'required|string|max:100',
            'device_token' => 'required|string',
        ]);

        // 고객 조회
        $customer = Customer::where('phone', $request->phone)->first();
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => '등록되지 않은 전화번호입니다.',
            ], 401);
        }

        $account = $customer->account;

        if (!$account || !$account->is_active) {
            return response()->json([
                'success' => false,
                'message' => '비활성화된 계정입니다.',
            ], 401);
        }

        // 디바이스 토큰 검증
        $deviceToken = DeviceToken::where('account_id', $account->account_id)
            ->where('device_uuid', $request->device_uuid)
            ->where('is_active', true)
            ->first();

        if (!$deviceToken || !Hash::check($request->device_token, $deviceToken->token_hash)) {
            return response()->json([
                'success' => false,
                'message' => '등록되지 않은 기기입니다. 휴대폰 인증을 다시 진행해주세요.',
                'require_otp' => true,
            ], 401);
        }

        // PIN 검증
        if (!Hash::check($request->pin, $account->pin_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'PIN이 올바르지 않습니다.',
            ], 401);
        }

        // 디바이스 last_used_at 갱신
        $deviceToken->update(['last_used_at' => now()]);

        // 기존 토큰 삭제 후 새 토큰 발급
        $account->tokens()->delete();
        $token = $account->createToken('customer-auth')->plainTextToken;
        $account->update(['last_login_at' => now()]);
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
     * Step 4-B: PIN 로그인 + 새 기기 등록 (OTP 인증 후 새 기기에서)
     */
    public function loginPinNewDevice(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'pin' => 'required|string|size:6',
            'device_uuid' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:100',
        ]);

        // OTP 인증 완료 여부 확인
        if (!$this->otpService->isVerified($request->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP 인증이 필요합니다.',
            ], 403);
        }

        $customer = Customer::where('phone', $request->phone)->first();
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => '등록되지 않은 전화번호입니다.',
            ], 401);
        }

        $account = $customer->account;

        if (!$account || !$account->is_active) {
            return response()->json([
                'success' => false,
                'message' => '비활성화된 계정입니다.',
            ], 401);
        }

        // PIN 검증
        if (!Hash::check($request->pin, $account->pin_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'PIN이 올바르지 않습니다.',
            ], 401);
        }

        // 새 기기 등록
        $deviceTokenPlain = Str::random(64);
        DeviceToken::updateOrCreate(
            [
                'account_id' => $account->account_id,
                'device_uuid' => $request->device_uuid,
            ],
            [
                'token_hash' => Hash::make($deviceTokenPlain),
                'device_name' => $request->device_name,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        // OTP 인증 플래그 소모
        $this->otpService->consumeVerification($request->phone);

        // 기존 토큰 삭제 후 새 토큰 발급
        $account->tokens()->delete();
        $token = $account->createToken('customer-auth')->plainTextToken;
        $account->update(['last_login_at' => now()]);
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
                'device_token' => $deviceTokenPlain,
            ],
            'message' => '로그인 성공. 기기가 등록되었습니다.',
        ]);
    }

    /**
     * 디바이스 등록 여부 확인 (앱 시작 시 호출)
     */
    public function checkDevice(Request $request): JsonResponse
    {
        $request->validate([
            'device_uuid' => 'required|string|max:100',
            'device_token' => 'required|string',
        ]);

        // device_uuid로 등록된 토큰 조회
        $deviceToken = DeviceToken::where('device_uuid', $request->device_uuid)
            ->where('is_active', true)
            ->first();

        if (!$deviceToken || !Hash::check($request->device_token, $deviceToken->token_hash)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'registered' => false,
                ],
                'message' => '등록되지 않은 기기입니다.',
            ]);
        }

        $account = $deviceToken->account;
        $customer = $account->customer;

        return response()->json([
            'success' => true,
            'data' => [
                'registered' => true,
                'has_pin' => !empty($account->pin_hash),
                'phone' => $customer?->phone,
                'customer_name' => $customer?->name,
            ],
        ]);
    }
}
