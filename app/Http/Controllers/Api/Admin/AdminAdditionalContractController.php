<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Contract;
use App\Models\InsuranceClaim;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminAdditionalContractController extends Controller
{
    /**
     * 추가계약 발굴 목록 조회 (SFR-040, 041)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->get('type', 'unclaimed');
        $perPage = min(max((int) $request->get('per_page', 15), 1), 100);

        $result = match ($type) {
            'unclaimed' => $this->getUnclaimedCustomers($perPage),
            'renewal' => $this->getRenewalCustomers($perPage),
            'undercovered' => $this->getUndercoveredCustomers($perPage),
            'abnormal' => $this->getAbnormalCustomers($perPage),
            default => $this->getUnclaimedCustomers($perPage),
        };

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 미청구 고객: 계약이 있지만 최근 6개월 내 청구 이력 없는 고객
     */
    private function getUnclaimedCustomers(int $perPage)
    {
        $customers = Customer::where('is_active', true)
            ->whereHas('contracts')
            ->whereDoesntHave('insuranceClaims', function ($q) {
                $q->where('created_at', '>=', Carbon::now()->subMonths(6));
            })
            ->with('agent:agent_id,name')
            ->paginate($perPage);

        $customers->getCollection()->transform(function ($customer) {
            return $this->mapCustomerResult($customer, '최근 6개월 내 청구 이력 없음');
        });

        return $customers;
    }

    /**
     * 갱신대상 고객: 계약 만기 3개월 이내 도래 고객
     */
    private function getRenewalCustomers(int $perPage)
    {
        $threeMonthsLater = Carbon::now()->addMonths(3);

        $customers = Customer::where('is_active', true)
            ->whereHas('contracts', function ($q) use ($threeMonthsLater) {
                $q->whereRaw('DATE_ADD(contract_date, INTERVAL 1 YEAR) <= ?', [$threeMonthsLater->toDateString()])
                  ->where('contract_status', '!=', 'cancelled');
            })
            ->with('agent:agent_id,name')
            ->paginate($perPage);

        $customers->getCollection()->transform(function ($customer) {
            return $this->mapCustomerResult($customer, '계약 만기 임박');
        });

        return $customers;
    }

    /**
     * 보장부족 고객: 총 진료비가 총 계약금액보다 큰 고객
     */
    private function getUndercoveredCustomers(int $perPage)
    {
        $customers = Customer::where('is_active', true)
            ->whereHas('medicalRecords')
            ->whereHas('contracts')
            ->with('agent:agent_id,name')
            ->withSum('medicalRecords', 'medical_cost')
            ->withSum('contracts', 'contract_amount')
            ->havingRaw('medical_records_sum_medical_cost > contracts_sum_contract_amount')
            ->paginate($perPage);

        $customers->getCollection()->transform(function ($customer) {
            return $this->mapCustomerResult($customer, '보장금액 대비 진료비 초과');
        });

        return $customers;
    }

    /**
     * 검진이상 고객: 중요 진료 기록이 있는 고객
     */
    private function getAbnormalCustomers(int $perPage)
    {
        $customers = Customer::where('is_active', true)
            ->whereHas('medicalRecords', function ($q) {
                $q->where('is_important', true);
            })
            ->with('agent:agent_id,name')
            ->paginate($perPage);

        $customers->getCollection()->transform(function ($customer) {
            return $this->mapCustomerResult($customer, '중요 진료 기록 존재');
        });

        return $customers;
    }

    /**
     * 고객 데이터를 추가계약 발굴 결과 형식으로 변환
     */
    private function mapCustomerResult(Customer $customer, string $reason): array
    {
        return [
            'customer_id' => $customer->customer_id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'agent_name' => $customer->agent?->name,
            'agent_id' => $customer->agent_id,
            'reason' => $reason,
        ];
    }
}
