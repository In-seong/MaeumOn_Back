<?php

namespace App\Services;

use RuntimeException;

class PdfToImageService
{
    /**
     * PDF 파일을 페이지별 PNG 이미지로 변환
     *
     * @param string $pdfPath PDF 파일 경로
     * @param int $dpi 해상도 (기본 200)
     * @return array{tempDir: string, images: string[]} 임시 디렉터리 경로와 페이지 순서대로 정렬된 PNG 파일 경로 배열
     */
    public function convertToImages(string $pdfPath, int $dpi = 200): array
    {
        if (!file_exists($pdfPath)) {
            throw new RuntimeException('PDF 파일을 찾을 수 없습니다.');
        }

        $tempDir = sys_get_temp_dir() . '/pdf_convert_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            throw new RuntimeException('임시 디렉터리 생성에 실패했습니다.');
        }

        $prefix = $tempDir . '/page';

        // pdftoppm -png -r {dpi} input.pdf output_prefix
        // 결과: page-1.png, page-2.png, ... (페이지 순서대로)
        $command = sprintf(
            'pdftoppm -png -r %d %s %s 2>&1',
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->cleanup($tempDir);
            throw new RuntimeException('PDF 변환에 실패했습니다: ' . implode("\n", $output));
        }

        // page-1.png, page-2.png, ... 또는 page-01.png, page-02.png, ...
        $images = glob($tempDir . '/page-*.png');

        if (empty($images)) {
            $this->cleanup($tempDir);
            throw new RuntimeException('PDF에서 이미지를 추출할 수 없습니다.');
        }

        // 페이지 순서 보장 (page-1, page-2, ..., page-10 순서)
        usort($images, function ($a, $b) {
            return strnatcmp(basename($a), basename($b));
        });

        return [
            'tempDir' => $tempDir,
            'images' => $images,
        ];
    }

    /**
     * 임시 디렉터리 및 파일 정리
     */
    public function cleanup(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        $files = glob($tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($tempDir);
    }
}
