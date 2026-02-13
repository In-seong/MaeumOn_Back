<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LG U+ FaxClientNC (WebFax) Configuration
    |--------------------------------------------------------------------------
    |
    | FaxClientNC Java 프로세스가 DB를 폴링하여 팩스를 발송합니다.
    | Laravel은 fc_meta_tran / fc_msg_tran 테이블에 INSERT하고,
    | FaxClientNC가 발송 후 결과를 DB에 업데이트합니다.
    |
    */

    // 릴레이 서버 인증 정보 (fc_meta_tran.tr_id에 사용)
    'relay_id' => env('WEBFAX_RELAY_ID', ''),

    // 발신 팩스 번호 (하이픈 제외)
    'sender_fax_number' => env('WEBFAX_SENDER_FAX_NUMBER', ''),

    // 발신자명
    'sender_name' => env('WEBFAX_SENDER_NAME', '마음온'),

    // 첨부파일 경로 (FaxClientNC의 AttFilePath와 동일해야 함)
    'send_doc_path' => env('WEBFAX_SEND_DOC_PATH', '/opt/faxclient/SendDoc'),

    // 결과 확인 타임아웃 (분) - 이 시간 초과 시 타임아웃 처리
    'result_timeout_minutes' => env('WEBFAX_RESULT_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | FC 테이블 상태 코드
    |--------------------------------------------------------------------------
    |
    | fc_meta_tran.tr_sendstat:
    |   '-' : 초기 상태 (INSERT 중, FaxClientNC가 아직 픽업하면 안됨)
    |   '0' : 발송 대기 (FaxClientNC가 픽업 가능)
    |   '1' : 발송 중 (FaxClientNC가 릴레이 서버로 전송 중)
    |
    | fc_msg_tran.tr_sendstat:
    |   '0' : 대기
    |   '1' : 발송 중
    |   '2' : 발송 완료 (결과 확인 필요 → tr_rsltstat 참조)
    |
    | fc_msg_tran.tr_rsltstat (발송 결과):
    |   '-'   : 미확인
    |   '000' : 성공
    |   기타  : 실패 코드
    |
    */

    // LG U+ 팩스 결과 코드 → 한글 메시지 매핑
    'result_codes' => [
        '000' => '발송 성공',
        '001' => '서버 내부 에러',
        '002' => '수신번호 에러',
        '003' => '수신측 응답없음',
        '004' => '전송 시간 초과',
        '005' => '수신측 수신 거부',
        '006' => '수신측 팩스기 이상',
        '007' => '수신측 통화중',
        '008' => '전송 데이터 에러',
        '009' => '회선 에러',
        '010' => '메모리 부족',
        '099' => '기타 에러',
    ],
];
