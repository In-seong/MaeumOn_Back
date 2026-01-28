<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    /**
     * 청구서 PDF 생성 (다중 페이지 지원)
     */
    public function generateClaimPdf(InsuranceClaim $claim): string
    {
        $template = $claim->claimFormTemplate;
        $pages = $template->templatePages()->orderBy('page_number')->get();
        $claimGenerator = new ClaimGeneratorService();

        // 다중 페이지 처리
        if ($pages->count() > 0) {
            // 이미지가 없으면 생성
            if (!$claim->generated_image_path) {
                $generatedImages = $claimGenerator->generateMultiPageImages($claim, $pages);
                if (!empty($generatedImages)) {
                    $claim->update(['generated_image_path' => $generatedImages[0]]);
                }
            }

            return $this->generateMultiPagePdf($claim, $pages, $claimGenerator);
        }

        // 단일 페이지 처리 (하위 호환성)
        if (!$claim->generated_image_path) {
            $imagePath = $claimGenerator->generateClaimImage($claim);
            $claim->update(['generated_image_path' => $imagePath]);
        }

        return $this->generateSinglePagePdf($claim);
    }

    /**
     * 다중 페이지 PDF 생성
     */
    private function generateMultiPagePdf(InsuranceClaim $claim, $pages, ClaimGeneratorService $claimGenerator): string
    {
        $imagesHtml = '';
        $claimDir = Storage::disk('public')->path('claims');

        foreach ($pages as $index => $page) {
            // 해당 페이지의 이미지 파일 찾기
            $pattern = 'claim_' . $claim->id . '_page_' . $page->page_number . '_*.png';
            $files = glob($claimDir . '/' . $pattern);

            if (empty($files)) {
                continue;
            }

            // 가장 최신 파일 사용
            $imageFullPath = end($files);

            if (!file_exists($imageFullPath)) {
                continue;
            }

            // 이미지를 base64로 인코딩
            $imageData = base64_encode(file_get_contents($imageFullPath));
            $imageMime = mime_content_type($imageFullPath);
            $imageBase64 = "data:{$imageMime};base64,{$imageData}";

            // 첫 페이지가 아니면 페이지 나누기 추가
            $pageBreak = $index > 0 ? '<div style="page-break-before: always;"></div>' : '';

            $imagesHtml .= <<<HTML
{$pageBreak}
<div class="page-container">
    <div class="page-number">페이지 {$page->page_number} / {$pages->count()}</div>
    <img src="{$imageBase64}" class="claim-image" alt="보험 청구서 페이지 {$page->page_number}">
</div>
HTML;
        }

        $html = $this->generateMultiPagePdfHtml($claim, $imagesHtml);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        // PDF 저장
        $filename = 'claim_' . $claim->id . '_' . time() . '.pdf';
        $outputPath = 'claims/' . $filename;
        $fullPath = Storage::disk('public')->path($outputPath);

        $pdf->save($fullPath);

        return $outputPath;
    }

    /**
     * 단일 페이지 PDF 생성 (구 방식)
     */
    private function generateSinglePagePdf(InsuranceClaim $claim): string
    {
        $imageFullPath = Storage::disk('public')->path($claim->generated_image_path);

        if (!file_exists($imageFullPath)) {
            throw new \Exception('청구서 이미지 파일을 찾을 수 없습니다.');
        }

        // 이미지를 base64로 인코딩
        $imageData = base64_encode(file_get_contents($imageFullPath));
        $imageMime = mime_content_type($imageFullPath);
        $imageBase64 = "data:{$imageMime};base64,{$imageData}";

        // HTML 템플릿으로 PDF 생성
        $html = $this->generatePdfHtml($claim, $imageBase64);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        // PDF 저장
        $filename = 'claim_' . $claim->id . '_' . time() . '.pdf';
        $outputPath = 'claims/' . $filename;
        $fullPath = Storage::disk('public')->path($outputPath);

        $pdf->save($fullPath);

        return $outputPath;
    }

    /**
     * 다중 페이지 PDF용 HTML 생성
     */
    private function generateMultiPagePdfHtml(InsuranceClaim $claim, string $imagesHtml): string
    {
        $template = $claim->claimFormTemplate;
        $insuranceCompany = $template->insuranceCompany;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 10mm;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'NanumGothic', sans-serif;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            font-size: 12px;
            color: #666;
        }
        .page-container {
            position: relative;
        }
        .page-number {
            text-align: right;
            font-size: 10px;
            color: #999;
            margin-bottom: 5px;
        }
        .claim-image {
            width: 100%;
            max-width: 100%;
            height: auto;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        {$insuranceCompany->name} - {$template->name}
    </div>
    {$imagesHtml}
    <div class="footer">
        생성일시: {$claim->created_at->format('Y-m-d H:i:s')} | 청구번호: {$claim->id}
    </div>
</body>
</html>
HTML;
    }

    /**
     * 단일 페이지 PDF용 HTML 생성
     */
    private function generatePdfHtml(InsuranceClaim $claim, string $imageBase64): string
    {
        $template = $claim->claimFormTemplate;
        $insuranceCompany = $template->insuranceCompany;

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 10mm;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'NanumGothic', sans-serif;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            font-size: 12px;
            color: #666;
        }
        .claim-image {
            width: 100%;
            max-width: 100%;
            height: auto;
        }
        .footer {
            text-align: center;
            margin-top: 10px;
            font-size: 10px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        {$insuranceCompany->name} - {$template->name}
    </div>
    <div class="content">
        <img src="{$imageBase64}" class="claim-image" alt="보험 청구서">
    </div>
    <div class="footer">
        생성일시: {$claim->created_at->format('Y-m-d H:i:s')} | 청구번호: {$claim->id}
    </div>
</body>
</html>
HTML;
    }
}
