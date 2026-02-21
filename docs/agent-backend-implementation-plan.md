# 설계사(Agent) Backend 구현 계획안

## 개요
설계사(Agent) 전용 API 엔드포인트 구현 계획입니다.
기존 Admin/Customer API 패턴을 따르며, `auth:sanctum` + `role:AGENT` 미들웨어로 보호됩니다.

---

## 1. 신규 모델 (Models)

### 1-1. 기존 테이블 기반 모델

| 모델 | 테이블 | PK | PK 타입 | 비고 |
|------|--------|-----|---------|------|
| Agent | agent | agent_id | CHAR(8) | Account FK, 핵심 모델 |
| Contract | contract | contract_id | INT AUTO | 보험 계약 실적 |
| Consultation | consultation | consultation_id | INT AUTO | 상담요청 |
| Memo | memo | memo_id | INT AUTO | 고객 메모 |
| SatisfactionSurvey | satisfaction_survey | survey_id | INT AUTO | 만족도 조사 |
| CustomerStatus | customer_status | status_id | INT AUTO | 고객 상태 이력 |
| CustomerAssignment | customer_assignment | assignment_id | INT AUTO | DB 배분 |
| Performance | performance | performance_id | INT AUTO | 월간 실적 |
| DisclosureObligation | disclosure_obligation | obligation_id | INT AUTO | 알릴의무 (5년) |
| Message | message | message_id | INT AUTO | 문자/카카오/푸시 |
| Notification | notification | notification_id | INT AUTO | 알림 |

### 1-2. 신규 마이그레이션 필요: `schedule` 테이블

> `physical_design.md`에 schedule 테이블이 없으므로 신규 생성 필요

```php
Schema::create('schedule', function (Blueprint $table) {
    $table->increments('schedule_id');
    $table->char('agent_id', 8);
    $table->string('customer_id', 8)->nullable();
    $table->string('title', 100);
    $table->date('schedule_date');
    $table->time('start_time');
    $table->time('end_time')->nullable();
    $table->enum('schedule_type', ['meeting', 'call', 'visit', 'other'])->default('other');
    $table->text('memo')->nullable();
    $table->boolean('is_completed')->default(false);
    $table->timestamps();

    $table->foreign('agent_id')->references('agent_id')->on('agent');
    $table->foreign('customer_id')->references('customer_id')->on('customer');
    $table->index(['agent_id', 'schedule_date']);
});
```

### 1-3. Account 모델 수정

```php
// app/Models/Account.php에 추가
public function agent()
{
    return $this->hasOne(Agent::class, 'account_id', 'account_id');
}
```

### 1-4. Agent 모델 핵심 Relationships

```php
class Agent extends Model
{
    protected $table = 'agent';
    protected $primaryKey = 'agent_id';
    public $incrementing = false;
    protected $keyType = 'string';

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }

    public function customers()
    {
        // agent가 관리하는 고객 목록 (customer_assignment 또는 직접 FK)
        return $this->hasMany(Customer::class, 'agent_id', 'agent_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'agent_id', 'agent_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'agent_id', 'agent_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'agent_id', 'agent_id');
    }

    public function performances()
    {
        return $this->hasMany(Performance::class, 'agent_id', 'agent_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'agent_id', 'agent_id');
    }
}
```

---

## 2. API 라우트 설계

### 2-1. 라우트 정의 (`routes/api.php`)

