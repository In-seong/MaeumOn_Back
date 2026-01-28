<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Popbill API Configuration
    |--------------------------------------------------------------------------
    |
    | 팝빌 API 연동을 위한 설정입니다.
    | https://www.popbill.com 에서 회원가입 후 연동신청하여 발급받은 정보로 변경하세요.
    |
    */

    // 링크아이디: 파트너 신청시 발급받은 LinkID
    'link_id' => env('POPBILL_LINK_ID', 'TESTER'),

    // 비밀키: 파트너 신청시 발급받은 SecretKey
    'secret_key' => env('POPBILL_SECRET_KEY', 'SwWxqU+0TErBXy/9TVjIPEnI0VTUMMSQZtJf3Ed8q3I='),

    // 테스트 환경 여부 (true: 테스트, false: 운영)
    'is_test' => env('POPBILL_IS_TEST', true),

    // 사업자번호 (하이픈 제외)
    'corp_num' => env('POPBILL_CORP_NUM', '1234567890'),

    // 팩스 발신번호 (하이픈 제외)
    'fax_sender_number' => env('POPBILL_FAX_SENDER_NUMBER', '07012345678'),

    // 인증토큰 IP 제한 기능 사용여부 (기본값: true)
    'ip_restrict_on_off' => env('POPBILL_IP_RESTRICT', true),

    // 팝빌 API 서비스 고정 IP 사용여부 (기본값: false)
    'use_static_ip' => env('POPBILL_USE_STATIC_IP', false),

    // 로컬 서버 시간 사용여부 (기본값: true)
    'use_local_time_yn' => env('POPBILL_USE_LOCAL_TIME', true),
];
