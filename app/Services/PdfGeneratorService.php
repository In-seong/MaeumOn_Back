<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    /**
     * 청구서 PDF 생성 (이미지 바이너리를 직접 받아 PDF 생성 → S3 저장)
     *
     * @param array<array{page_number: int, binary: string}> $imageBinaries
     */
    public function generateClaimPdf(InsuranceClaim $claim, array $imageBinaries): string
    {
        if (count($imageBinaries) > 1) {
            return $this->generateMultiPagePdf($claim, $imageBinaries);
        }

        return $this->generateSinglePagePdf($claim, $imageBinaries);
    }

    /**
     * 다중 페이지 PDF 생성
     *
     * @param array<array{page_number: int, binary: string}> $imageBinaries
     */
    private function generateMultiPagePdf(InsuranceClaim $claim, array $imageBinaries): string
    {
        $imagesHtml = '';

        foreach ($imageBinaries as $index => $imageData) {
            $imageBase64 = 'data:image/png;base64,' . base64_encode($imageData['binary']);
            $pageBreak = $index > 0 ? 'style="page-break-before: always;"' : '';

            $imagesHtml .= "<div {$pageBreak}><img src=\"{$imageBase64}\" class=\"claim-image\"></div>";
        }

        $html = $this->generatePdfHtml($imagesHtml);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $s3Key = 'claims/' . $claim->claim_id . '/document.pdf';
        Storage::disk('s3')->put($s3Key, $pdf->output());

        return $s3Key;
    }

    /**
     * 단일 페이지 PDF 생성
     *
     * @param array<array{page_number: int, binary: string}> $imageBinaries
     */
    private function generateSinglePagePdf(InsuranceClaim $claim, array $imageBinaries): string
    {
        if (empty($imageBinaries)) {
            throw new \Exception('청구서 이미지 바이너리가 없습니다.');
        }

        $imageBase64 = 'data:image/png;base64,' . base64_encode($imageBinaries[0]['binary']);
        $imagesHtml = "<div><img src=\"{$imageBase64}\" class=\"claim-image\"></div>";

        $html = $this->generatePdfHtml($imagesHtml);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        $s3Key = 'claims/' . $claim->claim_id . '/document.pdf';
        Storage::disk('s3')->put($s3Key, $pdf->output());

        return $s3Key;
    }

    /**
     * PDF용 HTML 생성 (이미지만, 한글 텍스트 없음)
     */
    private function generatePdfHtml(string $imagesHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
        }
        .claim-image {
            width: 100%;
            height: auto;
            display: block;
        }
    </style>
</head>
<body>
{$imagesHtml}
</body>
</html>
HTML;
    }
}
