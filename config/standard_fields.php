<?php

/**
 * 표준 필드 정의
 *
 * 보험 청구서에 공통적으로 들어가는 항목을 상수로 정의합니다.
 * - code: 양식 간 매칭 키 (form_field.standard_field_code)
 * - label: 관리자/설계사에게 보이는 표시명
 * - type: 필드 타입 (text, date, number, resident_number, phone 등)
 * - category: 분류 (계약자 정보, 피보험자 정보, 수익자 정보, 사고/청구 정보, 계좌 정보)
 * - customer_field: Customer 테이블 컬럼명 (고객 선택 시 자동 채움, null이면 수동 입력)
 */

return [
    // ── 계약자 정보 ──
    ['code' => 'CONTRACTOR_NAME',    'label' => '계약자 성명',     'type' => 'text',            'category' => '계약자 정보', 'customer_field' => 'name'],
    ['code' => 'CONTRACTOR_RRN_FRONT', 'label' => '계약자 생년월일',       'type' => 'resident_number_front', 'category' => '계약자 정보', 'customer_field' => 'resident_number_front'],
    ['code' => 'CONTRACTOR_RRN_BACK',  'label' => '계약자 주민번호 뒷자리', 'type' => 'resident_number_back',  'category' => '계약자 정보', 'customer_field' => 'resident_number_back'],
    ['code' => 'CONTRACTOR_PHONE',   'label' => '계약자 전화번호',  'type' => 'phone',           'category' => '계약자 정보', 'customer_field' => 'phone'],
    ['code' => 'CONTRACTOR_ADDRESS', 'label' => '계약자 주소',     'type' => 'text',            'category' => '계약자 정보', 'customer_field' => 'address'],
    ['code' => 'CONTRACTOR_EMAIL',   'label' => '계약자 이메일',   'type' => 'text',            'category' => '계약자 정보', 'customer_field' => 'email'],

    // ── 피보험자 정보 ──
    ['code' => 'INSURED_NAME',       'label' => '피보험자 성명',    'type' => 'text',            'category' => '피보험자 정보', 'customer_field' => null],
    ['code' => 'INSURED_RRN_FRONT',   'label' => '피보험자 생년월일',       'type' => 'resident_number_front', 'category' => '피보험자 정보', 'customer_field' => null],
    ['code' => 'INSURED_RRN_BACK',    'label' => '피보험자 주민번호 뒷자리', 'type' => 'resident_number_back',  'category' => '피보험자 정보', 'customer_field' => null],
    ['code' => 'INSURED_PHONE',      'label' => '피보험자 전화번호', 'type' => 'phone',           'category' => '피보험자 정보', 'customer_field' => null],
    ['code' => 'INSURED_ADDRESS',    'label' => '피보험자 주소',    'type' => 'text',            'category' => '피보험자 정보', 'customer_field' => null],

    // ── 수익자 정보 ──
    ['code' => 'BENEFICIARY_NAME',     'label' => '수익자 성명',     'type' => 'text',            'category' => '수익자 정보', 'customer_field' => null],
    ['code' => 'BENEFICIARY_RRN_FRONT', 'label' => '수익자 생년월일',       'type' => 'resident_number_front', 'category' => '수익자 정보', 'customer_field' => null],
    ['code' => 'BENEFICIARY_RRN_BACK',  'label' => '수익자 주민번호 뒷자리', 'type' => 'resident_number_back',  'category' => '수익자 정보', 'customer_field' => null],
    ['code' => 'BENEFICIARY_PHONE',    'label' => '수익자 전화번호',  'type' => 'phone',           'category' => '수익자 정보', 'customer_field' => null],
    ['code' => 'BENEFICIARY_RELATION', 'label' => '수익자 관계',     'type' => 'text',            'category' => '수익자 정보', 'customer_field' => null],
    ['code' => 'BENEFICIARY_REAL_OWNER', 'label' => '실제소유자 여부', 'type' => 'checkbox', 'category' => '수익자 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '예', 'value' => 'Y'],
            ['label' => '아니오', 'value' => 'N'],
        ],
    ],

    // ── 청구자 정보 ──
    ['code' => 'CLAIMANT_NAME',      'label' => '청구자 성명',           'type' => 'text',                  'category' => '청구자 정보', 'customer_field' => null],
    ['code' => 'CLAIMANT_RRN_FRONT', 'label' => '청구자 생년월일',       'type' => 'resident_number_front', 'category' => '청구자 정보', 'customer_field' => null],
    ['code' => 'CLAIMANT_RRN_BACK',  'label' => '청구자 주민번호 뒷자리', 'type' => 'resident_number_back',  'category' => '청구자 정보', 'customer_field' => null],
    ['code' => 'CLAIMANT_PHONE',     'label' => '청구자 전화번호',        'type' => 'phone',                 'category' => '청구자 정보', 'customer_field' => null],
    ['code' => 'CLAIMANT_ADDRESS',   'label' => '청구자 주소',           'type' => 'text',                  'category' => '청구자 정보', 'customer_field' => null],
    ['code' => 'CLAIMANT_SIGNATURE', 'label' => '청구자 서명',           'type' => 'signature',             'category' => '청구자 정보', 'customer_field' => null],

    // ── 사고/청구 정보 ──
    ['code' => 'ACCIDENT_DATE',  'label' => '사고일자', 'type' => 'date',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'DIAGNOSIS',      'label' => '진단명',   'type' => 'text',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'HOSPITAL_NAME',  'label' => '병원명',   'type' => 'text',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'CLAIM_AMOUNT',   'label' => '청구금액', 'type' => 'number', 'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'CLAIM_DATE',    'label' => '청구일자', 'type' => 'date',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'ACCIDENT_TYPE', 'label' => '사고유형', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '질병', 'value' => 'disease'],
            ['label' => '상해', 'value' => 'injury'],
            ['label' => '교통상해', 'value' => 'traffic_injury'],
            ['label' => '자살', 'value' => 'suicide'],
            ['label' => '재물', 'value' => 'property'],
            ['label' => '배상', 'value' => 'liability'],
            ['label' => '기타', 'value' => 'other'],
        ],
    ],
    ['code' => 'ACCIDENT_DETAIL_TYPE', 'label' => '세부유형', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '입원', 'value' => 'hospitalization'],
            ['label' => '통원', 'value' => 'outpatient'],
            ['label' => '수술', 'value' => 'surgery'],
            ['label' => '진단', 'value' => 'diagnosis'],
            ['label' => '사망', 'value' => 'death'],
            ['label' => '장해', 'value' => 'disability'],
            ['label' => '운전자', 'value' => 'driver'],
            ['label' => '치과 치료', 'value' => 'dental'],
            ['label' => '골절', 'value' => 'fracture'],
            ['label' => '치료', 'value' => 'treatment'],
            ['label' => '기타', 'value' => 'other'],
        ],
    ],
    ['code' => 'REPRESENTATIVE_EVENT', 'label' => '대표행사', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '사망', 'value' => 'death'],
            ['label' => '장해', 'value' => 'disability'],
            ['label' => '진단', 'value' => 'diagnosis'],
            ['label' => '입원', 'value' => 'hospitalization'],
            ['label' => '통원', 'value' => 'outpatient'],
            ['label' => '수술', 'value' => 'surgery'],
            ['label' => '실손의료비', 'value' => 'actual_expense'],
            ['label' => '기타', 'value' => 'other'],
        ],
    ],
    ['code' => 'ACCIDENT_DESCRIPTION', 'label' => '사고경위', 'type' => 'textarea', 'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'INSURED_RELATION', 'label' => '피보험자와의관계', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '본인', 'value' => 'self'],
            ['label' => '배우자', 'value' => 'spouse'],
            ['label' => '부모', 'value' => 'parent'],
            ['label' => '자녀', 'value' => 'child'],
            ['label' => '형제/자매', 'value' => 'sibling'],
            ['label' => '기타', 'value' => 'other'],
        ],
    ],
    ['code' => 'COMPENSATION_RECIPIENT', 'label' => '보상안내받으실분', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '계약자', 'value' => 'contractor'],
            ['label' => '피보험자', 'value' => 'insured'],
            ['label' => '기타', 'value' => 'other'],
        ],
    ],

    ['code' => 'AUTO_TRANSFER', 'label' => '자동이체계좌 지급 여부', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '예', 'value' => 'Y'],
            ['label' => '아니오', 'value' => 'N'],
        ],
    ],

    // ── 서명 ──
    ['code' => 'SELF_NAME',      'label' => '본인 성명',  'type' => 'text',      'category' => '서명', 'customer_field' => null],
    ['code' => 'SELF_SIGNATURE', 'label' => '본인 서명',  'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'LEGAL_GUARDIAN_NAME',      'label' => '법정대리인 성명', 'type' => 'text',      'category' => '서명', 'customer_field' => null],
    ['code' => 'INSURED_SIGNATURE',        'label' => '피보험자 서명',   'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'BENEFICIARY_SIGNATURE',    'label' => '수익자 서명',     'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'RECIPIENT_SIGNATURE',      'label' => '수령인 서명',     'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'LEGAL_GUARDIAN_SIGNATURE', 'label' => '법정대리인 서명', 'type' => 'signature', 'category' => '서명', 'customer_field' => null],

    // ── 계좌 정보 ──
    ['code' => 'BANK_NAME',       'label' => '은행명',   'type' => 'select', 'category' => '계좌 정보', 'customer_field' => null,
        'default_choices' => [
            // 은행
            ['label' => '산업은행', 'value' => '산업은행'],
            ['label' => '기업은행', 'value' => '기업은행'],
            ['label' => '국민은행', 'value' => '국민은행'],
            ['label' => '수협중앙회', 'value' => '수협중앙회'],
            ['label' => '농협은행', 'value' => '농협은행'],
            ['label' => '우리은행', 'value' => '우리은행'],
            ['label' => 'SC제일은행', 'value' => 'SC제일은행'],
            ['label' => '한국씨티은행', 'value' => '한국씨티은행'],
            ['label' => '대구은행', 'value' => '대구은행'],
            ['label' => '부산은행', 'value' => '부산은행'],
            ['label' => '광주은행', 'value' => '광주은행'],
            ['label' => '제주은행', 'value' => '제주은행'],
            ['label' => '전북은행', 'value' => '전북은행'],
            ['label' => '경남은행', 'value' => '경남은행'],
            ['label' => '새마을금고', 'value' => '새마을금고'],
            ['label' => '신협중앙회', 'value' => '신협중앙회'],
            ['label' => '상호저축은행', 'value' => '상호저축은행'],
            ['label' => '산림조합중앙회', 'value' => '산림조합중앙회'],
            ['label' => '우체국', 'value' => '우체국'],
            ['label' => '하나은행', 'value' => '하나은행'],
            ['label' => '신한은행', 'value' => '신한은행'],
            ['label' => '케이뱅크', 'value' => '케이뱅크'],
            ['label' => '카카오뱅크', 'value' => '카카오뱅크'],
            ['label' => '토스뱅크', 'value' => '토스뱅크'],
            // 기관
            ['label' => '신용보증기금', 'value' => '신용보증기금'],
            ['label' => '기술신용보증기금', 'value' => '기술신용보증기금'],
            ['label' => '한국주택금융공사', 'value' => '한국주택금융공사'],
            ['label' => '서울보증보험', 'value' => '서울보증보험'],
            ['label' => '한국전자금융', 'value' => '한국전자금융'],
            ['label' => '금융결제원', 'value' => '금융결제원'],
            ['label' => 'SBI저축은행', 'value' => 'SBI저축은행'],
            // 증권
            ['label' => '유안타증권', 'value' => '유안타증권'],
            ['label' => 'KB증권', 'value' => 'KB증권'],
            ['label' => 'IBK투자증권', 'value' => 'IBK투자증권'],
            ['label' => '미래에셋증권', 'value' => '미래에셋증권'],
            ['label' => '대우증권', 'value' => '대우증권'],
            ['label' => '삼성증권', 'value' => '삼성증권'],
            ['label' => '한국투자증권', 'value' => '한국투자증권'],
            ['label' => '교보증권', 'value' => '교보증권'],
            ['label' => '하이투자증권', 'value' => '하이투자증권'],
            ['label' => '키움증권', 'value' => '키움증권'],
            ['label' => '이베스트투자증권', 'value' => '이베스트투자증권'],
            ['label' => 'SK증권', 'value' => 'SK증권'],
            ['label' => '대신증권', 'value' => '대신증권'],
            ['label' => '메리츠종금증권', 'value' => '메리츠종금증권'],
            ['label' => '한화투자증권', 'value' => '한화투자증권'],
            ['label' => '하나금융투자증권', 'value' => '하나금융투자증권'],
            ['label' => '토스증권', 'value' => '토스증권'],
            ['label' => '신한금융투자', 'value' => '신한금융투자'],
            ['label' => '동부증권', 'value' => '동부증권'],
            ['label' => '유진투자증권', 'value' => '유진투자증권'],
            ['label' => '메리츠증권', 'value' => '메리츠증권'],
            ['label' => '카카오페이증권', 'value' => '카카오페이증권'],
            ['label' => 'NH투자증권', 'value' => 'NH투자증권'],
            ['label' => '부국증권', 'value' => '부국증권'],
            ['label' => '신영증권', 'value' => '신영증권'],
            ['label' => '케이프투자증권', 'value' => '케이프투자증권'],
            ['label' => '한국포스증권', 'value' => '한국포스증권'],
            ['label' => '기타', 'value' => '기타'],
        ],
    ],
    ['code' => 'ACCOUNT_NUMBER',  'label' => '계좌번호', 'type' => 'text', 'category' => '계좌 정보', 'customer_field' => null],
    ['code' => 'ACCOUNT_HOLDER',  'label' => '예금주',   'type' => 'text', 'category' => '계좌 정보', 'customer_field' => null],
    ['code' => 'ACCOUNT_HOLDER_RRN', 'label' => '예금주 주민번호', 'type' => 'resident_number', 'category' => '계좌 정보', 'customer_field' => null],

    // ── 동의 ──
    ['code' => 'CONSENT_UNIQUE_ID', 'label' => '고유식별정보 처리 동의', 'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_SENSITIVE', 'label' => '민감정보 처리 동의',     'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_CREDIT',    'label' => '개인(신용)정보 처리 동의', 'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_AUTO',      'label' => '자동동의',               'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_THIRD_PARTY', 'label' => '제3자 제공 동의', 'type' => 'checkbox', 'category' => '동의', 'customer_field' => null,
        'default_choices' => [
            ['label' => '동의', 'value' => 'agree'],
            ['label' => '동의안함', 'value' => 'disagree'],
        ],
    ],
    ['code' => 'CONSENT_PAYMENT_PROCEDURE', 'label' => '보험금 지급절차 동의', 'type' => 'checkbox', 'category' => '동의', 'customer_field' => null,
        'default_choices' => [
            ['label' => '확인', 'value' => 'confirmed'],
        ],
    ],
    ['code' => 'CONSENT_REQUIRED_CHECK', 'label' => '필수확인사항', 'type' => 'checkbox', 'category' => '동의', 'customer_field' => null,
        'default_choices' => [
            ['label' => '확인', 'value' => 'confirmed'],
        ],
    ],
    ['code' => 'CONSENT_CLAIM_ASSIGNMENT', 'label' => '채권양도 동의', 'type' => 'checkbox', 'category' => '동의', 'customer_field' => null,
        'default_choices' => [
            ['label' => '확인', 'value' => 'confirmed'],
        ],
    ],
];
