<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Typography\FontFactory;

class ClaimGeneratorService
{
    /**
     * 청구서 이미지 생성 (메모리 내 바이너리 배열 반환, S3 저장 안 함)
     *
     * @return array<array{page_number: int, binary: string}>
     */
    public function generateClaimImages(InsuranceClaim $claim): array
    {
        $claimForm = $claim->claimForm;
        $pages = $claimForm->formPages()->orderBy('page_number')->get();

        // 페이지가 없으면 구 방식 (단일 이미지)으로 처리
        if ($pages->isEmpty()) {
            return $this->generateSinglePageImageBinary($claim, $claimForm);
        }

        // 다중 페이지 이미지 바이너리 생성
        return $this->generateMultiPageImageBinaries($claim, $pages);
    }

    /**
     * 다중 페이지 이미지 바이너리 생성
     *
     * @return array<array{page_number: int, binary: string}>
     */
    private function generateMultiPageImageBinaries(InsuranceClaim $claim, $pages): array
    {
        $imageBinaries = [];
        $fieldValues = $claim->fieldValues()->with('formField')->get()->keyBy('form_field_id');

        foreach ($pages as $page) {
            if (!$page->page_image_path) {
                continue;
            }

            // S3에서 템플릿 이미지 읽기
            $templateImageBinary = Storage::disk('s3')->get($page->page_image_path);
            if (!$templateImageBinary) {
                continue;
            }

            // 바이너리에서 이미지 로드
            $image = Image::read($templateImageBinary);

            // 해당 페이지의 필드들만 처리
            foreach ($page->formFields as $field) {
                $fieldValue = $fieldValues->get($field->form_field_id);

                if (!$fieldValue || !$fieldValue->field_value) {
                    continue;
                }

                $formattedValue = $this->formatFieldValue($field->field_type, $fieldValue->field_value);

                $this->drawText(
                    $image,
                    $formattedValue,
                    $field->x_position,
                    $field->y_position,
                    $field->font_size,
                    $field->font_color
                );
            }

            // 팩스 발송 용량 제한(2MB) 대응: 적정 해상도로 리사이즈 + JPEG 압축
            $image = $this->resizeForFax($image);

            $imageBinaries[] = [
                'page_number' => $page->page_number,
                'binary' => $image->toJpeg(quality: 65)->toString(),
            ];
        }

        return $imageBinaries;
    }

    /**
     * 단일 페이지 이미지 바이너리 생성 (구 방식, 하위 호환성)
     *
     * @return array<array{page_number: int, binary: string}>
     */
    private function generateSinglePageImageBinary(InsuranceClaim $claim, $claimForm): array
    {
        if (!$claimForm->template_image_path) {
            throw new \Exception('템플릿 이미지가 없습니다.');
        }

        // S3에서 템플릿 이미지 읽기
        $templateImageBinary = Storage::disk('s3')->get($claimForm->template_image_path);
        if (!$templateImageBinary) {
            throw new \Exception('템플릿 이미지 파일을 찾을 수 없습니다.');
        }

        // 바이너리에서 이미지 로드
        $image = Image::read($templateImageBinary);

        // 필드 값들을 이미지에 텍스트로 삽입
        foreach ($claim->fieldValues()->with('formField')->get() as $fieldValue) {
            $field = $fieldValue->formField;

            if (!$field || !$fieldValue->field_value) {
                continue;
            }

            $formattedValue = $this->formatFieldValue($field->field_type, $fieldValue->field_value);

            $this->drawText(
                $image,
                $formattedValue,
                $field->x_position,
                $field->y_position,
                $field->font_size,
                $field->font_color
            );
        }

        $image = $this->resizeForFax($image);

        return [
            [
                'page_number' => 1,
                'binary' => $image->toJpeg(quality: 65)->toString(),
            ],
        ];
    }

    /**
     * 이미지에 텍스트 그리기
     *
     * font_size는 admin 에디터에서 CSS px 단위로 설정됨.
     * 고해상도 이미지가 A4 PDF에 축소되면 텍스트가 매우 작아지므로,
     * 이미지 너비 / A4 너비(595pt) 비율로 스케일업하여
     * PDF 출력 시 원래 의도한 크기로 보이도록 함.
     */
    private function drawText($image, string $text, int $x, int $y, int $fontSize, string $color): void
    {
        // A4 너비 595.28pt 기준으로 스케일 계산
        $imageWidth = $image->width();
        $a4WidthPt = 595.28;
        $scaleFactor = $imageWidth / $a4WidthPt;
        $scaledFontSize = max(1, (int) round($fontSize * $scaleFactor));

        // 폰트 파일 경로 (한글 폰트 필요)
        $fontPath = public_path('fonts/NanumGothic.ttf');

        // 폰트 파일이 없으면 기본 폰트 사용
        if (!file_exists($fontPath)) {
            $fontPath = null;
        }

        $image->text($text, $x, $y, function (FontFactory $font) use ($scaledFontSize, $color, $fontPath) {
            if ($fontPath) {
                $font->filename($fontPath);
            }
            $font->size($scaledFontSize);
            $font->color($color);
            $font->align('left');
            $font->valign('top');
        });
    }

    /**
     * 팩스 발송 용량 제한 대응: A4 200DPI(1654px) 이하로 리사이즈
     */
    private function resizeForFax($image)
    {
        $maxWidth = 2480; // A4 300DPI

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        return $image;
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
