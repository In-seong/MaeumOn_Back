<?php

namespace App\Console\Commands;

use App\Models\FcMetaTran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupFaxFiles extends Command
{
    protected $signature = 'fax:cleanup-files';
    protected $description = '팩스 발송 완료 건의 SendDoc 파일을 정리합니다';

    public function handle(): int
    {
        $sendDocPath = config('webfax.send_doc_path');

        if (!$sendDocPath || !is_dir($sendDocPath)) {
            $this->warn("SendDoc 경로가 존재하지 않습니다: {$sendDocPath}");
            return self::SUCCESS;
        }

        // TR_SENDSTAT != '0' AND TR_SENDSTAT != '-' → FaxClientNC가 처리 완료한 건
        $completedMetas = FcMetaTran::where('tr_sendstat', '!=', '0')
            ->where('tr_sendstat', '!=', '-')
            ->whereNotNull('tr_docname')
            ->where('tr_docname', '!=', '')
            ->get();

        if ($completedMetas->isEmpty()) {
            $this->line('정리할 파일이 없습니다.');
            return self::SUCCESS;
        }

        $deleted = 0;
        $notFound = 0;

        foreach ($completedMetas as $meta) {
            $filePath = rtrim($sendDocPath, '/') . '/' . $meta->tr_docname;

            if (file_exists($filePath)) {
                if (@unlink($filePath)) {
                    $deleted++;
                    $this->line("삭제: {$meta->tr_docname} (batch: {$meta->tr_batchid})");
                } else {
                    $this->warn("삭제 실패: {$meta->tr_docname}");
                    Log::warning('FaxClientNC: SendDoc 파일 삭제 실패', [
                        'file' => $filePath,
                        'batch_id' => $meta->tr_batchid,
                    ]);
                }
            } else {
                $notFound++;
            }
        }

        $this->info("정리 완료: {$deleted}개 삭제, {$notFound}개 파일 없음");

        if ($deleted > 0) {
            Log::info('FaxClientNC: SendDoc 파일 정리', [
                'deleted' => $deleted,
                'not_found' => $notFound,
            ]);
        }

        return self::SUCCESS;
    }
}
