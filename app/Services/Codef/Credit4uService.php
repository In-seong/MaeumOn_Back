<?php

namespace App\Services\Codef;

use Illuminate\Support\Facades\Log;

class Credit4uService
{
    protected CodefApiService $apiService;
    protected CodefRsaEncryptor $encryptor;
    protected string $organization;

    /** API 엔드포인트 */
    private const ENDPOINT_CONTRACT_INFO = '/v1/kr/insurance/0001/credit4u/contract-info';
    private const ENDPOINT_REGISTRATION_STATUS = '/v1/kr/insurance/0001/credit4u/registration-status';
    private const ENDPOINT_REGISTER = '/v1/kr/insurance/0001/credit4u/register';
    private const ENDPOINT_FIND_ID = '/v1/kr/insurance/0001/credit4u/find-id';
    private const ENDPOINT_CHANGE_PWD = '/v1/kr/insurance/0001/credit4u/change-pwd';

    /** API 유형 식별자 (2-Way 캐시 키) */
    public const API_TYPE_CONTRACT = 'contract';
    public const API_TYPE_CHECK_REG = 'check-registration';
    public const API_TYPE_REGISTER = 'register';
    public const API_TYPE_FIND_ID = 'find-id';
    public const API_TYPE_CHANGE_PWD = 'change-pwd';

    public function __construct(CodefApiService $apiService, CodefRsaEncryptor $encryptor)
    {
        $this->apiService = $apiService;
        $this->encryptor = $encryptor;
        $this->organization = config('codef.organization', '0001');
    }

