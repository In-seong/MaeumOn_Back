<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ImportClaimFormsToDownloads extends Command
{
    protected $signature = 'import:claim-forms-to-downloads';
    protected $description = 'claim_form 페이지 이미지를 PDF로 합쳐서 form_downloads에 일괄 등록';

    public function handle(): int
    {
        $forms = DB::table('claim_form')
            ->join('insurance_company', 'claim_form.company_id', '=', 'insurance_company.company_id')
            ->where('claim_form.is_active', 1)
            ->whereIn('insurance_company.category', ['non-life', 'life', 'cooperative'])
            ->select(
                'claim_form.claim_form_id',
                'insurance_company.company_name',
                'insurance_company.category',
                'claim_form.form_name'
            )
            ->orderBy('insurance_company.category')
            ->orderBy('insurance_company.company_name')
            ->get();

        $this->info("총 {$forms->count()}개 보험사 처리 시작");

        $sortOrders = ['non-life' => 0, 'life' => 0, 'cooperative' => 0];

        foreach ($forms as $form) {
            $pages = DB::table('form_page')
                ->where('claim_form_id', $form->claim_form_id)
                ->orderBy('page_number')
                ->get();

            if ($pages->isEmpty()) {
                $this->warn("  [{$form->company_name}] 페이지 없음, 건너뜀");
                continue;
            }

            $this->info("  [{$form->company_name}] {$pages->count()}페이지 → PDF 생성 중...");

            // S3에서 이미지 다운로드 → base64 인코딩
            $imageDataList = [];
            foreach ($pages as $page) {
                try {
                    $content = Storage::disk('s3')->get($page->page_image_path);
                    $base64 = base64_encode($content);
                    $ext = pathinfo($page->page_image_path, PATHINFO_EXTENSION) ?: 'png';
                    $mime = $ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/png';
                    $imageDataList[] = [
                        'src' => "data:{$mime};base64,{$base64}",
                        'width' => $page->image_width,
                        'height' => $page->image_height,
                    ];
                } catch (\Throwable $e) {
                    $this->warn("    페이지 {$page->page_number} 다운로드 실패: {$e->getMessage()}");
                }
            }

            if (empty($imageDataList)) {
                $this->warn("  [{$form->company_name}] 유효한 이미지 없음, 건너뜀");
                continue;
            }

            // HTML 생성 → dompdf로 PDF 변환
            $html = '<html><head><style>';
            $html .= 'body { margin: 0; padding: 0; }';
            $html .= '.page { page-break-after: always; text-align: center; }';
            $html .= '.page:last-child { page-break-after: auto; }';
            $html .= 'img { max-width: 100%; height: auto; }';
            $html .= '</style></head><body>';

            foreach ($imageDataList as $img) {
                $html .= '<div class="page"><img src="' . $img['src'] . '"></div>';
            }
            $html .= '</body></html>';

            // A4 세로 기본
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $pdfContent = $pdf->output();

            // S3에 업로드
            $uniqueId = time() . '_' . uniqid();
            $s3Path = "form-downloads/claim_{$form->claim_form_id}_{$uniqueId}.pdf";
            Storage::disk('s3')->put($s3Path, $pdfContent);

            $fileName = "{$form->company_name} 청구서.pdf";

            $sortOrders[$form->category]++;

            // form_downloads에 INSERT
            DB::table('form_downloads')->insert([
                'company_name' => $form->company_name,
                'category' => $form->category,
                'claim_form_path' => $s3Path,
                'claim_form_name' => $fileName,
                'dental_confirm_path' => null,
                'dental_confirm_name' => null,
                'sort_order' => $sortOrders[$form->category],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info("    ✅ 등록 완료 → {$s3Path}");
        }

        $total = array_sum($sortOrders);
        $this->info("\n완료! 총 {$total}개 보험사 등록됨");

        return Command::SUCCESS;
    }
}
