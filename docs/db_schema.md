# MaeumOn DB 스키마 (운영 기준)

> **최종 업데이트**: 2026-05-31
> **DB**: MySQL (MariaDB)
> **총 테이블**: 59개 (비즈니스 51개 + Laravel 시스템 8개)

---

## 목차

1. [인증/사용자](#1-인증사용자) — account, admin, agent, customer, device_token, fcm_token
2. [보험/계약](#2-보험계약) — insurance_company, insurance, contract
3. [보험청구](#3-보험청구) — insurance_claim, claim_form, form_page, form_field, claim_field_value, supporting_document, claim_document
4. [건강/의료](#4-건강의료) — medical_record, health_checkup, health_external_account, health_prediction, disclosure_obligation
5. [고객 관리](#5-고객-관리) — customer_status, customer_assignment, memo, consultation, complaint
6. [커뮤니케이션](#6-커뮤니케이션) — message, notification, notice, satisfaction_survey
7. [실적/통계](#7-실적통계) — performance
8. [병원 혜택](#8-병원-혜택) — partner_hospital, hospital_benefit, benefit_usage
9. [공통](#9-공통) — common_code, consent_template
10. [CODEF 내보험다보여 연동](#10-codef-신용정보원-내보험다보여-연동) — credit4u_account, insurance_coverage, insurance_payment_history, insurance_statistics
11. [캘린더/일정](#11-캘린더일정) — agent_calendar_event, agent_reminder
14. [간편 청구/예약](#14-간편-청구예약) — claim_request, claim_request_file, health_center, hospital_reservation, hospital_account
12. [팩스](#12-팩스-faxclientnc) — FC_META_TRAN, FC_MSG_TRAN, FC_RECV_TRAN
13. [Laravel 시스템](#13-laravel-시스템-테이블) — sessions, cache, jobs 등

---

## 공통 사항

- **PK 네이밍**: `{테이블명}_id` (예: `customer_id`, `claim_id`)
- **타임스탬프**: 모든 비즈니스 테이블에 `created_at`, `updated_at` 존재
- **Soft Delete**: `is_active` 컬럼 사용 (실제 DELETE 하지 않음)
- **CHAR(8) ID**: customer(`C`), agent(`A`), admin(`D`) + 7자리 순번 (예: `C0000001`)
- **FK 컬럼명**: `{참조테이블}_id`

---

## 1. 인증/사용자

### account

통합 계정 테이블. 모든 사용자(고객/설계사/관리자)의 로그인 정보.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| account_id | int(11) | NO | PRI | auto_increment | |
| username | varchar(50) | NO | UNI | | 고객: 전화번호, Agent/Admin: 이메일 |
| password_hash | varchar(255) | NO | | | |
| pin_hash | varchar(255) | YES | | | 고객 PIN 인증용 |
| role | enum('CUSTOMER','AGENT','ADMIN') | NO | MUL | | |
| is_active | tinyint(1) | NO | MUL | 1 | |
| last_login_at | datetime | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### admin

관리자 정보. **Model: `Admin`** (`app/Models/Admin.php`, 2026-02-26 구현).
- 관계: `account()` → BelongsTo(Account), `notices()` → HasMany(Notice), `customerAssignments()` → HasMany(CustomerAssignment)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| admin_id | char(8) | NO | PRI | | D + 7자리 순번 |
| account_id | int(11) | NO | MUL | | → account FK |
| name | varchar(50) | NO | MUL | | |
| phone | varchar(20) | YES | | | |
| email | varchar(100) | YES | | | |
| department | varchar(100) | YES | | | 소속 부서 |
| position | varchar(50) | YES | | | 직급 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### agent

설계사 정보.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| agent_id | char(8) | NO | PRI | | A + 7자리 순번 |
| account_id | int(11) | NO | MUL | | → account FK |
| name | varchar(50) | NO | MUL | | |
| employee_number | varchar(20) | YES | MUL | | 사번 |
| phone | varchar(20) | YES | | | |
| email | varchar(100) | YES | | | |
| office_location | varchar(100) | YES | | | 소속 지점 |
| specialization | varchar(200) | YES | | | 전문분야 |
| hire_date | date | YES | | | 입사일 |
| resignation_date | date | YES | | | 퇴사일 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### customer

고객 정보.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| customer_id | char(8) | NO | PRI | | C + 7자리 순번 |
| account_id | int(11) | YES | MUL | | → account FK (고객앱 미가입 시 NULL) |
| agent_id | char(8) | YES | MUL | | → agent FK (담당 설계사) |
| name | varchar(50) | NO | MUL | | |
| resident_number | text | YES | | | 주민등록번호 (AES-256 암호화 저장) |
| gender | enum('M','F','OTHER') | YES | | | |
| birth_date | date | YES | | | |
| phone | varchar(20) | YES | MUL | | |
| email | varchar(100) | YES | | | |
| address | varchar(255) | YES | | | |
| detailed_address | varchar(255) | YES | | | 상세 주소 |
| job | varchar(100) | YES | | | 직업 |
| telecom | varchar(20) | YES | | | 통신사 |
| acquisition_channel | varchar(50) | YES | | | 고객정보취득경로(병원 등) |
| last_contact_date | date | YES | | | 최근 연락일 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### device_token

고객 기기 인증 토큰.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| device_token_id | bigint(20) unsigned | NO | PRI | auto_increment | |
| account_id | int(11) | NO | MUL | | → account FK |
| device_uuid | varchar(100) | NO | | | 프론트 localStorage UUID |
| token_hash | varchar(255) | NO | | | 해시된 토큰 |
| device_name | varchar(100) | YES | | | |
| is_active | tinyint(4) | NO | | 1 | |
| last_used_at | timestamp | YES | | | |
| created_at | timestamp | YES | | | |
| updated_at | timestamp | YES | | | |

### fcm_token

FCM 푸시 알림 토큰. **Model: `FcmToken`** (`app/Models/FcmToken.php`, 2026-03-07 구현).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| id | bigint(20) unsigned | NO | PRI | auto_increment | |
| user_type | varchar(20) | NO | MUL | | AGENT/CUSTOMER/ADMIN |
| user_id | char(8) | NO | MUL | | 사용자 ID |
| fcm_token | varchar(500) | NO | | | Firebase Cloud Messaging 토큰 |
| device_info | varchar(500) | YES | | | 기기 정보 |
| created_at | timestamp | YES | | | |
| updated_at | timestamp | YES | | | |

- UNIQUE KEY: `(user_type, user_id, fcm_token)`

---

## 2. 보험/계약

### insurance_company

보험사 정보.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| company_id | int(11) | NO | PRI | auto_increment | |
| company_name | varchar(100) | NO | MUL | | |
| company_code | varchar(20) | YES | MUL | | 금감원 코드 |
| business_number | varchar(20) | YES | | | 사업자등록번호 |
| representative_name | varchar(50) | YES | | | 대표자명 |
| address | varchar(255) | YES | | | |
| contact_phone | varchar(20) | YES | | | |
| fax_number | varchar(20) | YES | | | 팩스 발송 번호 |
| logo_path | varchar(255) | YES | | | S3 경로 |
| website_url | varchar(255) | YES | | | |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### insurance

고객 가입보험 정보. CODEF 신용정보원 내보험다보여 연동 데이터 저장. **Model: `Insurance`** (2026-04-07 구현).
- 관계: customer(belongsTo), insuranceCompany(belongsTo, alias of company), coverages(hasMany → InsuranceCoverage), paymentHistories(hasMany → InsurancePaymentHistory)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| insurance_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| company_id | int(11) | NO | MUL | | → insurance_company FK |
| policy_number | varchar(50) | NO | MUL | | 증권번호 |
| insurance_type | varchar(50) | YES | | | 보험 유형 |
| product_name | varchar(200) | YES | | | 상품명 |
| coverage_amount | decimal(15,2) | YES | | | 보장 금액 |
| premium_amount | decimal(12,2) | YES | | | 보험료 |
| payment_period | varchar(50) | YES | | | 납입 주기 |
| subscription_date | date | YES | MUL | | 가입일 |
| expiration_date | date | YES | MUL | | 만기일 |
| contract_type | varchar(20) | YES | | | savings/car/property/actual_loss/flat_rate |
| contractor_name | varchar(50) | YES | | | 계약자명 (resContractor) |
| contract_status | varchar(20) | YES | | | 계약상태 (resContractStatus, '정상'/'만기'/'해지' 등) |
| company_phone | varchar(20) | YES | | | 보험사 전화번호 (resPhoneNo) |
| company_homepage | varchar(255) | YES | | | 보험사 홈페이지 (resHomePage) |
| payment_cycle | varchar(20) | YES | | | 납입주기 (resPaymentCycle) |
| start_date | date | YES | | | 보장개시일 (commStartDate) |
| end_date | date | YES | | | 보장종료일 (commEndDate) |
| insured_person | varchar(50) | YES | | | 피보험자 (resInsuredPerson) |
| res_number | varchar(20) | YES | | | 호수/순번 (resNumber) |
| contract_date_of | date | YES | | | 계약일 (resDateOfContract, 화재특종용) |
| car_name | varchar(100) | YES | | | 자동차명 (commCarName) |
| car_number | varchar(20) | YES | | | 차량번호 (resCarNo) |
| codef_synced | tinyint(1) | NO | | 0 | 0=수동입력, 1=CODEF동기화 |
| codef_raw_data | longtext | YES | | | CODEF 원본 응답 (JSON, 디버깅용) |
| synced_at | datetime | YES | | | CODEF 동기화 시각 |
| is_active | tinyint(1) | NO | | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### contract

보험 계약 (설계사 관리용).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| contract_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | YES | MUL | | → customer FK |
| agent_id | char(8) | NO | MUL | | → agent FK |
| company_id | int(11) | NO | MUL | | → insurance_company FK |
| contract_number | varchar(50) | YES | MUL | | 계약번호 |
| contract_amount | decimal(12,2) | NO | | | 계약 금액 |
| contract_date | date | NO | MUL | | 계약일 |
| contract_status | varchar(20) | NO | MUL | active | active/expired/cancelled |
| customer_name | varchar(50) | YES | | | 비정규화 (조회 성능) |
| customer_phone | varchar(20) | YES | | | 비정규화 |
| insurance_product | varchar(200) | YES | | | 보험상품명 |
| expiration_date | date | YES | | | 만기일 |
| payment_method | varchar(20) | YES | | | 납입 방식 |
| notes | text | YES | | | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 3. 보험청구

### batch_claim

다중 보험 청구 묶음. 여러 보험사에 동시 청구 시 N건의 insurance_claim을 하나로 그룹핑.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| batch_claim_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | YES | MUL | | → customer FK (임시저장 시 NULL 가능) |
| agent_id | char(8) | YES | MUL | | → agent FK |
| batch_status | varchar(20) | NO | MUL | pending | draft/pending/processing/completed/partial_failed |
| total_count | int(11) | NO | | 0 | 총 청구 건수 |
| completed_count | int(11) | NO | | 0 | 완료 건수 |
| notes | text | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

**Model**: `App\Models\BatchClaim` (구현 완료)
**관계**: customer(belongsTo), agent(belongsTo), claims(hasMany → InsuranceClaim)

### insurance_claim

보험금 청구.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| claim_id | int(11) | NO | PRI | auto_increment | |
| batch_claim_id | int(11) | YES | MUL | | → batch_claim FK (NULL이면 단건 청구) |
| customer_id | char(8) | YES | MUL | | → customer FK (임시저장 시 NULL 가능) |
| insurance_id | int(11) | YES | MUL | | → insurance FK (직접청구 시 NULL) |
| company_id | int(11) | NO | MUL | | → insurance_company FK |
| claim_form_id | int(11) | YES | MUL | | → claim_form FK |
| agent_id | char(8) | YES | MUL | | → agent FK |
| claim_number | varchar(50) | YES | MUL | | 청구번호 |
| claim_type | varchar(50) | NO | | | '직접청구' 등 |
| accident_date | date | YES | | | 사고일 |
| claim_amount | decimal(12,2) | YES | | | 청구 금액 |
| approved_amount | decimal(12,2) | YES | | | 승인 금액 |
| claim_status | varchar(20) | NO | MUL | pending | draft/pending/processing/approved/rejected/paid |
| claim_date | date | NO | MUL | | 청구일 |
| approval_date | date | YES | | | 승인일 |
| paid_date | date | YES | | | 실제 지급일 |
| paid_amount | decimal(12,2) | YES | | | 실제 지급 금액 |
| rejection_reason | text | YES | | | 거절 사유 |
| generated_pdf_path | varchar(255) | YES | | | S3 PDF 경로 |
| fax_sent_at | datetime | YES | | | 팩스 발송 시각 |
| fax_status | enum('pending','sending','sent','failed') | YES | | | |
| fax_batch_id | bigint(20) | YES | | | FC_META_TRAN 참조 |
| fax_number_sent | varchar(20) | YES | | | 발송 팩스번호 |
| fax_result_code | varchar(20) | YES | | | 팩스 결과 코드 |
| notes | text | YES | | | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### claim_form

청구서 양식 템플릿.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| claim_form_id | int(11) | NO | PRI | auto_increment | |
| company_id | int(11) | NO | MUL | | → insurance_company FK |
| form_name | varchar(200) | NO | | | 양식명 |
| form_description | text | YES | | | |
| form_version | varchar(20) | YES | | | 버전 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### form_page

청구서 양식 페이지 (이미지 기반).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| form_page_id | int(11) | NO | PRI | auto_increment | |
| claim_form_id | int(11) | NO | MUL | | → claim_form FK |
| page_number | int(11) | NO | MUL | | 페이지 순서 |
| page_title | varchar(200) | YES | | | |
| page_description | text | YES | | | |
| page_image_path | varchar(255) | YES | | | S3 템플릿 이미지 경로 |
| image_width | int(11) | YES | | | 이미지 폭 (px) |
| image_height | int(11) | YES | | | 이미지 높이 (px) |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### form_field

청구서 양식 필드 (위치/스타일 포함).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| form_field_id | int(11) | NO | PRI | auto_increment | |
| claim_form_id | int(11) | NO | MUL | | → claim_form FK |
| form_page_id | int(11) | YES | MUL | | → form_page FK |
| field_name | varchar(100) | NO | | | 필드 식별명 |
| standard_field_code | varchar(50) | YES | | NULL | 표준 필드 코드 (CONTRACTOR_NAME 등). NULL이면 커스텀 필드 |
| field_label | varchar(200) | NO | | | 표시 라벨 |
| field_type | varchar(50) | NO | | | text/date/number/resident_number/phone/textarea/checkbox/radio/consent/signature |
| field_order | int(11) | NO | MUL | | 정렬 순서 |
| is_required | tinyint(1) | NO | | 0 | |
| field_options | text | YES | | | select 옵션 (JSON) |
| validation_rules | text | YES | | | 유효성 규칙 (JSON) |
| x_position | int(11) | NO | | 0 | 이미지 위 X 좌표 |
| y_position | int(11) | NO | | 0 | 이미지 위 Y 좌표 |
| width | int(11) | NO | | 200 | 필드 폭 (px) |
| height | int(11) | NO | | 30 | 필드 높이 (px) |
| font_size | int(11) | NO | | 14 | 글꼴 크기 |
| font_color | varchar(7) | NO | | #000000 | 글꼴 색상 |
| placeholder | varchar(255) | YES | | | |
| default_value | varchar(255) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### claim_field_value

청구서 제출 시 필드별 입력 값.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| claim_field_value_id | int(11) | NO | PRI | auto_increment | |
| claim_id | int(11) | NO | MUL | | → insurance_claim FK |
| form_field_id | int(11) | NO | MUL | | → form_field FK |
| field_value | text | YES | | | 입력된 값 |
| created_at | timestamp | YES | | | |
| updated_at | timestamp | YES | | | |

### supporting_document

보험사별 필요 증빙서류 유형. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| supporting_document_id | int(11) | NO | PRI | auto_increment | |
| company_id | int(11) | NO | MUL | | → insurance_company FK |
| document_name | varchar(200) | NO | | | 서류명 |
| document_description | text | YES | | | |
| is_required | tinyint(1) | NO | | 0 | 필수 여부 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### claim_document

청구서 첨부 파일. **Model: `ClaimDocument`** (2026-02-23 구현).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| claim_document_id | int(11) | NO | PRI | auto_increment | |
| claim_id | int(11) | NO | MUL | | → insurance_claim FK |
| supporting_document_id | int(11) | NO | MUL | | → supporting_document FK |
| document_file_url | varchar(255) | NO | | | S3 파일 경로 |
| document_file_name | varchar(255) | NO | | | 원본 파일명 |
| document_file_size | int(11) | YES | | | 바이트 |
| upload_status | varchar(20) | NO | MUL | uploaded | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 4. 건강/의료

### medical_record

진료 기록. HIRA 내진료정보열람 동기화 시 `source='codef_hira'`로 저장. **Model: `MedicalRecord`** (2026-04-08 HIRA 확장).
- 관계: customer(belongsTo), disclosureObligations(hasMany)
- Scope: forCustomer, fromHira, manual
- 상수: SOURCE_MANUAL='manual', SOURCE_CODEF_HIRA='codef_hira'

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| record_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| treatment_date | date | NO | MUL | | 진료일 |
| hospital_name | varchar(100) | YES | | | |
| department | varchar(100) | YES | | | 진단과 (HIRA resDepartment) |
| diagnosis_code | varchar(20) | YES | MUL | | 진단 코드 (KCD) |
| diagnosis_name | varchar(200) | YES | | | 진단명 |
| treatment_type | varchar(50) | YES | | | 진료 유형 (외래/입원) |
| medical_cost | decimal(10,2) | YES | | | 진료비 |
| prescription | text | YES | | | 처방 내용 (수동 입력용) |
| is_important | tinyint(1) | NO | MUL | 0 | 중요 표시 |
| visit_days | int(11) | YES | | | 내원일수 (HIRA) |
| total_amount | decimal(12,2) | YES | | | 총 진료비 (HIRA) |
| public_charge | decimal(12,2) | YES | | | 공단부담금 (HIRA) |
| deductible_amt | decimal(12,2) | YES | | | 본인부담금 (HIRA) |
| hospital_code | varchar(50) | YES | | | HIRA 병원코드 |
| detail_treat_list_json | longtext | YES | | | HIRA 세부진료/처치 JSON |
| prescribe_drug_list_json | longtext | YES | | | HIRA 처방약 JSON |
| codef_synced | tinyint(1) | NO | | 0 | CODEF 동기화 여부 |
| synced_at | datetime | YES | | | 마지막 동기화 시각 |
| source | varchar(20) | NO | | manual | manual / codef_hira |
| codef_raw_data | longtext | YES | | | CODEF 원본 응답 JSON |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

- INDEX: idx_medical_record_cust_treat (customer_id, treatment_date)
- INDEX: idx_medical_record_cust_source (customer_id, source)
- INDEX: idx_medical_record_cust_diagnosis (customer_id, diagnosis_code)

### health_checkup

NHIS 건강검진결과 (resPreviewList 회차별). **Model: `HealthCheckup`** (2026-04-08 구현).
- 관계: customer(belongsTo)
- Scope: latestForCustomer
- Accessor: `risk_summary` (KDCA 위험지표 평가, $appends)
- Observer: HealthCheckupObserver (저장 시 risk 평가 → FCM 알림)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| checkup_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| checkup_date | date | NO | MUL | | 검진일 |
| checkup_year | varchar(4) | YES | MUL | | 검진연도 (YYYY) |
| checkup_type | varchar(50) | YES | | | 본인검진/영유아검진 |
| hospital_name | varchar(100) | YES | | | 검진 장소 |
| organization_name | varchar(100) | YES | | | NHIS 검진기관명 |
| opinion | text | YES | | | 검진 소견 (resOpinion) |
| judgement | varchar(50) | YES | | | 판정 (정A/정B/주의/이상...) |
| height | varchar(10) | YES | | | 신장 cm |
| weight | varchar(10) | YES | | | 체중 kg |
| waist | varchar(10) | YES | | | 허리둘레 cm |
| bmi | varchar(10) | YES | | | BMI |
| sight | varchar(20) | YES | | | 시력 |
| hearing | varchar(20) | YES | | | 청력 |
| blood_pressure | varchar(20) | YES | | | 혈압 (예: "120/80") |
| urinary_protein | varchar(20) | YES | | | 요단백 |
| hemoglobin | varchar(10) | YES | | | 혈색소 g/dL |
| fasting_blood_sugar | varchar(10) | YES | | | 공복혈당 mg/dL |
| total_cholesterol | varchar(10) | YES | | | 총콜레스테롤 mg/dL |
| hdl_cholesterol | varchar(10) | YES | | | HDL mg/dL |
| ldl_cholesterol | varchar(10) | YES | | | LDL mg/dL |
| triglyceride | varchar(10) | YES | | | 중성지방 mg/dL |
| serum_creatinine | varchar(10) | YES | | | 혈청크레아티닌 mg/dL |
| gfr | varchar(10) | YES | | | GFR mL/min |
| ast | varchar(10) | YES | | | AST U/L |
| alt | varchar(10) | YES | | | ALT U/L |
| y_gtp | varchar(10) | YES | | | γGTP U/L |
| tb_chest_disease | varchar(50) | YES | | | 폐결핵·흉부질환 |
| osteoporosis | varchar(50) | YES | | | 골다공증 |
| question_info_json | longtext | YES | | | 문진 JSON (resQuestionInfoList) |
| checkup_results | text | YES | | | (레거시) 통합 텍스트 |
| abnormal_findings | text | YES | | | (레거시) 이상 소견 |
| follow_up_required | tinyint(1) | YES | | | (레거시) 추적 필요 |
| codef_synced | tinyint(1) | NO | | 0 | CODEF 동기화 여부 |
| synced_at | datetime | YES | | | 마지막 동기화 시각 |
| codef_raw_data | longtext | YES | | | CODEF 원본 응답 JSON |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

- INDEX: idx_health_checkup_cust_date (customer_id, checkup_date)
- INDEX: idx_health_checkup_cust_year (customer_id, checkup_year)

### health_external_account

NHIS 건강검진 + HIRA 내진료정보 동의/연동 상태. **Model: `HealthExternalAccount`** (2026-04-08 구현).
- 관계: customer(belongsTo)
- 메서드: hasCheckupConsent(), hasMedicalInfoConsent()

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| health_external_account_id | int(10) unsigned | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | UNI | | → customer FK |
| checkup_consent_at | datetime | YES | | | NHIS 건강검진 조회 동의 시각 |
| medical_info_consent_at | datetime | YES | | | HIRA 내진료정보열람 동의 시각 |
| health_in_linked | tinyint(1) | NO | | 0 | 건강iN 연동 여부 (예측 API용) |
| health_in_linked_at | datetime | YES | | | 건강iN 연동 시각 |
| last_checkup_sync_at | datetime | YES | | | NHIS 검진결과 마지막 동기화 |
| last_medical_info_sync_at | datetime | YES | | | HIRA 진료정보 마지막 동기화 |
| last_examination_sync_at | datetime | YES | | | NHIS 검진대상 마지막 동기화 |
| last_health_age_sync_at | datetime | YES | | | NHIS 건강나이 마지막 동기화 |
| last_prediction_sync_at | datetime | YES | | | NHIS 예측 5종 마지막 동기화 |
| medical_info_range_start | date | YES | | | HIRA 마지막 호출 startDate (증분용) |
| medical_info_range_end | date | YES | | | HIRA 마지막 호출 endDate |
| is_active | tinyint(1) | NO | | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### health_prediction

NHIS 건강나이 + 질환 예측 5종 (cardio/stroke/diabetes/kidney/mi/health_age). **Model: `HealthPrediction`** (2026-04-08 구현).
- 관계: customer(belongsTo)
- Scope: forCustomer, ofType, latest
- 상수: TYPE_CARDIO/STROKE/DIABETES/KIDNEY/MI/HEALTH_AGE
- Observer: HealthPredictionObserver (risk_grade>=4 → FCM 알림)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| prediction_id | int(10) unsigned | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| prediction_type | varchar(20) | NO | | | cardio/stroke/diabetes/kidney/mi/health_age |
| checkup_date | date | YES | | | 예측 기준 검진일자 |
| risk_grade | varchar(2) | YES | | | 1=잘하고있어요 ~ 5=관리필요 |
| risk_ratio | varchar(20) | YES | | | 3년 내 발생 확률 % |
| average_age | varchar(10) | YES | | | 나의 연령대 |
| average_ratio | varchar(20) | YES | | | 유사집단 내 위치 (21/100) |
| health_age | varchar(10) | YES | | | 건강나이 (health_age 전용) |
| chronological_age | varchar(10) | YES | | | 실제나이 (health_age 전용) |
| change_after_text | text | YES | | | 위험요인 조절 시 안내 문구 |
| detail_list_json | longtext | YES | | | resDetailList JSON (위험요인) |
| sub_detail_list_json | longtext | YES | | | resSubDetailList JSON (처방) |
| compare_list_json | longtext | YES | | | resCompareList JSON (비교) |
| codef_raw_data | longtext | YES | | | CODEF 원본 응답 |
| predicted_at | datetime | NO | | | 예측 수행 시각 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

- INDEX: idx_health_prediction_cust_type (customer_id, prediction_type)
- INDEX: idx_health_prediction_predicted_at

### disclosure_obligation

알릴의무 (고지의무 추적).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| disclosure_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| medical_record_id | int(11) | YES | MUL | | → medical_record FK |
| disease_name | varchar(200) | NO | | | 질병명 |
| diagnosis_date | date | NO | | | 진단일 |
| tracking_start_date | date | NO | MUL | | 추적 시작일 |
| tracking_end_date | date | NO | | | 추적 종료일 |
| is_disclosed | tinyint(1) | NO | MUL | 0 | 고지 완료 여부 |
| disclosure_date | date | YES | | | 고지일 |
| notes | text | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 5. 고객 관리

### customer_status

고객 상태 이력.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| status_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| agent_id | char(8) | YES | MUL | | → agent FK |
| status_type | varchar(50) | NO | MUL | | 상태 유형 |
| status_value | varchar(100) | NO | | | 상태 값 |
| status_description | text | YES | | | |
| status_date | date | NO | MUL | | |
| is_important | tinyint(1) | NO | MUL | 0 | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### customer_assignment

DB 배분 (고객 배정).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| assignment_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| agent_id | char(8) | NO | MUL | | → agent FK (배정 설계사) |
| admin_id | char(8) | NO | MUL | | → admin FK (배정자) |
| assignment_type | varchar(50) | NO | | | 배분 유형 |
| assignment_date | date | NO | MUL | | 배분일 |
| notes | text | YES | | | |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### memo

고객 메모.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| memo_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| author_id | char(8) | NO | MUL | | 작성자 ID |
| author_type | enum('AGENT','ADMIN') | NO | | | |
| title | varchar(200) | YES | | | 메모 제목 |
| content | text | NO | | | 메모 내용 |
| memo_date | datetime | NO | MUL | | 메모 날짜 |
| created_at | datetime | NO | MUL | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### consultation

상담 요청/기록.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| consultation_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | YES | MUL | | → customer FK |
| assignee_id | char(8) | YES | MUL | | 담당자 ID |
| assignee_type | enum('AGENT','ADMIN') | YES | | | |
| consultation_type | varchar(50) | YES | | | 상담 유형 |
| consultation_date | datetime | YES | MUL | | 상담일시 |
| consultation_content | text | YES | | | 상담 내용 |
| consultation_answer | text | YES | | | 설계사 답변 내용 |
| consultation_status | varchar(20) | NO | MUL | pending | pending/in_progress/completed |
| customer_name | varchar(50) | YES | | | 비정규화 |
| customer_phone | varchar(20) | YES | | | 비정규화 |
| created_by_id | char(8) | YES | | | |
| updated_by_id | char(8) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### complaint

컴플레인/민원. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| complaint_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| receiver_agent_id | char(8) | YES | MUL | | 접수 설계사 |
| receiver_admin_id | char(8) | YES | MUL | | 접수 관리자 |
| receiver_type | enum('AGENT','ADMIN') | NO | | | |
| assignee_agent_id | char(8) | YES | MUL | | 처리 담당 설계사 |
| assignee_admin_id | char(8) | YES | MUL | | 처리 담당 관리자 |
| assignee_type | enum('AGENT','ADMIN') | YES | | | |
| complaint_type | varchar(50) | NO | | | 민원 유형 |
| complaint_title | varchar(200) | NO | | | |
| complaint_content | text | NO | | | |
| complaint_status | varchar(20) | NO | MUL | received | received/processing/resolved/closed |
| resolution | text | YES | | | 처리 결과 |
| received_at | datetime | NO | MUL | | 접수일시 |
| resolved_at | datetime | YES | | | 해결일시 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 6. 커뮤니케이션

### message

메시지 발송 이력 (문자/카카오).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| message_id | int(11) | NO | PRI | auto_increment | |
| receiver_id | char(8) | YES | MUL | | → customer FK |
| sender_id | char(8) | NO | MUL | | 발송자 ID |
| sender_type | enum('AGENT','ADMIN') | NO | | | |
| phone_number | varchar(20) | NO | | | 수신 전화번호 |
| message_type | varchar(20) | NO | | | SMS/LMS/KAKAO 등 |
| message_content | text | NO | | | |
| image_url | varchar(255) | YES | | | 첨부 이미지 URL |
| send_status | varchar(20) | NO | MUL | pending | pending/sent/failed |
| scheduled_at | datetime | YES | MUL | | 예약 발송 시각 |
| sent_at | datetime | YES | MUL | | 실제 발송 시각 |
| error_message | text | YES | | | 발송 실패 사유 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### notification

앱 내 알림.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| notification_id | int(11) | NO | PRI | auto_increment | |
| receiver_id | char(8) | NO | MUL | | 수신자 ID |
| receiver_type | enum('CUSTOMER','AGENT','ADMIN') | NO | | | |
| sender_id | char(8) | YES | MUL | | 발신자 ID |
| sender_type | enum('AGENT','ADMIN','SYSTEM') | YES | | | |
| notification_type | varchar(50) | NO | | | 알림 유형 |
| title | varchar(200) | NO | | | |
| content | text | NO | | | |
| is_read | tinyint(1) | NO | MUL | 0 | |
| read_at | datetime | YES | | | |
| sent_at | datetime | YES | MUL | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |

### notice

공지사항. **Model: `Notice`** (`app/Models/Notice.php`, 2026-02-26 구현).
- 관계: `author()` → BelongsTo(Admin, 'author_id', 'admin_id')

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| notice_id | int(11) | NO | PRI | auto_increment | |
| author_id | char(8) | NO | MUL | | 작성자 (관리자) |
| notice_type | varchar(50) | YES | | | 공지 유형 |
| title | varchar(200) | NO | | | |
| content | text | NO | | | |
| is_pinned | tinyint(1) | NO | MUL | 0 | 상단 고정 |
| display_start_date | date | YES | MUL | | 노출 시작일 |
| display_end_date | date | YES | | | 노출 종료일 |
| view_count | int(11) | NO | | 0 | 조회수 |
| created_at | datetime | NO | MUL | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### satisfaction_survey

만족도 조사.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| survey_id | int(11) | NO | PRI | auto_increment | |
| agent_id | char(8) | NO | MUL | | → agent FK (발송 설계사) |
| customer_id | char(8) | YES | MUL | | → customer FK |
| survey_title | varchar(200) | NO | | | |
| survey_content | text | NO | | | |
| rating | int(11) | YES | | | 평점 (1~5) |
| feedback | text | YES | | | 고객 피드백 |
| survey_status | varchar(20) | NO | MUL | sent | sent/responded |
| sent_at | datetime | YES | MUL | | |
| responded_at | datetime | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 7. 실적/통계

### performance

설계사 월간 실적.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| performance_id | int(11) | NO | PRI | auto_increment | |
| agent_id | char(8) | NO | MUL | | → agent FK |
| year | int(11) | NO | MUL | | 연도 |
| month | int(11) | NO | | | 월 |
| db_assigned_count | int(11) | NO | | 0 | DB배분 건수 |
| contract_count | int(11) | NO | | 0 | 계약 건수 |
| contract_amount | decimal(15,2) | NO | | 0.00 | 계약 금액 |
| consultation_count | int(11) | NO | | 0 | 상담 건수 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 8. 병원 혜택

### partner_hospital

제휴 병원. **Model: `PartnerHospital`** (구현 완료). Trait: `HasScheduleConfig`.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| hospital_id | int(11) | NO | PRI | auto_increment | |
| hospital_name | varchar(100) | NO | MUL | | |
| business_number | varchar(20) | YES | | | 사업자등록번호 |
| address | varchar(255) | NO | | | |
| detailed_address | varchar(255) | YES | | | |
| contact_phone | varchar(20) | YES | | | |
| latitude | decimal(10,8) | YES | MUL | | 위도 |
| longitude | decimal(11,8) | YES | | | 경도 |
| business_hours | text | YES | | | 영업시간 |
| introduction | text | YES | | | 소개 |
| specialties | text | YES | | | 진료 과목 |
| schedule_config | json | YES | | NULL | 예약 스케줄 설정 (요일별/차단일/특별일정) |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### hospital_benefit

병원 혜택 정보. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| benefit_id | int(11) | NO | PRI | auto_increment | |
| hospital_id | int(11) | NO | MUL | | → partner_hospital FK |
| benefit_name | varchar(200) | NO | | | 혜택명 |
| benefit_description | text | YES | | | |
| benefit_type | varchar(50) | YES | | | 혜택 유형 |
| discount_rate | decimal(5,2) | YES | | | 할인율 (%) |
| is_active | tinyint(1) | NO | MUL | 1 | |
| start_date | date | YES | MUL | | 시작일 |
| end_date | date | YES | | | 종료일 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### benefit_usage

혜택 사용 이력. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| usage_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| hospital_id | int(11) | NO | MUL | | → partner_hospital FK |
| benefit_id | int(11) | NO | MUL | | → hospital_benefit FK |
| consultation_id | int(11) | YES | MUL | | → consultation FK |
| usage_date | date | NO | MUL | | 사용일 |
| discount_amount | decimal(10,2) | YES | | | 할인 금액 |
| notes | text | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 9. 공통

### common_code

공통 코드 관리. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| code_id | int(11) | NO | PRI | auto_increment | |
| code | varchar(6) | NO | UNI | | 코드값 |
| code_name | varchar(100) | NO | | | 코드명 |
| code_description | text | YES | | | |
| parent_code | varchar(6) | YES | MUL | | 상위 코드 (계층 구조) |
| code_type | varchar(2) | NO | MUL | | 코드 유형 (CT/IT/CS 등) |
| sort_order | int(11) | YES | | | 정렬 순서 |
| is_active | tinyint(1) | NO | MUL | 1 | |
| is_system | tinyint(1) | NO | MUL | 0 | 시스템 코드 (삭제 불가) |
| note | varchar(255) | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### consent_template

동의서 템플릿 관리 (고유식별정보, 민감정보, 개인신용정보, 내보험다보여 정보이용). 관리자가 전역 편집.

**Model**: `ConsentTemplate` ✅
- 상수: `TYPE_UNIQUE_ID`, `TYPE_SENSITIVE`, `TYPE_CREDIT`, `TYPE_CREDIT4U`, `TYPE_HEALTH_CHECKUP`, `TYPE_MEDICAL_INFO`

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| consent_template_id | bigint(20) unsigned | NO | PRI | auto_increment | |
| consent_type | varchar(30) | NO | UNI | | unique_id / sensitive / credit / credit4u / health_checkup / medical_info |
| title | varchar(100) | NO | | | 동의서 제목 |
| content | text | NO | | | 동의서 본문 |
| is_active | tinyint(1) | NO | | 1 | 활성 여부 |
| created_at | timestamp | YES | | | |
| updated_at | timestamp | YES | | | |

**기본 데이터**: unique_id(고유식별정보), sensitive(민감정보), credit(개인신용정보), credit4u(내보험다보여 정보이용), health_checkup(건강검진정보 조회 NHIS), medical_info(내진료정보열람 HIRA) 6건

---

## 10. CODEF 신용정보원 내보험다보여 연동

CODEF 플랫폼을 통해 한국신용정보원 "내보험다보여" 서비스에서 고객의 가입 보험 정보를 조회/저장.
관련 테이블: `credit4u_account`, `insurance_coverage`, `insurance_payment_history`, `insurance_statistics`
참고: 보험 상세 정보는 [`insurance` 테이블](#insurance) 확장 컬럼에 저장됨.

### credit4u_account

내보험다보여 계정 연동 정보. **Model: `Credit4uAccount`** (2026-04-07 구현).
- 관계: customer(belongsTo), consentTemplate(belongsTo → ConsentTemplate)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| credit4u_account_id | int(10) unsigned | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | UNI | | → customer FK |
| credit4u_login_id | varchar(100) | YES | | | 내보험다보여 ID (동의만 한 상태에선 NULL) |
| registration_status | enum('consented','registered','needs_verify','temp_password') | NO | | consented | 가입 상태 |
| consent_template_id | bigint(20) unsigned | YES | MUL | | → consent_template FK |
| last_synced_at | datetime | YES | | | 마지막 동기화 시각 |
| consent_agreed_at | datetime | YES | | | 정보이용 동의 시각 |
| is_active | tinyint(1) | NO | | 1 | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

> **비밀번호 저장 여부**: 보안상 내보험다보여 비밀번호는 저장하지 않음. 매번 사용자 입력 받아 RSA 암호화 후 CODEF로 전송.

### insurance_coverage

보험 보장내역. CODEF 계약정보 API의 `resCoverageLists` 데이터. **Model: `InsuranceCoverage`** (2026-04-07 구현).
- 관계: insurance(belongsTo)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| coverage_id | int(10) unsigned | NO | PRI | auto_increment | |
| insurance_id | int(11) | NO | MUL | | → insurance FK |
| insured_person | varchar(50) | YES | | | 피보험자 (resInsuredPerson) |
| coverage_name | varchar(200) | NO | | | 보장명 (resCoverageName) |
| coverage_amount | decimal(15,2) | YES | | | 보장금액 (resCoverageAmount) |
| coverage_status | varchar(20) | YES | | | 상태 (resCoverageStatus) |
| agreement_type | varchar(20) | YES | | | 특약구분 (resAgreementType) |
| coverage_type | varchar(20) | YES | | | 보장유형 - 실손형 (resType) |
| object_info | varchar(200) | YES | | | 목적물 - 화재특종 (resObject) |
| zip_code | varchar(10) | YES | | | 우편번호 - 화재특종 (resZipCode) |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

> **동기화 정책**: 계약정보 재동기화 시 해당 insurance_id의 기존 보장내역을 삭제 후 재생성.

### insurance_payment_history

실손형 보험 지급내역. CODEF 계약정보 API의 `resActualLossPaymentList`. **Model: `InsurancePaymentHistory`** (2026-04-07 구현).
- 관계: customer(belongsTo), insurance(belongsTo, NULL 허용)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| payment_id | int(10) unsigned | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK (직접 참조, 매칭 실패해도 유지) |
| insurance_id | int(11) | YES | MUL | | → insurance FK (증권번호 매칭 성공 시) |
| company_name | varchar(100) | YES | | | 보험사명 (resCompanyNm, 비정규화) |
| policy_number | varchar(50) | YES | | | 증권번호 (resPolicyNumber, 매칭 키) |
| insurance_name | varchar(200) | YES | | | 보험명 (resInsuranceName) |
| res_number | varchar(20) | YES | | | 호수/순번 (resNumber) |
| occur_date_time | varchar(20) | YES | | | 원사고발생일시 (resOccurDateTime) |
| total_amount | decimal(12,2) | YES | | | 총 지급금액 (resTotalAmount) |
| payment_type | varchar(50) | YES | | | 지급유형 (resType) |
| reason | varchar(200) | YES | | | 지급사유 (resReasonForPayment) |
| paid_amount | decimal(12,2) | YES | | | 지급금액 (resPaidAmount) |
| payment_date | date | YES | | | 지급일 (resPaymentDate) |
| judge_result | varchar(50) | YES | | | 심사결과 (resJudgeResult) |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

> **FK 설계**: insurance와 1:1 매칭이 안 될 수 있으므로 customer_id를 직접 참조 (NOT NULL), insurance_id는 NULL 허용.

### insurance_statistics

분석통계자료. CODEF 계약정보 API의 `resFlatRateStatisticsList` / `resActualLossStatisticsList`. **Model: `InsuranceStatistics`** (2026-04-07 구현).
- 관계: customer(belongsTo)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| stat_id | int(10) unsigned | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| stat_type | enum('flat_rate','actual_loss') | NO | | | 정액형/실손형 |
| coverage_name | varchar(200) | NO | | | 보장명 (resCoverageName) |
| self_coverage_amt | decimal(15,2) | YES | | | 본인 보장금액 (resSelfCoverageAmt) |
| avg_group_coverage_amt | decimal(15,2) | YES | | | 동일그룹 평균 보장금액 (resAvgGroupCoverageAmt) |
| self_reg_yn | varchar(1) | YES | | | 실손 가입여부 Y/N (resSelfRegYN) |
| avg_group_reg_rate | varchar(10) | YES | | | 동일그룹 가입률 % (resAvgGroupRegRate) |
| synced_at | datetime | NO | | | 동기화 시각 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 11. 캘린더/일정

### agent_calendar_event

설계사 캘린더 일정. 수동 등록 또는 시스템 자동 생성 (생일, 계약 만기 등).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| event_id | int(11) | NO | PRI | auto_increment | |
| agent_id | char(8) | NO | MUL | | → agent FK |
| customer_id | char(8) | YES | MUL | | → customer FK |
| contract_id | int(11) | YES | MUL | | → contract FK |
| event_type | varchar(30) | NO | MUL | manual | manual/birthday/contract_expiry/insurance_expiry |
| title | varchar(200) | NO | | | |
| memo | text | YES | | | |
| event_date | date | NO | MUL | | |
| start_time | time | YES | | | |
| end_time | time | YES | | | |
| is_all_day | tinyint(1) | NO | | 1 | |
| is_recurring | tinyint(1) | NO | | 0 | |
| is_completed | tinyint(1) | NO | | 0 | |
| source | varchar(20) | NO | MUL | manual | manual/system |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

**Model**: `App\Models\CalendarEvent` (구현 완료)
**관계**: agent(belongsTo), customer(belongsTo), contract(belongsTo), reminders(hasMany → Reminder)

### agent_reminder

일정 사전 알림. D-N일 전 알림 설정.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| reminder_id | int(11) | NO | PRI | auto_increment | |
| event_id | int(11) | NO | MUL | | → agent_calendar_event FK |
| agent_id | char(8) | NO | MUL | | → agent FK |
| remind_before_days | int(11) | NO | | 1 | D-N일 전 |
| is_sent | tinyint(1) | NO | | 0 | 발송 여부 |
| sent_at | datetime | YES | | | |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

**Model**: `App\Models\Reminder` (구현 완료)
**관계**: event(belongsTo → CalendarEvent), agent(belongsTo)

---

## 12. 팩스 (FaxClientNC)

FaxClientNC 연동용 테이블. **테이블명 반드시 대문자 유지**.

### FC_META_TRAN

팩스 발송 메타 정보 (건당 1행).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| TR_BATCHID | bigint(20) unsigned | NO | PRI | auto_increment | |
| TR_TYPE | char(1) | YES | | 1 | 발송 유형 |
| TR_SENDDATE | datetime | NO | | | 발송 요청일시 |
| TR_ID | varchar(16) | YES | | | 발신자 ID |
| TR_TITLE | varchar(128) | YES | | | 팩스 제목 |
| TR_SENDNAME | varchar(50) | YES | | | 발신자명 |
| TR_SENDFAXNUM | varchar(20) | YES | | | 발신 팩스번호 |
| TR_MSGCOUNT | int(11) | NO | | | 수신자 수 |
| TR_DOCNAME | varchar(255) | NO | | | 문서 파일 경로 |
| TR_SENDSTAT | varchar(1) | YES | | - | 발송 상태 (- → 0 → 완료) |
| TR_RESERVETIME | datetime | YES | | | 예약 시각 |

### FC_MSG_TRAN

팩스 수신자 정보 (수신자당 1행).

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| TR_BATCHID | bigint(20) unsigned | NO | PRI | | → FC_META_TRAN FK |
| TR_SERIALNO | bigint(20) unsigned | NO | PRI | | 수신자 일련번호 |
| TR_SENDDATE | datetime | NO | | | |
| TR_NAME | varchar(50) | YES | | | 수신자명 |
| TR_PHONE | varchar(20) | NO | | | 수신 팩스번호 |
| TR_EMAIL | varchar(100) | YES | | | |
| TR_SENDSTAT | varchar(1) | YES | | 0 | 발송 상태 |
| TR_RSLTSTAT | varchar(3) | YES | | - | 결과 코드 |
| TR_SENDTIME | varchar(14) | YES | | | 발송 시각 |
| TR_RECVTIME | varchar(14) | YES | | | 수신 시각 |
| TR_DURATION | int(11) | YES | | | 소요 시간 (초) |
| TR_PAGECNT | int(11) | YES | | | 전송 페이지 수 |

### FC_RECV_TRAN

팩스 수신 정보.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| TR_MSGID | varchar(50) | YES | | | 메시지 ID |
| TR_TITLE | varchar(500) | YES | | | |
| TR_SENDFAXNUM | varchar(20) | YES | | | 발신 팩스번호 |
| TR_RECVFAXNUM | varchar(20) | YES | | | 수신 팩스번호 |
| TR_RECVTIME | varchar(20) | YES | | | 수신 시각 |
| TR_FILENAMELIST | varchar(500) | YES | | | 수신 파일 목록 |

---

## 13. Laravel 시스템 테이블

프레임워크 자동 생성 테이블. 직접 수정하지 않음.

| 테이블 | 용도 |
|--------|------|
| cache | 캐시 저장소 (OTP 등) |
| cache_locks | 캐시 락 |
| jobs | 큐 작업 |
| job_batches | 배치 작업 |
| failed_jobs | 실패한 작업 |
| migrations | 마이그레이션 이력 |
| personal_access_tokens | Sanctum 인증 토큰 |
| sessions | 세션 저장소 |

---

## 14. 간편 청구/예약

### claim_request

간편 청구 신청 (사용자 앱 리뉴얼). 로그인 없이 이름/전화번호로 접수. **Model: `ClaimRequest`** (2026-05-31 구현).
- 관계: files(hasMany → ClaimRequestFile), assignedAgent(belongsTo → Agent), linkedClaim(belongsTo → InsuranceClaim)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| request_id | int(11) | NO | PRI | auto_increment | |
| name | varchar(50) | NO | | | 신청자 이름 |
| phone | varchar(20) | NO | | | 신청자 전화번호 |
| memo | text | YES | | | 메모 |
| status | enum('pending','assigned','completed','cancelled') | NO | MUL | pending | |
| assigned_agent_id | varchar(20) | YES | MUL | | → agent FK |
| linked_claim_id | int(11) | YES | | | → insurance_claim FK |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP | |
| updated_at | timestamp | NO | | CURRENT_TIMESTAMP ON UPDATE | |

### claim_request_file

간편 청구 신청 첨부파일. **Model: `ClaimRequestFile`** (2026-05-31 구현).
- 관계: claimRequest(belongsTo → ClaimRequest)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| file_id | int(11) | NO | PRI | auto_increment | |
| request_id | int(11) | NO | MUL | | → claim_request FK |
| file_url | varchar(500) | NO | | | S3 파일 경로 |
| file_name | varchar(255) | YES | | | 원본 파일명 |
| file_size | int(11) | YES | | | 바이트 |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP | |

### health_center

건강검진 센터 (partner_hospital과 별도). **Model: `HealthCenter`** (2026-05-31 구현).
- 관계: reservations(hasMany → HospitalReservation), accounts(hasMany → HospitalAccount)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| center_id | int(11) | NO | PRI | auto_increment | |
| center_name | varchar(100) | NO | MUL | | |
| address | varchar(255) | NO | | | |
| detailed_address | varchar(255) | YES | | | |
| latitude | decimal(10,8) | YES | | | 위도 |
| longitude | decimal(11,8) | YES | | | 경도 |
| contact_phone | varchar(20) | YES | | | |
| business_hours | text | YES | | | 영업시간 |
| introduction | text | YES | | | 소개 |
| schedule_config | json | YES | | NULL | 예약 스케줄 설정 (요일별/차단일/특별일정) |
| is_active | tinyint(1) | NO | MUL | 1 | |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP | |
| updated_at | timestamp | NO | | CURRENT_TIMESTAMP ON UPDATE | |

### hospital_reservation

병원/건강검진 센터 예약. **Model: `HospitalReservation`** (2026-05-31 구현).
- 관계: hospital(belongsTo → PartnerHospital), healthCenter(belongsTo → HealthCenter)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| reservation_id | int(11) | NO | PRI | auto_increment | |
| hospital_id | int(11) | YES | MUL | | → partner_hospital FK |
| center_id | int(11) | YES | MUL | | → health_center FK |
| reservation_type | enum('hospital','health_center') | NO | | | |
| patient_name | varchar(50) | NO | | | |
| patient_phone | varchar(20) | NO | | | |
| reservation_date | date | NO | MUL | | |
| reservation_time | varchar(10) | NO | | | "09:00" 등 |
| memo | text | YES | | | |
| status | enum('pending','confirmed','cancelled','completed') | NO | MUL | pending | |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP | |
| updated_at | timestamp | NO | | CURRENT_TIMESTAMP ON UPDATE | |

### hospital_account

병원/센터 포털 로그인 계정. **Model: `HospitalAccount`** (2026-05-31 구현).
- 관계: hospital(belongsTo → PartnerHospital), healthCenter(belongsTo → HealthCenter)

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| account_id | int(11) | NO | PRI | auto_increment | |
| hospital_id | int(11) | YES | | | → partner_hospital FK |
| center_id | int(11) | YES | | | → health_center FK |
| username | varchar(50) | NO | UNI | | 로그인 ID |
| password | varchar(255) | NO | | | bcrypt 해시 |
| account_name | varchar(50) | YES | | | 표시 이름 |
| is_active | tinyint(1) | NO | | 1 | |
| created_at | timestamp | NO | | CURRENT_TIMESTAMP | |
| updated_at | timestamp | NO | | CURRENT_TIMESTAMP ON UPDATE | |

---

## 변경 이력

| 날짜 | 변경 내용 |
|------|----------|
| 2026-02-22 | 최초 작성 (운영 DB DESCRIBE 기준) |
| 2026-02-22 | customer 테이블에 telecom, acquisition_channel, last_contact_date 추가 |
| 2026-02-22 | agent 테이블에 specialization 추가 |
| 2026-02-23 | claim_document: Model 미구현 → `ClaimDocument` 모델 구현 완료 |
| 2026-02-23 | form_field.field_type 허용값 목록 업데이트: checkbox, radio, consent, signature 추가 |
| 2026-02-24 | 바로청구 기능 추가: 폼 필드에서 고객 정보 추출 → 자동 고객 생성 후 청구 (customer_id NOT NULL 유지) |
| 2026-02-26 | admin: Model 미구현 → `Admin` 모델 구현 완료 (관계: account, notices, customerAssignments) |
| 2026-02-26 | notice: Model 미구현 → `Notice` 모델 구현 완료 (관계: author → Admin) |
| 2026-02-26 | Account 모델에 `admin()` hasOne 관계 추가 |
| 2026-02-26 | Customer 모델에 `medicalRecords()` hasMany 관계 추가 |
| 2026-03-01 | insurance_claim.customer_id: NOT NULL → NULL 허용 변경 (임시저장 draft 기능 지원) |
| 2026-03-01 | insurance_claim.claim_status: 'draft' 값 추가 (임시저장 상태, DDL 변경 없음 varchar(20)) |
| 2026-03-03 | form_field: standard_field_code VARCHAR(50) NULL 컬럼 추가 (다중 보험 청구 표준 필드 매칭 키) |
| 2026-03-03 | batch_claim 테이블 신규 생성 (다중 보험 청구 묶음) |
| 2026-03-03 | insurance_claim: batch_claim_id INT NULL 컬럼 추가 (배치 FK, NULL이면 단건) |
| 2026-03-07 | customer.resident_number: char(13) → TEXT 변경 (AES-256 암호화 저장, Laravel encrypted cast) |
| 2026-03-07 | contract: expiration_date DATE NULL 컬럼 추가 (보험 만기일) |
| 2026-03-07 | agent_calendar_event 테이블 신규 생성 (설계사 캘린더 일정, 수동+시스템 자동 생성) |
| 2026-03-07 | agent_reminder 테이블 신규 생성 (일정 사전 알림, D-N일 전 리마인더) |
| 2026-03-07 | fcm_token 테이블 신규 생성 (FCM 푸시 알림 토큰 저장, FcmToken 모델 구현) |
| 2026-03-09 | consent_template 테이블 신규 생성 (동의서 관리: 고유식별정보/민감정보/개인신용정보, ConsentTemplate 모델 구현) |
| 2026-03-13 | consultation: consultation_answer TEXT NULL 컬럼 추가 (설계사 답변 내용 저장) |
| 2026-03-13 | insurance_claim.claim_status 문서 오류 수정: reviewing→processing, completed→paid (코드 기준으로 통일) |
| 2026-03-16 | insurance_claim: paid_date DATE NULL, paid_amount DECIMAL(12,2) NULL 컬럼 추가 (보험금 지급 정보) |
| 2026-04-07 | CODEF 신용정보원 내보험다보여 연동 스키마 추가 (마이그레이션 5건, batch 8) |
| 2026-04-07 | insurance 테이블에 CODEF 동기화용 16개 컬럼 추가 (contract_type, contractor_name, contract_status, company_phone, company_homepage, payment_cycle, start_date, end_date, insured_person, res_number, contract_date_of, car_name, car_number, codef_synced, codef_raw_data, synced_at) + Insurance 모델 신규 구현 |
| 2026-04-07 | credit4u_account 테이블 신규 생성 (내보험다보여 계정 연동 정보, Credit4uAccount 모델 구현) |
| 2026-04-07 | insurance_coverage 테이블 신규 생성 (보험 보장내역, InsuranceCoverage 모델 구현) |
| 2026-04-07 | insurance_payment_history 테이블 신규 생성 (실손 지급내역, InsurancePaymentHistory 모델 구현) |
| 2026-04-07 | insurance_statistics 테이블 신규 생성 (분석통계자료, InsuranceStatistics 모델 구현) |
| 2026-04-07 | consent_template: PK 타입 문서 정정 (int(11) → 실제는 bigint(20) unsigned), Credit4U 동의 템플릿(consent_type='credit4u') 시드 추가, consent_type varchar(20) → varchar(30) |
| 2026-04-07 | Customer 모델에 insurances/credit4uAccount/insuranceStatistics/insurancePaymentHistories 관계 추가 |
| 2026-04-07 | ConsentTemplate 모델에 TYPE_CREDIT4U 상수 추가 |
| 2026-04-08 | NHIS 건강검진 + HIRA 내진료정보열람 통합 스키마 추가 (마이그레이션 5건, batch 9) |
| 2026-04-08 | health_checkup 테이블 확장: 30+ 컬럼 추가 (height/weight/bmi/blood_pressure/공복혈당/콜레스테롤/AST/ALT/GFR 등 NHIS resPreviewList 매핑 + checkup_year/organization_name/opinion/judgement/question_info_json/codef_synced/synced_at/codef_raw_data) — `HealthCheckup` 모델 신규 구현 (risk_summary accessor 포함) |
| 2026-04-08 | medical_record 테이블 확장: HIRA 필드 12개 추가 (department, visit_days, total_amount, public_charge, deductible_amt, hospital_code, detail_treat_list_json, prescribe_drug_list_json, codef_synced, synced_at, source, codef_raw_data) — `MedicalRecord` 모델에 fillable/scope/상수 추가 |
| 2026-04-08 | health_external_account 테이블 신규 생성 (NHIS+HIRA 동의/연동 통합 추적, `HealthExternalAccount` 모델 구현) |
| 2026-04-08 | health_prediction 테이블 신규 생성 (건강나이 + 5종 질환예측 통합 저장, `HealthPrediction` 모델 구현) |
| 2026-04-08 | consent_template에 health_checkup, medical_info 2종 시드 추가 (NHIS/HIRA 동의서); ConsentTemplate 모델에 TYPE_HEALTH_CHECKUP/TYPE_MEDICAL_INFO 상수 추가 |
| 2026-04-08 | Customer 모델에 healthExternalAccount/healthCheckups/healthPredictions 관계 추가 |
| 2026-04-08 | HealthCheckupObserver/HealthPredictionObserver 추가 (저장 시 위험지표 평가 → FCM 알림); HealthRiskNotificationCommand 일일 배치(`health:notify-risks`, 매일 09:00) |
| 2026-04-10 | **운영 DB 반영**: 건강/의료 스키마 5건 마이그레이션 운영 DB 적용 (health_checkup 30컬럼 확장, medical_record 12컬럼 확장, health_external_account 신규, health_prediction 신규, consent_template health_checkup/medical_info 시드 2건) |
| 2026-05-07 | 헤더 카운트 표기 정정: 총 53개→54개, 비즈니스 45개→46개 (DEV/PROD/문서 3자 비교 스크립트로 일치 확인, 컬럼·인덱스 차이 0건) |
| 2026-05-31 | 사용자 앱 리뉴얼: 5개 테이블 신규 생성 (claim_request, claim_request_file, health_center, hospital_reservation, hospital_account) + 6개 Model 구현 (ClaimRequest, ClaimRequestFile, PartnerHospital, HealthCenter, HospitalReservation, HospitalAccount) + 공개 API/관리자 API/병원 포털 API 추가 |
| 2026-05-31 | partner_hospital, health_center에 schedule_config JSON NULL 컬럼 추가 (예약 스케줄 커스터마이징: 요일별/차단일/특별일정/간격 설정). HasScheduleConfig Trait 구현. 병원 포털 스케줄 API 2개 추가 (GET/PUT /hospital-portal/schedule) |