```php
// Agent Authentication (기존 AuthController 활용)
// POST /api/auth/login (role: AGENT) → 이미 존재

// Agent APIs
Route::prefix('agent')->middleware(['auth:sanctum', 'role:AGENT'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [AgentDashboardController::class, 'index']);
    Route::get('/profile', [AgentDashboardController::class, 'profile']);

    // Customer Management
    Route::apiResource('customers', AgentCustomerController::class);
    Route::get('/customers/{customerId}/contracts', [AgentCustomerController::class, 'contracts']);

    // Customer Memos
    Route::get('/customers/{customerId}/memos', [AgentMemoController::class, 'index']);
    Route::post('/customers/{customerId}/memos', [AgentMemoController::class, 'store']);
    Route::delete('/customers/{customerId}/memos/{memoId}', [AgentMemoController::class, 'destroy']);

    // Consultations
    Route::get('/consultations', [AgentConsultationController::class, 'index']);
    Route::put('/consultations/{id}/respond', [AgentConsultationController::class, 'respond']);

    // Claims (조회 only - 생성은 Customer가 수행)
    Route::get('/claims', [AgentClaimController::class, 'index']);
    Route::get('/claims/{id}', [AgentClaimController::class, 'show']);

    // Schedules
    Route::apiResource('schedules', AgentScheduleController::class);
    Route::get('/schedules/month/{year}/{month}', [AgentScheduleController::class, 'monthSummary']);

    // Statistics
    Route::get('/statistics/current', [AgentStatisticsController::class, 'current']);
    Route::get('/statistics/trend', [AgentStatisticsController::class, 'trend']);

    // DB Distribution
    Route::get('/db-distributions', [AgentDbDistributionController::class, 'index']);
    Route::put('/db-distributions/{id}/process', [AgentDbDistributionController::class, 'process']);

    // Messages
    Route::post('/messages/send', [AgentMessageController::class, 'send']);
    Route::get('/messages', [AgentMessageController::class, 'index']);
    Route::get('/messages/templates', [AgentMessageController::class, 'templates']);

    // Notifications
    Route::get('/notifications', [AgentNotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [AgentNotificationController::class, 'markRead']);

    // Satisfaction Surveys
    Route::apiResource('satisfaction-surveys', AgentSatisfactionController::class)
        ->only(['index', 'store']);

    // Disclosure Obligations
    Route::get('/disclosure-obligations', [AgentObligationController::class, 'index']);
});
```

### 2-2. 인증 방식

기존 `AuthController@login`이 이미 role 기반으로 동작:
- Agent 로그인: `POST /api/auth/login` with `{ username, password }` → Account.role === 'AGENT' 확인
- Sanctum 토큰 발행 → 클라이언트에서 `agentToken`으로 저장
- 모든 Agent API는 `auth:sanctum` + `role:AGENT` 미들웨어 적용

---

## 3. 컨트롤러 구현 계획

### 디렉토리: `app/Http/Controllers/Api/Agent/`

### 3-1. AgentDashboardController

```
GET /api/agent/dashboard
```

**Response:**
```json
{
  "data": {
    "today_schedules": 3,
    "pending_consultations": 5,
    "expiring_contracts": 2,
    "unprocessed_db": 4,
    "obligation_alerts": 1,
    "unread_notifications": 7
  }
}
```

**구현 로직:**
- `$agentId = auth()->user()->agent->agent_id`
- 각 카운트는 별도 쿼리 또는 서브쿼리로 집계
- 오늘 일정: `Schedule::where('agent_id', $agentId)->whereDate('schedule_date', today())->count()`
- 미처리 상담: `Consultation::where('agent_id', $agentId)->where('status', 'pending')->count()`
- 만기 임박: `Contract::where('agent_id', $agentId)->whereBetween('expiry_date', [now(), now()->addDays(30)])->count()`

### 3-2. AgentCustomerController

```
GET    /api/agent/customers          (목록 + 검색 + 필터)
POST   /api/agent/customers          (등록)
GET    /api/agent/customers/{id}     (상세)
PUT    /api/agent/customers/{id}     (수정)
DELETE /api/agent/customers/{id}     (삭제)
GET    /api/agent/customers/{id}/contracts (계약 목록)
```

**핵심 로직:**
- 모든 쿼리에 `->where('agent_id', $agentId)` 스코핑
- 검색: `where(name LIKE ? OR phone LIKE ?)`
- 필터: `customer_tag` (VIP/신규/관심/일반)
- 정렬: `name` / `last_contact_date` / `created_at`
- 페이지네이션: `paginate($perPage)`

