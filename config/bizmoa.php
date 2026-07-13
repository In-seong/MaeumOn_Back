<?php

return [
    'uid' => env('BIZMOA_UID', ''),
    'pwd' => env('BIZMOA_PWD', ''),
    'from_number' => env('BIZMOA_FROM_NUMBER', ''),

    'fax_url' => env('BIZMOA_FAX_URL', 'https://biz.moashot.com/EXT/URLASP/faxsendUTF.asp'),

    // FTP 설정 (PDF 파일 전송용)
    'ftp_host' => env('BIZMOA_FTP_HOST', ''),
    'ftp_port' => env('BIZMOA_FTP_PORT', 21),
    'ftp_user' => env('BIZMOA_FTP_USER', ''),
    'ftp_pass' => env('BIZMOA_FTP_PASS', ''),

    // 보안 설정 (commType=2 사용 시)
    'sec_code' => env('BIZMOA_SEC_CODE', ''),

    // 결과 콜백 URL
    'return_url' => env('BIZMOA_RETURN_URL', ''),

    // 결과 확인 타임아웃 (분)
    'result_timeout_minutes' => env('BIZMOA_RESULT_TIMEOUT', 30),

    // 팩스 전송 결과 코드
    'fax_result_codes' => [
        '1' => '전송 성공',
        '2' => '전송 중 실패 (연결 끊김 또는 일반 전화)',
        '3' => '응답 없음',
        '4' => '통화 중',
        '5' => '번호 오류',
        '6' => '기타 오류',
        '20' => '사용불가 아이디',
        '21' => '패스워드 오류',
        '22' => '존재하지 않는 아이디',
        '23' => '치환 오류',
        '24' => '발송번호 정보 오류',
        '25' => '주소 정보 생성 오류',
        '31' => '전송 취소',
        '32' => '오류 번호 (발송 전)',
        '33' => '전송 차단',
        '34' => '번호 중복',
        '41' => '아이디 param 오류',
        '42' => '패스워드 param 오류',
        '43' => '팩스 번호 param 오류',
        '44' => '팩스 데이터 param 오류',
        '45' => '발신 번호 미인증',
        '46' => '잔액 부족',
        '51' => '기타 오류',
        '52' => '원본 문서 파일 오류',
        '53' => '원본 문서 파일 실행 오류',
        '55' => '문서 변환 오류',
        '56' => '문서 병합 오류',
        '57' => '커버 처리 오류',
        '70' => '지원되지 않는 서비스',
    ],
];
