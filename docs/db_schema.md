# MaeumOn DB 스키마 (운영 기준)

> **최종 업데이트**: 2026-02-26
> **DB**: MySQL (MariaDB)
> **총 테이블**: 43개 (비즈니스 35개 + Laravel 시스템 8개)

---

## 목차

1. [인증/사용자](#1-인증사용자) — account, admin, agent, customer, device_token
2. [보험/계약](#2-보험계약) — insurance_company, insurance, contract
3. [보험청구](#3-보험청구) — insurance_claim, claim_form, form_page, form_field, claim_field_value, supporting_document, claim_document
4. [건강/의료](#4-건강의료) — medical_record, health_checkup, disclosure_obligation
5. [고객 관리](#5-고객-관리) — customer_status, customer_assignment, memo, consultation, complaint
6. [커뮤니케이션](#6-커뮤니케이션) — message, notification, notice, satisfaction_survey
7. [실적/통계](#7-실적통계) — performance
8. [병원 혜택](#8-병원-혜택) — partner_hospital, hospital_benefit, benefit_usage
9. [공통](#9-공통) — common_code
10. [팩스](#10-팩스-faxclientnc) — FC_META_TRAN, FC_MSG_TRAN, FC_RECV_TRAN
11. [Laravel 시스템](#11-laravel-시스템-테이블) — sessions, cache, jobs 등

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
| resident_number | char(13) | YES | | | 주민등록번호 |
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

고객 가입보험 정보. **Model 미구현**.

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
| claim_status | varchar(20) | NO | MUL | pending | pending/reviewing/approved/rejected/completed |
| claim_date | date | NO | MUL | | 청구일 |
| approval_date | date | YES | | | 승인일 |
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

진료 기록.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| record_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| treatment_date | date | NO | MUL | | 진료일 |
| hospital_name | varchar(100) | YES | | | |
| diagnosis_code | varchar(20) | YES | MUL | | 진단 코드 |
| diagnosis_name | varchar(200) | YES | | | 진단명 |
| treatment_type | varchar(50) | YES | | | 진료 유형 |
| medical_cost | decimal(10,2) | YES | | | 진료비 |
| prescription | text | YES | | | 처방 내용 |
| is_important | tinyint(1) | NO | MUL | 0 | 중요 표시 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

### health_checkup

건강검진 기록. **Model 미구현**.

| 컬럼 | 타입 | NULL | Key | Default | 비고 |
|------|------|------|-----|---------|------|
| checkup_id | int(11) | NO | PRI | auto_increment | |
| customer_id | char(8) | NO | MUL | | → customer FK |
| checkup_date | date | NO | MUL | | 검진일 |
| checkup_type | varchar(50) | YES | | | 검진 유형 |
| hospital_name | varchar(100) | YES | | | 검진 기관 |
| checkup_results | text | YES | | | 검진 결과 (통합 텍스트) |
| abnormal_findings | text | YES | | | 이상 소견 |
| follow_up_required | tinyint(1) | YES | | | 추적 검사 필요 |
| created_at | datetime | NO | | CURRENT_TIMESTAMP | |
| updated_at | datetime | YES | | CURRENT_TIMESTAMP ON UPDATE | |

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

제휴 병원. **Model 미구현**.

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

---

## 10. 팩스 (FaxClientNC)

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

## 11. Laravel 시스템 테이블

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