**Validation:**
```php
'name' => 'required|string|max:50',
'phone' => 'required|string|regex:/^01[016789]-?\d{3,4}-?\d{4}$/',
'telecom' => 'nullable|in:SKT,KT,LGU+,알뜰폰',
'resident_number' => 'nullable|string|max:14',
'address' => 'nullable|string|max:200',
'occupation' => 'nullable|string|max:50',
'acquisition_source' => 'nullable|string|max:100',
'customer_tag' => 'nullable|in:VIP,신규,관심,일반',
```

### 3-3. AgentConsultationController

```
GET /api/agent/consultations          (목록)
PUT /api/agent/consultations/{id}/respond (응답)
```

**목록 필터:** `status` (pending/in_progress/completed)
**응답 로직:**
```php
$consultation->update([
    'status' => 'completed',
    'response' => $request->response,
    'responded_at' => now(),
]);
```

### 3-4. AgentClaimController

```
GET /api/agent/claims          (목록 - 담당 고객의 청구 건)
GET /api/agent/claims/{id}     (상세)
```

**조회 범위:** Agent가 담당하는 고객의 보험금 청구 건
```php
InsuranceClaim::whereHas('customer', function ($q) use ($agentId) {
    $q->where('agent_id', $agentId);
})->with(['customer', 'insuranceCompany'])
```

### 3-5. AgentScheduleController

```
CRUD /api/agent/schedules
GET  /api/agent/schedules/month/{year}/{month}
```

**monthSummary Response:**
```json
{
  "data": {
    "2026-02-01": [{ "schedule_id": 1, "title": "...", ... }],
    "2026-02-05": [{ "schedule_id": 2, ... }]
  }
}
```

**Validation:**
```php
'title' => 'required|string|max:100',
'schedule_date' => 'required|date',
'start_time' => 'required|date_format:H:i',
'end_time' => 'nullable|date_format:H:i|after:start_time',
'schedule_type' => 'required|in:meeting,call,visit,other',
'customer_id' => 'nullable|exists:customer,customer_id',
'memo' => 'nullable|string|max:500',
```

### 3-6. AgentStatisticsController

```
GET /api/agent/statistics/current    (이번 달 실적)
GET /api/agent/statistics/trend      (월별 추이)
```

**current:** Performance 테이블에서 현재 연/월 조회
**trend:** 최근 6개월 Performance 데이터 조회, 연/월 내림차순

### 3-7. AgentDbDistributionController

```
GET /api/agent/db-distributions           (배분 목록)
PUT /api/agent/db-distributions/{id}/process (처리)
```

**process 로직:**
```php
$assignment->update([
    'status' => $request->status, // 'processing' or 'completed'
    'memo' => $request->memo,
    'processed_at' => $request->status === 'completed' ? now() : null,
]);
```

### 3-8. AgentMessageController

```
POST /api/agent/messages/send       (발송)
GET  /api/agent/messages             (발송 이력)
GET  /api/agent/messages/templates   (카카오 템플릿 목록)
```

**발송 유형:**
- `sms`: 문자 발송 (외부 SMS API 연동 필요)
- `kakao`: 카카오 알림톡 (정해진 템플릿만, 외부 API 연동)
- `push`: PUSH 알림 (FCM 등 연동)

> 초기 구현: Message 레코드 생성 + status='sent' (실제 발송은 외부 API 연동 시 구현)

### 3-9. AgentNotificationController

```
GET /api/agent/notifications          (목록)
PUT /api/agent/notifications/{id}/read (읽음 처리)
```

### 3-10. AgentSatisfactionController

```
GET  /api/agent/satisfaction-surveys   (목록)
POST /api/agent/satisfaction-surveys   (발송)
```

**발송 로직:**
- SatisfactionSurvey 레코드 생성 (status: 'sent')
- 고객에게 설문 링크 발송 (Message 시스템 연동)

### 3-11. AgentObligationController