    /**
     * 가입여부 확인 (inquiryType=0) 또는 회원정보확인 (inquiryType=4)
     *
     * @param array  $data        [id, password, userName?, identity?, birthDate?, phoneNo?, telecom?]
     * @param string $inquiryType '0' (가입여부) 또는 '4' (회원정보확인)
     */
    public function checkRegistration(array $data, string $inquiryType = '0'): array
    {
        $params = [
            'organization' => $this->organization,
            'id' => $data['id'],
            'password' => $this->encryptor->encrypt($data['password']),
            'inquiryType' => $inquiryType,
        ];

        $result = $this->apiService->callApi(
            self::ENDPOINT_REGISTRATION_STATUS,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_CHECK_REG
        );

        // 2-Way 발생 시 컨텍스트 저장
        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_CHECK_REG,
                self::ENDPOINT_REGISTRATION_STATUS,
                $params
            );
        }

        return $result;
    }

    /**
     * 가입여부 확인 2-Way
     */
    public function checkRegistrationConfirm(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_CHECK_REG);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::ENDPOINT_REGISTRATION_STATUS,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_CHECK_REG
        );
    }

    /**
     * 회원가입 신청
     *
     * @param array  $data [userName, identity, birthDate, phoneNo, telecom, id?, password?, email?]
     * @param string $type '0' (본인인증→바로가입) 또는 '1' (본인인증 후 별도 입력)
     */
    public function register(array $data, string $type = '0'): array
    {
        $params = [
            'organization' => $this->organization,
            'userName' => $data['userName'],
            'identity' => $data['identity'],
            'birthDate' => $data['birthDate'] ?? '',
            'phoneNo' => $data['phoneNo'],
            'telecom' => $data['telecom'],
            'type' => $type,
        ];

        // type=0: 1차 요청에서 가입정보도 함께 전달
        if ($type === '0') {
            if (isset($data['id'])) {
                $params['id'] = $data['id'];
            }
            if (isset($data['password'])) {
                $params['password'] = $this->encryptor->encrypt($data['password']);
            }
            if (isset($data['email'])) {
                $params['email'] = $data['email'];
            }
        }

        $result = $this->apiService->callApi(
            self::ENDPOINT_REGISTER,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_REGISTER
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_REGISTER,
                self::ENDPOINT_REGISTER,
                $params
            );
        }

        return $result;
    }

    /**
     * 회원가입 2-Way 확인
     */
    public function registerConfirm(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_REGISTER);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        // 가입정보 입력 단계에서 비밀번호가 있으면 RSA 암호화
        if (isset($twoWayInput['password'])) {
            $twoWayInput['password'] = $this->encryptor->encrypt($twoWayInput['password']);
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::ENDPOINT_REGISTER,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_REGISTER
        );
    }

    /**
     * 계약정보 조회
     *
     * @param array      $loginData   [id, password]
     * @param array|null $extraParams status=2인 경우 [userName, identity, birthDate, phoneNo, telecom]
     */
    public function getContractInfo(array $loginData, ?array $extraParams = null): array
    {
        $params = [
            'organization' => $this->organization,
            'id' => $loginData['id'],
            'password' => $this->encryptor->encrypt($loginData['password']),
        ];

        // status=2인 경우 본인인증 파라미터 추가
        if ($extraParams) {
            $params = array_merge($params, array_filter([
                'userName' => $extraParams['userName'] ?? null,
                'identity' => $extraParams['identity'] ?? null,
                'birthDate' => $extraParams['birthDate'] ?? null,
                'phoneNo' => $extraParams['phoneNo'] ?? null,
                'telecom' => $extraParams['telecom'] ?? null,
            ]));
        }

        $result = $this->apiService->callApi(
            self::ENDPOINT_CONTRACT_INFO,
            $params,
            $loginData['customer_id'] ?? null,
            self::API_TYPE_CONTRACT
        );

        if ($this->isTwoWay($result) && isset($loginData['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $loginData['customer_id'],
                self::API_TYPE_CONTRACT,
                self::ENDPOINT_CONTRACT_INFO,
                $params
            );
        }

        return $result;
    }

    /**
     * 계약정보 조회 2-Way 확인
     */
    public function getContractInfoConfirm(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_CONTRACT);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::ENDPOINT_CONTRACT_INFO,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_CONTRACT
        );
    }

    /**
     * 아이디 찾기
     *
     * @param array $data [userName, identity, birthDate, phoneNo, telecom]
     */
    public function findId(array $data): array
    {
        $params = [
            'organization' => $this->organization,
            'userName' => $data['userName'],
            'identity' => $data['identity'],
            'birthDate' => $data['birthDate'] ?? '',
            'phoneNo' => $data['phoneNo'],
            'telecom' => $data['telecom'],
        ];

        $result = $this->apiService->callApi(
            self::ENDPOINT_FIND_ID,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_FIND_ID
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_FIND_ID,
                self::ENDPOINT_FIND_ID,
                $params
            );
        }

        return $result;
    }

    /**
     * 아이디 찾기 2-Way 확인
     */
    public function findIdConfirm(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_FIND_ID);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::ENDPOINT_FIND_ID,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_FIND_ID
        );
    }

    /**
     * 비밀번호 변경
     *
     * @param array  $data [id, password?, userName, identity, birthDate, phoneNo, telecom]
     * @param string $type '0' (1차에 password 입력) 또는 '1' (추가인증에서 입력)
     */
    public function changePassword(array $data, string $type = '0'): array
    {
        $params = [
            'organization' => $this->organization,
            'id' => $data['id'],
            'userName' => $data['userName'],
            'identity' => $data['identity'],
            'birthDate' => $data['birthDate'] ?? '',
            'phoneNo' => $data['phoneNo'],
            'telecom' => $data['telecom'],
            'type' => $type,
        ];

        // type=0: 1차 요청에서 변경할 비밀번호 전달
        if ($type === '0' && isset($data['password'])) {
            $params['password'] = $this->encryptor->encrypt($data['password']);
        }

        $result = $this->apiService->callApi(
            self::ENDPOINT_CHANGE_PWD,
            $params,
            $data['customer_id'] ?? null,
            self::API_TYPE_CHANGE_PWD
        );

        if ($this->isTwoWay($result) && isset($data['customer_id'])) {
            $this->apiService->storeTwoWayContext(
                $data['customer_id'],
                self::API_TYPE_CHANGE_PWD,
                self::ENDPOINT_CHANGE_PWD,
                $params
            );
        }

        return $result;
    }

    /**
     * 비밀번호 변경 2-Way 확인
     */
    public function changePasswordConfirm(array $twoWayInput, string $customerId): array
    {
        $context = $this->apiService->getTwoWayContext($customerId, self::API_TYPE_CHANGE_PWD);
        if (!$context) {
            return [
                'success' => false,
                'code' => 'TWO_WAY_EXPIRED',
                'message' => '추가인증 세션이 만료되었습니다.',
            ];
        }

        // 비밀번호 입력이 있으면 RSA 암호화
        if (isset($twoWayInput['password'])) {
            $twoWayInput['password'] = $this->encryptor->encrypt($twoWayInput['password']);
        }
        if (isset($twoWayInput['password1'])) {
            // 임시비밀번호(password1)는 평문 전달 (사용자가 이메일에서 받은 값)
        }

        return $this->apiService->callApi2Way(
            $context['endpoint'] ?? self::ENDPOINT_CHANGE_PWD,
            $context['originalParams'] ?? [],
            $twoWayInput,
            $customerId,
            self::API_TYPE_CHANGE_PWD
        );
    }

    /**
     * 범용 2-Way 확인 (apiType으로 분기)
     */
    public function confirm2Way(string $apiType, array $twoWayInput, string $customerId): array
    {
        return match ($apiType) {
            self::API_TYPE_CHECK_REG => $this->checkRegistrationConfirm($twoWayInput, $customerId),
            self::API_TYPE_REGISTER => $this->registerConfirm($twoWayInput, $customerId),
            self::API_TYPE_CONTRACT => $this->getContractInfoConfirm($twoWayInput, $customerId),
            self::API_TYPE_FIND_ID => $this->findIdConfirm($twoWayInput, $customerId),
            self::API_TYPE_CHANGE_PWD => $this->changePasswordConfirm($twoWayInput, $customerId),
            default => [
                'success' => false,
                'code' => 'INVALID_API_TYPE',
                'message' => '유효하지 않은 API 유형입니다.',
            ],
        };
    }

    /**
     * 2-Way 응답 여부 확인
     */
    private function isTwoWay(array $result): bool
    {
        return ($result['two_way'] ?? false) === true;
    }
}
