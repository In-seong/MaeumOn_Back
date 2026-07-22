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
     * Step 2: OTP 검증 + 기존 회원 자동 로그인
     * - 기존 회원(account 있음): 토큰 발급하여 바로 로그인
     * - 신규/미연결 회원: { is_new: true } → 회원가입 필요
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

        $customer = Customer::where('phone', $request->phone)->first();

        // 기존 회원 (account 연결됨) → 바로 로그인
        if ($customer && $customer->account_id) {
            $account = $customer->account;

            if (!$account || !$account->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => '비활성화된 계정입니다.',
                ], 401);
            }

            $account->tokens()->delete();
            $token = $account->createToken('customer-auth')->plainTextToken;
            $account->update(['last_login_at' => now()]);
            $account->load('customer');

            return response()->json([
                'success' => true,
                'data' => [
                    'is_new' => false,
                    'token' => $token,
                    'account' => $account,
                ],
                'message' => '로그인 성공.',
            ]);
        }

        // 신규 또는 설계사가 등록한 미연결 고객 → 회원가입 필요
        return response()->json([
            'success' => true,
            'data' => [
                'is_new' => true,
                'phone' => $request->phone,
            ],
            'message' => '인증 성공. 회원가입을 진행해주세요.',
        ]);
    }

    /**
     * Step 3: 신규 회원 등록 (OTP 인증 후)
     * - 기존 고객 매칭: 이름(필수) + 전화번호 OR 주민등록번호
     * - 매칭 시 기존 Customer에 account_id 연결
     * - 미매칭 시 새 Customer 생성
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|regex:/^01[0-9]{8,9}$/',
            'name' => 'required|string|max:100',
            'resident_number' => 'required|string|max:20',
            'telecom' => 'required|string|max:20',
            'address' => 'required|string|max:500',
        ]);

        if (!$this->otpService->isVerified($request->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP 인증이 필요합니다.',
            ], 403);
        }

        // 이미 가입된 전화번호 확인 (account 연결된 고객)
        $existing = Customer::where('phone', $request->phone)
            ->whereNotNull('account_id')
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '이미 가입된 전화번호입니다.',
            ], 409);
        }

        // 기존 고객 매칭: 이름 + (전화번호 OR 주민등록번호)
        $matched = $this->findMatchingCustomer(
            $request->name,
            $request->phone,
            $request->resident_number
        );

        $account = Account::create([
            'username' => $request->phone,
            'password_hash' => Hash::make(Str::random(32)),
            'role' => Account::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        if ($matched) {
            // 기존 고객에 account 연결 + 정보 업데이트
            $matched->update([
                'account_id' => $account->account_id,
                'phone' => $request->phone,
                'resident_number' => $request->resident_number,
                'telecom' => $request->telecom,
                'address' => $request->address,
            ]);
        } else {
            // 새 Customer 생성
            $lastCustomer = Customer::where('customer_id', 'like', 'C%')
                ->orderByRaw('CAST(SUBSTRING(customer_id, 2) AS UNSIGNED) DESC')
                ->first();
            $nextNum = $lastCustomer
                ? (int) substr($lastCustomer->customer_id, 1) + 1
                : 1;
            $customerId = 'C' . str_pad((string) $nextNum, 7, '0', STR_PAD_LEFT);

            Customer::create([
                'customer_id' => $customerId,
                'account_id' => $account->account_id,
                'name' => $request->name,
                'phone' => $request->phone,
                'resident_number' => $request->resident_number,
                'telecom' => $request->telecom,
                'address' => $request->address,
                'is_active' => true,
            ]);
        }

        $this->otpService->consumeVerification($request->phone);

        $token = $account->createToken('customer-auth')->plainTextToken;
        $account->load('customer');

        return response()->json([
            'success' => true,
            'data' => [
                'account' => $account,
                'token' => $token,
            ],
            'message' => $matched
                ? '기존 고객 정보와 연결되었습니다.'
                : '회원가입이 완료되었습니다.',
        ], 201);
    }

    /**
     * 기존 고객 매칭: 이름(필수) + 전화번호 OR 주민등록번호
     */
    private function findMatchingCustomer(string $name, string $phone, string $residentNumber): ?Customer
    {
        // 1) 이름 + 전화번호 매칭
        $matched = Customer::where('name', $name)
            ->where('phone', $phone)
            ->whereNull('account_id')
            ->first();
        if ($matched) {
            return $matched;
        }

        // 2) 이름 + 주민등록번호 매칭 (암호화 컬럼이라 복호화 후 비교)
        $cleanInput = preg_replace('/\D/', '', $residentNumber);
        if (!$cleanInput) {
            return null;
        }

        $candidates = Customer::where('name', $name)
            ->whereNull('account_id')
            ->whereNotNull('resident_number')
            ->where('resident_number', '!=', '')
            ->get();

        foreach ($candidates as $candidate) {
            $cleanStored = preg_replace('/\D/', '', $candidate->resident_number ?? '');
            if ($cleanStored && $cleanInput === $cleanStored) {
                return $candidate;
            }
        }

        return null;
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
