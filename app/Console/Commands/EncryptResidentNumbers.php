<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptResidentNumbers extends Command
{
    protected $signature = 'customers:encrypt-rrn';
    protected $description = '기존 평문 주민등록번호를 암호화합니다 (1회성)';

    public function handle(): int
    {
        $rows = DB::table('customer')
            ->whereNotNull('resident_number')
            ->where('resident_number', '!=', '')
            ->get(['customer_id', 'resident_number']);

        $encrypted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            // 이미 암호화된 값인지 확인 (복호화 시도)
            try {
                Crypt::decryptString($row->resident_number);
                $skipped++;
                continue; // 이미 암호화됨
            } catch (\Exception $e) {
                // 복호화 실패 = 평문이므로 암호화 필요
            }

            $encryptedValue = Crypt::encryptString($row->resident_number);

            DB::table('customer')
                ->where('customer_id', $row->customer_id)
                ->update(['resident_number' => $encryptedValue]);

            $encrypted++;
        }

        $this->info("완료: {$encrypted}건 암호화, {$skipped}건 이미 암호화됨 (스킵)");

        return self::SUCCESS;
    }
}
