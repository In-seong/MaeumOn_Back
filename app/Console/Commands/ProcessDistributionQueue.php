<?php

namespace App\Console\Commands;

use App\Services\DistributionService;
use Illuminate\Console\Command;

class ProcessDistributionQueue extends Command
{
    protected $signature = 'distribution:process';
    protected $description = '자동배분 대기열에서 예정 시각이 지난 건을 설계사에게 배분';

    public function handle(DistributionService $service): int
    {
        $processed = $service->processPendingQueue();

        if ($processed > 0) {
            $this->info("{$processed}건 자동배분 완료");
        }

        return self::SUCCESS;
    }
}
