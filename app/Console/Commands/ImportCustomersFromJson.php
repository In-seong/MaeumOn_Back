<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class ImportCustomersFromJson extends Command
{
    protected $signature = 'import:customers {file : JSON 파일 경로} {--dry-run : 실제 INSERT 없이 시뮬레이션만}';
    protected $description = 'CHAMS 고객 JSON 데이터를 MaeumOn DB로 import';

    private array $agentMap = [];
    private int $lastSeq = 0;

    private array $stats = [
        'total' => 0,
        'inserted' => 0,
        'skipped_no_name' => 0,
        'agent_mapped' => 0,
        'agent_null' => 0,
        'gender_unknown' => 0,
        'memo_count' => 0,
    ];
    private array $unmappedAgents = [];

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("파일 없음: {$file}");
            return 1;
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->error('JSON 파싱 실패');
            return 1;
        }

        $this->stats['total'] = count($data);
        $this->info("총 {$this->stats['total']}건 로드 완료");

        if ($dryRun) {
            $this->warn('>> DRY-RUN 모드 (실제 DB 변경 없음)');
        }

        $this->prepareAgentMap();
        $this->prepareLastSeq();

        $this->info("설계사 매핑: " . count($this->agentMap) . "명");
        $this->info("마지막 customer_id: C" . str_pad($this->lastSeq, 7, '0', STR_PAD_LEFT));
        $this->newLine();

        if ($dryRun) {
            $this->runDry($data);
        } else {
            $this->runInsert($data);
        }

        $this->printReport();
        return 0;
    }

    private function prepareAgentMap(): void
    {
        $agents = DB::table('agent')
            ->select('agent_id', 'name')
            ->where('is_active', 1)
            ->get();

        foreach ($agents as $agent) {
            $this->agentMap[$agent->name] = $agent->agent_id;
        }
    }

    private function prepareLastSeq(): void
    {
        $last = DB::table('customer')
            ->orderBy('customer_id', 'desc')
            ->value('customer_id');

        $this->lastSeq = $last ? (int) substr($last, 1) : 0;
    }

    private function nextCustomerId(): string
    {
        $this->lastSeq++;
        return 'C' . str_pad($this->lastSeq, 7, '0', STR_PAD_LEFT);
    }

    private function parseRow(array $row): ?array
    {
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            $this->stats['skipped_no_name']++;
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $row['phone']['phone'] ?? '');

        // 주민번호 → 생년월일, 성별
        $rn = $row['regist_number'] ?? '';
        $birthDate = null;
        $gender = null;

        if (strlen($rn) >= 7) {
            $yy = substr($rn, 0, 2);
            $mm = substr($rn, 2, 2);
            $dd = substr($rn, 4, 2);
            $gd = substr($rn, 6, 1);
            $century = in_array($gd, ['1', '2']) ? '19' : '20';
            $birthDate = "{$century}{$yy}-{$mm}-{$dd}";
            $gender = in_array($gd, ['1', '3']) ? 'M' : 'F';
        } elseif (strlen($rn) === 6) {
            $yy = substr($rn, 0, 2);
            $mm = substr($rn, 2, 2);
            $dd = substr($rn, 4, 2);
            $birthDate = "19{$yy}-{$mm}-{$dd}";
            $this->stats['gender_unknown']++;
        }

        // 설계사 매핑
        $plannerName = $row['planner']['planner01']['planner_name'] ?? null;
        $agentId = null;
        if ($plannerName) {
            $agentId = $this->agentMap[$plannerName] ?? null;
            if ($agentId) {
                $this->stats['agent_mapped']++;
            } else {
                $this->stats['agent_null']++;
                $this->unmappedAgents[$plannerName] = ($this->unmappedAgents[$plannerName] ?? 0) + 1;
            }
        } else {
            $this->stats['agent_null']++;
        }

        // 주소
        $addr = $row['address'] ?? [];
        $address = trim(($addr['address'] ?? '') . ' ' . ($addr['extra_address'] ?? '')) ?: null;
        $detailedAddress = $addr['detail_address'] ?? null;

        // 병원명 → 취득경로
        $acquisitionChannel = !empty($row['hospital_info']['hospital'])
            ? $row['hospital_info']['hospital']
            : null;

        $customerId = $this->nextCustomerId();

        $customer = [
            'customer_id' => $customerId,
            'account_id' => null,
            'agent_id' => $agentId,
            'name' => $name,
            'resident_number' => $rn ?: null,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'phone' => $phone ?: null,
            'telecom' => $row['phone']['telecom'] ?? null,
            'address' => $address,
            'detailed_address' => $detailedAddress,
            'acquisition_channel' => $acquisitionChannel,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 메모
        $memos = [];
        $memoList = $row['memo'] ?? [];
        $memoDateList = $row['memoDate'] ?? [];
        foreach ($memoList as $mi => $content) {
            if (empty(trim($content))) continue;

            $memoDate = now()->toDateTimeString();
            if (isset($memoDateList[$mi]) && !empty($memoDateList[$mi])) {
                $raw = trim($memoDateList[$mi], '. ');
                $parts = preg_split('/\.\s*/', $raw);
                if (count($parts) === 3) {
                    $memoDate = sprintf('%04d-%02d-%02d 00:00:00', $parts[0], $parts[1], $parts[2]);
                }
            }

            $memos[] = [
                'customer_id' => $customerId,
                'author_id' => $agentId ?? 'D0000001',
                'author_type' => $agentId ? 'AGENT' : 'ADMIN',
                'title' => null,
                'content' => $content,
                'memo_date' => $memoDate,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return ['customer' => $customer, 'memos' => $memos];
    }

    private function runDry(array $data): void
    {
        foreach ($data as $row) {
            $result = $this->parseRow($row);
            if (!$result) continue;

            $this->stats['inserted']++;
            $this->stats['memo_count'] += count($result['memos']);
        }
    }

    private function runInsert(array $data): void
    {
        $customerBatch = [];
        $allMemos = [];

        DB::beginTransaction();
        try {
            // 1단계: customer 전부 INSERT
            foreach ($data as $i => $row) {
                $result = $this->parseRow($row);
                if (!$result) continue;

                $rn = $result['customer']['resident_number'];
                if ($rn) {
                    $result['customer']['resident_number'] = Crypt::encryptString($rn);
                }

                $customerBatch[] = $result['customer'];
                foreach ($result['memos'] as $m) {
                    $allMemos[] = $m;
                }

                $this->stats['inserted']++;
                $this->stats['memo_count'] += count($result['memos']);

                if (count($customerBatch) >= 500) {
                    DB::table('customer')->insert($customerBatch);
                    $this->info("  고객 INSERT... " . $this->stats['inserted'] . "건");
                    $customerBatch = [];
                }
            }
            if (!empty($customerBatch)) {
                DB::table('customer')->insert($customerBatch);
            }
            $this->info("고객 INSERT 완료: {$this->stats['inserted']}건");

            // 2단계: memo 전부 INSERT (customer FK 보장됨)
            $memoBatch = [];
            foreach ($allMemos as $j => $m) {
                $memoBatch[] = $m;
                if (count($memoBatch) >= 500) {
                    DB::table('memo')->insert($memoBatch);
                    $memoBatch = [];
                }
            }
            if (!empty($memoBatch)) {
                DB::table('memo')->insert($memoBatch);
            }
            $this->info("메모 INSERT 완료: {$this->stats['memo_count']}건");

            DB::commit();
            $this->info('DB INSERT 완료 (커밋됨)');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('INSERT 실패 → 롤백: ' . $e->getMessage());
        }
    }

    private function printReport(): void
    {
        $this->newLine();
        $this->info('==========================================');
        $this->info('  Import 결과 리포트');
        $this->info('==========================================');
        $this->table(
            ['항목', '건수'],
            [
                ['전체 JSON 건수', $this->stats['total']],
                ['INSERT 성공', $this->stats['inserted']],
                ['이름 없음 SKIP', $this->stats['skipped_no_name']],
                ['설계사 매핑 성공', $this->stats['agent_mapped']],
                ['설계사 NULL 처리', $this->stats['agent_null']],
                ['성별 판별 불가', $this->stats['gender_unknown']],
                ['메모 INSERT', $this->stats['memo_count']],
            ]
        );

        if (!empty($this->unmappedAgents)) {
            $this->newLine();
            $this->warn('--- 매핑 안 된 설계사 ---');
            foreach ($this->unmappedAgents as $name => $count) {
                $this->line("  - {$name} ({$count}건)");
            }
        }
    }
}
