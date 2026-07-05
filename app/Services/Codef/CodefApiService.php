<?php

namespace App\Services\Codef;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CodefApiService
{
    protected CodefAuthService $authService;
    protected string $baseUrl;
    protected int $timeout;

    /** 2-Way 추가인증 필요 코드 */
    private const CODE_TWO_WAY = 'CF-03002';
    /** 성공 코드 */
    private const CODE_SUCCESS = 'CF-00000';

    public function __construct(CodefAuthService $authService)
    {
        $this->authService = $authService;
        $this->baseUrl = config('codef.base_url');
        $this->timeout = config('codef.timeout', 300);
    }

    /**
     * CODEF API 호출 (1차 요청)
     *
     * @param string      $endpoint   API 엔드포인트 (예: /v1/kr/insurance/0001/credit4u/contract-info)
     * @param array       $params     요청 파라미터
     * @param string|null $customerId 2-Way 캐시 키에 사용할 고객 ID
     * @param string|null $apiType    2-Way 캐시 키에 사용할 API 유형 (contract/register/change-pwd 등)
     * @return array 정규화된 응답 배열
     */
    public function callApi(string $endpoint, array $params, ?string $customerId = null, ?string $apiType = null): array
    {
        $accessToken = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('CODEF API 호출', [
            'endpoint' => $endpoint,
            'customer_id' => $customerId,
            'api_type' => $apiType,
        ]);

        // 디버그 모드에서 1-Way 파라미터 상세 로깅 (민감 정보 마스킹)
        if (config('app.debug')) {
            $debugParams = $params;
            foreach (['password', 'password1', 'identity', 'userPassword'] as $sensitive) {
                if (isset($debugParams[$sensitive])) {
                    $debugParams[$sensitive] = '[MASKED:' . strlen($debugParams[$sensitive]) . ']';
                }
            }
            Log::debug('CODEF 1-Way 파라미터 상세', [
                'endpoint' => $endpoint,
                'param_keys' => array_keys($params),
                'debug_params' => $debugParams,
            ]);
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->post($url, $params);

            // 401 인증 만료 → 토큰 갱신 후 재시도 (1회)
            if ($response->status() === 401) {
                Log::info('CODEF 토큰 만료, 갱신 후 재시도');
                $accessToken = $this->authService->refreshToken();

                $response = Http::withToken($accessToken)
                    ->timeout($this->timeout)
                    ->post($url, $params);
            }

            if (!$response->successful()) {
                Log::error('CODEF API HTTP 오류', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'code' => 'HTTP_ERROR',
                    'message' => 'CODEF API 호출 실패 (HTTP ' . $response->status() . ')',
                ];
            }

            // CODEF 응답은 URL-encoded JSON 문자열로 올 수 있음
            $body = $response->body();
            $data = $response->json();

            if ($data === null && !empty($body)) {
                $decoded = urldecode($body);
                $data = json_decode($decoded, true);
            }

            if (!is_array($data)) {
                Log::error('CODEF API 응답 파싱 실패', [
                    'endpoint' => $endpoint,
                    'body' => substr($body, 0, 200),
                ]);

                return [
                    'success' => false,
                    'code' => 'PARSE_ERROR',
                    'message' => 'CODEF 응답을 파싱할 수 없습니다.',
                ];
            }

            Log::info('CODEF API 응답 수신', [
                'endpoint' => $endpoint,
                'api_type' => $apiType,
                'result_code' => $data['result']['code'] ?? 'UNKNOWN',
                'result_message' => $data['result']['message'] ?? '',
            ]);

            return $this->handleResponse($data, $customerId, $apiType);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('CODEF API 연결 타임아웃', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => 'TIMEOUT',
                'message' => 'CODEF API 응답 시간이 초과되었습니다.',
            ];
        } catch (\Exception $e) {
            Log::error('CODEF API 호출 중 오류', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => 'ERROR',
                'message' => 'CODEF API 호출 중 오류가 발생했습니다.',
            ];
        }
    }

