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

    // ── 사고/청구 정보 ──
    ['code' => 'ACCIDENT_DATE',  'label' => '사고일자', 'type' => 'date',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'DIAGNOSIS',      'label' => '진단명',   'type' => 'text',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'HOSPITAL_NAME',  'label' => '병원명',   'type' => 'text',   'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'CLAIM_AMOUNT',   'label' => '청구금액', 'type' => 'number', 'category' => '사고/청구 정보', 'customer_field' => null],
    ['code' => 'ACCIDENT_TYPE', 'label' => '사고유형', 'type' => 'checkbox', 'category' => '사고/청구 정보', 'customer_field' => null,
        'default_choices' => [
            ['label' => '질병', 'value' => 'disease'],
            ['label' => '상해', 'value' => 'injury'],
            ['label' => '재물', 'value' => 'property'],
            ['label' => '배상', 'value' => 'liability'],
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

    // ── 서명 ──
    ['code' => 'INSURED_SIGNATURE',        'label' => '피보험자 서명',   'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'BENEFICIARY_SIGNATURE',    'label' => '수익자 서명',     'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'RECIPIENT_SIGNATURE',      'label' => '수령인 서명',     'type' => 'signature', 'category' => '서명', 'customer_field' => null],
    ['code' => 'LEGAL_GUARDIAN_SIGNATURE', 'label' => '법정대리인 서명', 'type' => 'signature', 'category' => '서명', 'customer_field' => null],

    // ── 계좌 정보 ──
    ['code' => 'BANK_NAME',       'label' => '은행명',   'type' => 'text', 'category' => '계좌 정보', 'customer_field' => null],
    ['code' => 'ACCOUNT_NUMBER',  'label' => '계좌번호', 'type' => 'text', 'category' => '계좌 정보', 'customer_field' => null],
    ['code' => 'ACCOUNT_HOLDER',  'label' => '예금주',   'type' => 'text', 'category' => '계좌 정보', 'customer_field' => null],

    // ── 동의 ──
    ['code' => 'CONSENT_UNIQUE_ID', 'label' => '고유식별정보 처리 동의', 'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_SENSITIVE', 'label' => '민감정보 처리 동의',     'type' => 'consent', 'category' => '동의', 'customer_field' => null],
    ['code' => 'CONSENT_CREDIT',    'label' => '개인(신용)정보 처리 동의', 'type' => 'consent', 'category' => '동의', 'customer_field' => null],
];
