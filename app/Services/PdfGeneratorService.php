<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfGeneratorService
{
    protected ClaimGeneratorService $claimGenerator;

    public function __construct(ClaimGeneratorService $claimGenerator)
    {
        $this->claimGenerator = $claimGenerator;
    }

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
            $imageBase64 = 'data:image/jpeg;base64,' . base64_encode($imageData['binary']);
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

        $imageBase64 = 'data:image/jpeg;base64,' . base64_encode($imageBinaries[0]['binary']);
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
     * 청구서 이미지 + 첨부파일 이미지를 하나의 PDF로 병합 (바이너리 return)
     */
    public function mergeClaimWithAttachments(InsuranceClaim $claim): string
    {
        // 1. 청구서 이미지 재생성
        $claimImages = $this->claimGenerator->generateClaimImages($claim);

        $imagesHtml = '';

        // 2. 청구서 이미지 페이지 추가
        foreach ($claimImages as $index => $imageData) {
            $imageBase64 = 'data:image/jpeg;base64,' . base64_encode($imageData['binary']);
            $pageBreak = $index > 0 ? 'style="page-break-before: always;"' : '';
            $imagesHtml .= "<div {$pageBreak}><img src=\"{$imageBase64}\" class=\"claim-image\"></div>";
        }

        // 3. 첨부파일 이미지 추가 (각각 새 페이지)
        $claim->load('documents');

        foreach ($claim->documents as $doc) {
            $mimeType = $this->guessMimeType($doc->document_file_name);

            // 이미지 파일만 PDF에 추가 (PDF 첨부는 건너뜀)
            if (str_starts_with($mimeType, 'image/')) {
                try {
                    $fileContent = Storage::disk('s3')->get($doc->document_file_url);
                    $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                    $imagesHtml .= "<div style=\"page-break-before: always;\"><img src=\"{$base64}\" class=\"claim-image\"></div>";
                } catch (\Exception $e) {
                    // 첨부파일 로드 실패 시 건너뜀
                }
            }
        }

        $html = $this->generatePdfHtml($imagesHtml);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        return $pdf->output();
    }

    /**
     * 파일 확장자로 MIME 타입 추측
     */
    private function guessMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
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