```
GET /api/agent/disclosure-obligations  (목록)
```

**필터:** `urgency` 파라미터 (imminent/upcoming/normal)
- imminent: D-Day (당일)
- upcoming: 7일 이내
- normal: 30일 이내

**정렬:** `days_remaining` ASC (긴급한 것 우선)

### 3-12. AgentMemoController

```
GET    /api/agent/customers/{customerId}/memos
POST   /api/agent/customers/{customerId}/memos
DELETE /api/agent/customers/{customerId}/memos/{memoId}
```

**소유권 검증:** 해당 customer가 현재 agent의 고객인지 확인

---

## 4. 공통 패턴

### 4-1. Agent ID 획득

```php
// 모든 Agent 컨트롤러의 기본 패턴
private function getAgentId(): string
{
    return auth()->user()->agent->agent_id;
}
```

### 4-2. 스코핑 패턴

```php
// 모든 쿼리에 agent_id 스코핑 적용
$query = Model::where('agent_id', $this->getAgentId());
```

### 4-3. 페이지네이션 패턴

```php
$perPage = $request->get('per_page', 15);
$result = $query->paginate($perPage);
return response()->json($result);
```

### 4-4. 에러 응답 패턴

```php
return response()->json(['message' => '에러 메시지'], 404);
```

---

## 5. 구현 순서 (권장)

### Phase 1: 기반
1. Agent 모델 생성 + Account relationship 추가
2. Schedule 마이그레이션 생성
3. `routes/api.php`에 agent prefix 그룹 추가
4. AgentDashboardController (profile + dashboard)

### Phase 2: 핵심 CRM
5. Customer 모델에 agent_id 관계 추가 (이미 존재 시 확인)
6. AgentCustomerController (CRUD + 검색/필터)
7. AgentMemoController
8. Contract 모델 + AgentCustomerController@contracts

### Phase 3: 일상 업무
9. Consultation 모델 + AgentConsultationController
10. AgentClaimController (조회)
11. Schedule 모델 + AgentScheduleController

### Phase 4: 부가 기능
12. Performance 모델 + AgentStatisticsController
13. CustomerAssignment 모델 + AgentDbDistributionController
14. Message 모델 + AgentMessageController
15. Notification 모델 + AgentNotificationController

### Phase 5: 추가 기능
16. DisclosureObligation 모델 + AgentObligationController
17. SatisfactionSurvey 모델 + AgentSatisfactionController

---

## 6. 외부 서비스 연동 (향후)

| 서비스 | 용도 | 우선순위 |
|--------|------|----------|
| SMS API | 문자 발송 | 높음 |
| 카카오 알림톡 API | 카카오 메시지 | 높음 |
| FCM/APNs | PUSH 알림 | 중간 |
| 캘린더 동기화 | Google/Apple 캘린더 | 낮음 |

---

## 7. 보안 고려사항

- **Agent 스코핑 필수**: 모든 데이터 접근은 `agent_id`로 스코핑. 다른 Agent의 데이터 접근 방지
- **Customer 소유권 검증**: 고객 관련 API에서 해당 고객이 현재 Agent 소속인지 반드시 확인
- **Rate Limiting**: 메시지 발송 API에 rate limit 적용 (예: 분당 10건)
- **입력 검증**: 모든 입력값 validation (주민등록번호 등 민감 정보 마스킹 처리)
- **Sanctum 토큰**: 기존 패턴 유지, 토큰 만료 정책 적용

---

## 8. 테스트 계획

### Unit Tests
- 각 컨트롤러의 CRUD 동작
- Agent 스코핑 정상 동작 (다른 Agent 데이터 접근 불가)
- Validation rule 검증

### Integration Tests
- 로그인 → 토큰 발급 → API 호출 전체 플로우
- 대시보드 집계 정확성
- 상담 응답 → 상태 변경 플로우
- DB배분 처리 플로우

### 기존 테스트 영향 없음 확인
- Customer 앱 API 정상 동작
- Admin 앱 API 정상 동작
