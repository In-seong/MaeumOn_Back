<?php

namespace App\Console\Commands;

use App\Services\DistributionService;
use Illuminate\Console\Command;

class CheckDistributionTimeout extends Command
{
    protected $signature = 'distribution:check-timeout';
    protected $description = '배분 후 미확인 건을 다음 설계사에게 재배분';

    public function handle(DistributionService $service): int
    {
        $processed = $service->processTimeouts();

        if ($processed > 0) {
            $this->info("{$processed}건 타임아웃 재배분 완료");
        }

        return self::SUCCESS;
    }
}
