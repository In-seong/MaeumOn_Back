<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class FixCustomerRrnData extends Command
{
    protected $signature = 'fix:customer-rrn {file : JSON 파일 경로} {--dry-run : 실제 UPDATE 없이 확인만}';
    protected $description = 'Import된 고객 데이터에 주민번호/성별/생년월일 보정';

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("파일 없음: {$file}");
            return 1;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            $this->error('JSON 파싱 실패');
            return 1;
        }

        $this->info("JSON 총 " . count($data) . "건 로드");

        if ($dryRun) {
            $this->warn('>> DRY-RUN 모드');
        }

        $updated = 0;
        $skipped = 0;
        $noRrn = 0;
        $genderUnknown = 0;

        $importedIds = DB::table('customer')
            ->where('customer_id', '>', 'C0000033')
            ->orderBy('customer_id')
            ->pluck('customer_id')
            ->toArray();

        $this->info("DB import 데이터: " . count($importedIds) . "건");

        $jsonIdx = 0;
        $dbIdx = 0;

        foreach ($data as $row) {
            $name = trim($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if ($dbIdx >= count($importedIds)) {
                break;
            }

            $customerId = $importedIds[$dbIdx];
            $dbIdx++;

            $rn = trim($row['regist_number'] ?? '');
            if ($rn === '') {
                $noRrn++;
                continue;
            }

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
                $genderUnknown++;
            }

            $updateData = [
                'resident_number' => Crypt::encryptString($rn),
                'birth_date' => $birthDate,
                'updated_at' => now(),
            ];
            if ($gender) {
                $updateData['gender'] = $gender;
            }

            if (!$dryRun) {
                DB::table('customer')
                    ->where('customer_id', $customerId)
                    ->update($updateData);
            }

            $updated++;

            if ($updated % 500 === 0) {
                $this->info("  처리 중... {$updated}건");
            }
        }

        $this->newLine();
        $this->info('==========================================');
        $this->info('  보정 결과');
        $this->info('==========================================');
        $this->table(
            ['항목', '건수'],
            [
                ['UPDATE 대상', $updated],
                ['주민번호 없음', $noRrn],
                ['성별 판별 불가 (6자리)', $genderUnknown],
            ]
        );

        return 0;
    }
}
