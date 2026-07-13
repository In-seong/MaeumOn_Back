<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupFaxFiles extends Command
{
    protected $signature = 'fax:cleanup-files';
    protected $description = '비즈모아샷 URL연동 방식에서는 로컬 팩스 파일이 없으므로 별도 정리가 불필요합니다';

    public function handle(): int
    {
        $this->info('비즈모아샷 URL연동 방식: 로컬 팩스 파일 없음, 정리 불필요');

        return self::SUCCESS;
    }
}
