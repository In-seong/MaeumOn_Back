<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

class ClaimGeneratorService
{
    /**
     * 청구서 이미지 생성 (다중 페이지 지원)
     * 단일 페이지인 경우: 기존과 동일하게 단일 이미지 반환
     * 다중 페이지인 경우: 첫 번째 페이지 이미지 경로 반환, 전체 페이지는 별도 배열로 저장
     */
    public function generateClaimImage(InsuranceClaim $claim): string
    {
        $template = $claim->claimFormTemplate;
        $pages = $template->templatePages()->orderBy('page_number')->get();

        // 페이지가 없으면 구 방식 (단일 이미지)으로 처리
        if ($pages->isEmpty()) {
            return $this->generateSinglePageImage($claim, $template);
        }

        // 다중 페이지 이미지 생성
        $generatedImages = $this->generateMultiPageImages($claim, $pages);

        // 첫 번째 페이지 이미지 경로 반환 (하위 호환성)
        return $generatedImages[0] ?? '';
    }

    /**
     * 다중 페이지 이미지 생성
     */
    public function generateMultiPageImages(InsuranceClaim $claim, $pages = null): array
    {
        $template = $claim->claimFormTemplate;

        if ($pages === null) {
            $pages = $template->templatePages()->orderBy('page_number')->get();
        }

        $generatedImages = [];
        $fieldValues = $claim->fieldValues()->with('templateField')->get()->keyBy('template_field_id');

        foreach ($pages as $page) {
            if (!$page->page_image_path) {
                continue;
            }

            $templateImagePath = Storage::disk('public')->path($page->page_image_path);

            if (!file_exists($templateImagePath)) {
                continue;
            }

            // 페이지 이미지 로드
            $image = Image::read($templateImagePath);

            // 해당 페이지의 필드들만 처리
            foreach ($page->templateFields as $field) {
                $fieldValue = $fieldValues->get($field->id);

                if (!$fieldValue || !$fieldValue->field_value) {
                    continue;
                }

                $this->drawText(
                    $image,
                    $fieldValue->field_value,
                    $field->x_position,
                    $field->y_position,
                    $field->font_size,
                    $field->font_color
                );
            }

            // 이미지 저장
            $filename = 'claim_' . $claim->id . '_page_' . $page->page_number . '_' . time() . '.png';
            $outputPath = 'claims/' . $filename;
            $fullPath = Storage::disk('public')->path($outputPath);

            $image->save($fullPath);

            $generatedImages[] = $outputPath;
        }

        return $generatedImages;
    }

    /**
     * 단일 페이지 이미지 생성 (구 방식, 하위 호환성)
     */
    private function generateSinglePageImage(InsuranceClaim $claim, $template): string
    {
        if (!$template->template_image_path) {
            throw new \Exception('템플릿 이미지가 없습니다.');
        }

        $templateImagePath = Storage::disk('public')->path($template->template_image_path);

        if (!file_exists($templateImagePath)) {
            throw new \Exception('템플릿 이미지 파일을 찾을 수 없습니다.');
        }

        // 템플릿 이미지 로드
        $image = Image::read($templateImagePath);

        // 필드 값들을 이미지에 텍스트로 삽입
        foreach ($claim->fieldValues()->with('templateField')->get() as $fieldValue) {
            $field = $fieldValue->templateField;

            if (!$field || !$fieldValue->field_value) {
                continue;
            }

            $this->drawText(
                $image,
                $fieldValue->field_value,
                $field->x_position,
                $field->y_position,
                $field->font_size,
                $field->font_color
            );
        }

        // 이미지 저장
        $filename = 'claim_' . $claim->id . '_' . time() . '.png';
        $outputPath = 'claims/' . $filename;
        $fullPath = Storage::disk('public')->path($outputPath);

        $image->save($fullPath);

        return $outputPath;
    }

    /**
     * 전체 페이지 이미지 URL 목록 반환
     */
    public function getGeneratedImageUrls(InsuranceClaim $claim): array
    {
        $template = $claim->claimFormTemplate;
        $pages = $template->templatePages()->orderBy('page_number')->get();

        if ($pages->isEmpty()) {
            // 단일 페이지
            if ($claim->generated_image_path) {
                return [asset('storage/' . $claim->generated_image_path)];
            }
            return [];
        }

        // 다중 페이지: claim_id_page_N 패턴으로 파일 검색
        $urls = [];
        $claimDir = Storage::disk('public')->path('claims');
        $pattern = 'claim_' . $claim->id . '_page_*';

        foreach (glob($claimDir . '/' . $pattern) as $file) {
            $urls[] = asset('storage/claims/' . basename($file));
        }

        // 페이지 번호로 정렬
        usort($urls, function ($a, $b) {
            preg_match('/page_(\d+)/', $a, $matchA);
            preg_match('/page_(\d+)/', $b, $matchB);
            return ($matchA[1] ?? 0) <=> ($matchB[1] ?? 0);
        });

        return $urls;
    }

    /**
     * 이미지에 텍스트 그리기
     */
    private function drawText($image, string $text, int $x, int $y, int $fontSize, string $color): void
    {
        // 폰트 파일 경로 (한글 폰트 필요)
        $fontPath = public_path('fonts/NanumGothic.ttf');

        // 폰트 파일이 없으면 기본 폰트 사용
        if (!file_exists($fontPath)) {
            // 기본 시스템 폰트 사용 시도
            $fontPath = null;
        }

        $image->text($text, $x, $y, function (FontFactory $font) use ($fontSize, $color, $fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size($fontSize);
            $font->color($color);
            $font->align('left');
            $font->valign('top');
        });
    }

    /**
     * 필드 값 포맷팅 (필드 타입에 따라)
     */
    public function formatFieldValue(string $fieldType, ?string $value): string
    {
        if (!$value) {
            return '';
        }

        return match ($fieldType) {
            'date' => $this->formatDate($value),
            'phone' => $this->formatPhone($value),
            'resident_number' => $this->formatResidentNumber($value),
            'number' => number_format((float) $value),
            default => $value,
        };
    }

    private function formatDate(string $value): string
    {
        try {
            $date = new \DateTime($value);
            return $date->format('Y년 m월 d일');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function formatPhone(string $value): string
    {
        $numbers = preg_replace('/[^0-9]/', '', $value);

        if (strlen($numbers) === 11) {
            return substr($numbers, 0, 3) . '-' . substr($numbers, 3, 4) . '-' . substr($numbers, 7, 4);
        } elseif (strlen($numbers) === 10) {
            return substr($numbers, 0, 3) . '-' . substr($numbers, 3, 3) . '-' . substr($numbers, 6, 4);
        }

        return $value;
    }

    private function formatResidentNumber(string $value): string
    {
        $numbers = preg_replace('/[^0-9]/', '', $value);

        if (strlen($numbers) === 13) {
            return substr($numbers, 0, 6) . '-' . substr($numbers, 6, 7);
        }

        return $value;
    }
}