    /**
     * CODEF API 2-Way 추가인증 호출 (2차 요청)
     *
     * @param string $endpoint       API 엔드포인트
     * @param array  $originalParams 원래 1차 요청 파라미터
     * @param array  $twoWayInput    사용자가 입력한 추가인증 데이터 (secureNo, smsAuthNo 등)
     * @param string $customerId     고객 ID
     * @param string $apiType        API 유형
     * @return array 정규화된 응답 배열
     */
    public function callApi2Way(
        string $endpoint,
        array $originalParams,
        array $twoWayInput,
        string $customerId,
        string $apiType
    ): array {
        $cacheKey = $this->getTwoWayCacheKey($customerId, $apiType);
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다. 처음부터 다시 진행해주세요.',
            ];
        }

        // 캐시에서 twoWayInfo 복원
        $twoWayInfo = $cachedData['twoWayInfo'] ?? [];

        // 보안: 사용자 입력에서 민감/불필요 필드 제거
        unset($twoWayInput['twoWayInfo'], $twoWayInput['is2Way'], $twoWayInput['is_confirm']);

        // CODEF 스펙: N단계 요청은 1~N-1단계의 입력값을 누적 포함해야 함
        // (예: SMS 단계 = 기본 + 보안문자 + SMS)
        $accumulatedInputs = $cachedData['accumulatedInputs'] ?? [];
        $accumulatedInputs = array_merge($accumulatedInputs, $twoWayInput);

        // 2차 요청 파라미터 구성: 원래 파라미터 + 누적 입력 + twoWayInfo + is2Way 플래그
        $params = array_merge($originalParams, $accumulatedInputs);
        $params['is2Way'] = true;
        $params['twoWayInfo'] = $twoWayInfo;

        // 누적 입력을 캐시에 저장 (다음 단계에서 사용)
        $cachedData['accumulatedInputs'] = $accumulatedInputs;
        Cache::put($cacheKey, $cachedData, config('codef.two_way_cache_ttl', 300));

        Log::info('CODEF 2-Way 추가인증 요청', [
            'endpoint' => $endpoint,
            'customer_id' => $customerId,
            'api_type' => $apiType,
        ]);

        // 디버그 모드에서만 파라미터 상세 로깅 (민감 정보 마스킹)
        if (config('app.debug')) {
            $debugParams = $params;
            foreach (['password', 'password1', 'identity'] as $sensitive) {
                if (isset($debugParams[$sensitive])) {
                    $debugParams[$sensitive] = '[MASKED:' . strlen($debugParams[$sensitive]) . ']';
                }
            }
            Log::debug('CODEF 2-Way 파라미터 상세', [
                'param_keys' => array_keys($params),
                'debug_params' => $debugParams,
            ]);
        }

        $accessToken = $this->authService->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withToken($accessToken)
                ->timeout($this->timeout)
                ->post($url, $params);

            if ($response->status() === 401) {
                $accessToken = $this->authService->refreshToken();
                $response = Http::withToken($accessToken)
                    ->timeout($this->timeout)
                    ->post($url, $params);
            }

            if (!$response->successful()) {
                Log::error('CODEF 2-Way HTTP 오류', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'code' => 'HTTP_ERROR',
                    'message' => 'CODEF 추가인증 요청 실패 (HTTP ' . $response->status() . ')',
                ];
            }

            // CODEF 응답은 URL-encoded JSON 문자열로 올 수 있음
            $body = $response->body();
            $data = $response->json();

            if ($data === null && !empty($body)) {
                $decoded = urldecode($body);
                $data = json_decode($decoded, true);
            }

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'code' => 'PARSE_ERROR',
                    'message' => 'CODEF 추가인증 응답을 파싱할 수 없습니다.',
                ];
            }

            Log::info('CODEF 2-Way 응답 수신', [
                'endpoint' => $endpoint,
                'api_type' => $apiType,
                'result_code' => $data['result']['code'] ?? 'UNKNOWN',
                'result_message' => $data['result']['message'] ?? '',
            ]);

            $result = $this->handleResponse($data, $customerId, $apiType);

            // 성공 시 캐시 삭제
            if ($result['success'] === true) {
                Cache::forget($cacheKey);
            }

            return $result;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('CODEF 2-Way 연결 타임아웃', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => 'TIMEOUT',
                'message' => 'CODEF 추가인증 응답 시간이 초과되었습니다.',
            ];
        } catch (\Exception $e) {
            Log::error('CODEF 2-Way 호출 중 오류', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => 'ERROR',
                'message' => 'CODEF 추가인증 중 오류가 발생했습니다.',
            ];
        }
    }

    /**
     * CODEF 응답 처리: 성공 / 2-Way / 에러 분기
     */
    private function handleResponse(array $data, ?string $customerId, ?string $apiType): array
    {
        $resultCode = $data['result']['code'] ?? 'UNKNOWN';
        $resultMessage = $data['result']['message'] ?? '';
        $extraMessage = $data['result']['extraMessage'] ?? '';
        $responseData = $data['data'] ?? [];

        // 성공
        if ($resultCode === self::CODE_SUCCESS) {
            return [
                'success' => true,
                'code' => $resultCode,
                'message' => $resultMessage,
                'data' => $responseData,
            ];
        }

        // 2-Way 추가인증 필요
        if ($resultCode === self::CODE_TWO_WAY) {
            return $this->handleTwoWayResponse($data, $customerId, $apiType);
        }

        // 에러 처리
        Log::warning('CODEF API 에러 응답', [
            'api_type' => $apiType,
            'customer_id' => $customerId,
            'code' => $resultCode,
            'message' => $resultMessage,
            'extra' => $extraMessage,
            'raw_data_keys' => is_array($responseData) ? array_keys($responseData) : 'not_array',
        ]);

        return [
            'success' => false,
            'code' => $resultCode,
            'message' => $this->getErrorMessage($resultCode, $resultMessage),
            'data' => $responseData,
        ];
    }

    /**
     * 2-Way 응답 처리: twoWayInfo 캐시 저장, UI용 extraInfo만 반환
     */
    private function handleTwoWayResponse(array $data, ?string $customerId, ?string $apiType): array
    {
        $responseData = $data['data'] ?? [];
        $continue2Way = $responseData['continue2Way'] ?? false;

        if (!$continue2Way) {
            return [
                'success' => false,
                'code' => self::CODE_TWO_WAY,
                'message' => '추가인증이 필요하지만 진행할 수 없습니다.',
            ];
        }

        // twoWayInfo를 서버 캐시에 저장 (jti, twoWayTimestamp 등 민감 정보)
        $twoWayInfo = [];
        $twoWayFields = ['jti', 'twoWayTimestamp', 'jobIndex', 'threadIndex'];
        foreach ($twoWayFields as $field) {
            if (isset($responseData[$field])) {
                $twoWayInfo[$field] = $responseData[$field];
            }
        }

        if ($customerId && $apiType) {
            $cacheKey = $this->getTwoWayCacheKey($customerId, $apiType);
            // 기존 캐시의 endpoint, originalParams는 유지하고 twoWayInfo만 업데이트
            $existing = Cache::get($cacheKey, []);
            $existing['twoWayInfo'] = $twoWayInfo;
            Cache::put($cacheKey, $existing, config('codef.two_way_cache_ttl', 300));
        }

        // Frontend에 필요한 extraInfo만 추출
        $extraInfo = $this->extractExtraInfo($responseData);

        return [
            'success' => false,
            'code' => self::CODE_TWO_WAY,
            'message' => '추가인증이 필요합니다.',
            'two_way' => true,
            'extraInfo' => $extraInfo,
        ];
    }

    /**
     * CODEF 응답 data에서 UI에 필요한 extraInfo 필드만 추출
     * (jti, twoWayTimestamp 등 민감 정보는 제외)
     */
    private function extractExtraInfo(array $data): array
    {
        $extraInfo = [];

        $uiFields = [
            'reqSecureNo',          // 보안문자 입력 필요 여부
            'reqSecureNoRefresh',   // 보안문자 새로고침 가능 여부
            'secureNoImage',        // 보안문자 이미지 (base64)
            'reqSMSAuthNo',         // SMS 인증번호 입력 필요 여부
            'commSimpleAuth',       // PASS 간편인증 필요 여부
            'reqUserPass1',         // 임시비밀번호 입력 필요 여부 (비밀번호변경)
            'reqUserId',            // 아이디 입력 필요 여부 (회원가입)
            'reqUserPass',          // 비밀번호 입력 필요 여부 (회원가입/비밀번호변경)
            'reqEmail',             // 이메일 입력 필요 여부 (회원가입)
            'reqEmailAuthNo',       // 이메일 인증번호 입력 필요 여부 (회원가입)
        ];

        // CODEF 2-Way 응답: UI 필드는 data.extraInfo 하위 객체에 존재
        $nestedExtraInfo = $data['extraInfo'] ?? [];

        // data 최상위 + data.extraInfo 양쪽에서 검색
        foreach ($uiFields as $field) {
            if (isset($nestedExtraInfo[$field])) {
                $extraInfo[$field] = $nestedExtraInfo[$field];
            } elseif (isset($data[$field])) {
                $extraInfo[$field] = $data[$field];
            }
        }

        // method 필드 (추가인증 방식 정보)
        if (isset($data['method'])) {
            $extraInfo['method'] = $data['method'];
        }

        return $extraInfo;
    }

    /**
     * 2-Way 캐시에 엔드포인트와 원래 파라미터도 함께 저장
     */
    public function storeTwoWayContext(string $customerId, string $apiType, string $endpoint, array $originalParams): void
    {
        $cacheKey = $this->getTwoWayCacheKey($customerId, $apiType);
        $existing = Cache::get($cacheKey, []);

        $existing['endpoint'] = $endpoint;
        $existing['originalParams'] = $originalParams;

        Cache::put($cacheKey, $existing, config('codef.two_way_cache_ttl', 300));
    }

    /**
     * 2-Way 캐시에서 저장된 컨텍스트 조회
     */
    public function getTwoWayContext(string $customerId, string $apiType): ?array
    {
        $cacheKey = $this->getTwoWayCacheKey($customerId, $apiType);

        return Cache::get($cacheKey);
    }

    /**
     * 2-Way 캐시 키 생성
     */
    private function getTwoWayCacheKey(string $customerId, string $apiType): string
    {
        return "credit4u_2way:{$customerId}:{$apiType}";
    }

    /**
     * CODEF 에러 코드별 사용자 메시지
     */
    private function getErrorMessage(string $code, string $defaultMessage): string
    {
        $errorMessages = [
            'CF-00025' => '내보험다보여 미가입 회원입니다. 회원가입을 진행해주세요.',
            'CF-13300' => '아이디를 확인해주세요.',
            'CF-13301' => '비밀번호를 확인해주세요.',
            'CF-13302' => '로그인 5회 실패로 비밀번호 변경이 필요합니다.',
            'CF-13303' => '임시 비밀번호 상태입니다. 비밀번호를 변경해주세요.',
            'CF-13304' => '휴면 계정입니다. 내보험다보여 사이트에서 휴면 해제 후 이용해주세요.',
            'CF-13305' => '탈퇴된 계정입니다.',
            'CF-12801' => '보안문자 입력 횟수를 초과했습니다.',
            'CF-12824' => '아이디를 확인해주세요 (6~12자, 영문+숫자).',
            'CF-12825' => '아이디 형식 오류입니다 (영문+숫자만 입력 가능).',
            'CF-12826' => '비밀번호를 확인해주세요 (9~20자).',
            'CF-12827' => '비밀번호 형식 오류입니다 (영문+숫자+특수문자 필수).',
            'CF-13349' => '이미 등록된 아이디입니다.',
            'CF-13341' => '이미 등록된 이메일입니다.',
            'CF-13342' => '이메일 형식을 확인해주세요.',
            'CF-13343' => '허용되지 않는 이메일 도메인입니다.',
            'CF-13300' => '인증번호 입력 횟수를 초과했습니다.',
            'CF-12803' => '아이디 또는 비밀번호가 일치하지 않습니다.',
            'CF-12001' => '입력 시간이 초과되었습니다 (180초).',
            'CF-12102' => '인증이 취소되었습니다.',
            'CF-12831' => '본인인증 후 이용 가능합니다.',
        ];

        return $errorMessages[$code] ?? $defaultMessage;
    }
}
