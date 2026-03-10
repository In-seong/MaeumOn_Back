<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimForm;
use App\Models\FormPage;
use App\Models\FormField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormFieldController extends Controller
{
    /**
     * 양식의 필드 목록
     */
    public function index(int $templateId): JsonResponse
    {
        $template = ClaimForm::findOrFail($templateId);
        $fields = $template->formFields()->orderBy('field_order')->get();

        return response()->json([
            'success' => true,
            'data' => $fields,
        ]);
    }

    /**
     * 페이지의 필드 목록
     */
    public function indexByPage(int $pageId): JsonResponse
    {
        $page = FormPage::findOrFail($pageId);
        $fields = $page->formFields()->orderBy('field_order')->get();

        return response()->json([
            'success' => true,
            'data' => $fields,
        ]);
    }

    /**
     * 필드 추가
     */
    public function store(Request $request, int $templateId): JsonResponse
    {
        $template = ClaimForm::findOrFail($templateId);

        $validated = $request->validate([
            'form_page_id' => 'nullable|exists:form_page,form_page_id',
            'field_name' => 'required|string|max:50',
            'standard_field_code' => 'nullable|string|max:50',
            'field_label' => 'required|string|max:100',
            'field_type' => 'required|in:text,date,number,resident_number,resident_number_front,resident_number_back,phone,textarea,checkbox,radio,consent,signature',
            'x_position' => 'required|integer|min:0',
            'y_position' => 'required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'field_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'field_options' => 'nullable',
        ]);

        // 표준 필드 코드 유효성 검증
        if (!empty($validated['standard_field_code'])) {
            $standardFields = config('standard_fields', []);
            $validCodes = array_column($standardFields, 'code');
            if (!in_array($validated['standard_field_code'], $validCodes)) {
                return response()->json([
                    'success' => false,
                    'message' => '유효하지 않은 표준 필드 코드입니다.',
                ], 422);
            }

            // 같은 양식 내 동일 표준 필드 코드 중복 방지
            $exists = FormField::where('claim_form_id', $templateId)
                ->where('standard_field_code', $validated['standard_field_code'])
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => '이 양식에 이미 동일한 표준 필드가 존재합니다.',
                ], 422);
            }
        }

        $validated['claim_form_id'] = $templateId;

        // 페이지 ID가 없으면 첫 번째 페이지에 연결
        if (empty($validated['form_page_id'])) {
            $firstPage = $template->formPages()->first();
            if ($firstPage) {
                $validated['form_page_id'] = $firstPage->form_page_id;
            }
        }

        // field_order가 없으면 마지막으로
        if (!isset($validated['field_order'])) {
            $validated['field_order'] = $template->formFields()->max('field_order') + 1;
        }

        $field = FormField::create($validated);

        return response()->json([
            'success' => true,
            'data' => $field,
            'message' => '필드가 추가되었습니다.',
        ], 201);
    }

    /**
     * 특정 페이지에 필드 추가
     */
    public function storeToPage(Request $request, int $pageId): JsonResponse
    {
        $page = FormPage::findOrFail($pageId);
        $templateId = $page->claim_form_id;

        $validated = $request->validate([
            'field_name' => 'required|string|max:50',
            'standard_field_code' => 'nullable|string|max:50',
            'field_label' => 'required|string|max:100',
            'field_type' => 'required|in:text,date,number,resident_number,resident_number_front,resident_number_back,phone,textarea,checkbox,radio,consent,signature',
            'x_position' => 'required|integer|min:0',
            'y_position' => 'required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'field_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'field_options' => 'nullable',
        ]);

        // 표준 필드 코드 유효성 검증
        if (!empty($validated['standard_field_code'])) {
            $standardFields = config('standard_fields', []);
            $validCodes = array_column($standardFields, 'code');
            if (!in_array($validated['standard_field_code'], $validCodes)) {
                return response()->json([
                    'success' => false,
                    'message' => '유효하지 않은 표준 필드 코드입니다.',
                ], 422);
            }

            $exists = FormField::where('claim_form_id', $templateId)
                ->where('standard_field_code', $validated['standard_field_code'])
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => '이 양식에 이미 동일한 표준 필드가 존재합니다.',
                ], 422);
            }
        }

        $validated['claim_form_id'] = $templateId;
        $validated['form_page_id'] = $pageId;

        // field_order가 없으면 마지막으로
        if (!isset($validated['field_order'])) {
            $validated['field_order'] = $page->formFields()->max('field_order') + 1;
        }

        $field = FormField::create($validated);

        return response()->json([
            'success' => true,
            'data' => $field,
            'message' => '필드가 추가되었습니다.',
        ], 201);
    }

    /**
     * 필드 수정
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $field = FormField::findOrFail($id);

        // 표준 필드는 field_name, field_label, field_type 수정 불가
        if ($field->standard_field_code && $request->hasAny(['field_name', 'field_label', 'field_type'])) {
            return response()->json([
                'success' => false,
                'message' => '표준 필드의 이름, 라벨, 타입은 수정할 수 없습니다.',
            ], 422);
        }

        $validated = $request->validate([
            'form_page_id' => 'sometimes|nullable|exists:form_page,form_page_id',
            'field_name' => 'sometimes|required|string|max:50',
            'field_label' => 'sometimes|required|string|max:100',
            'field_type' => 'sometimes|required|in:text,date,number,resident_number,resident_number_front,resident_number_back,phone,textarea,checkbox,radio,consent,signature',
            'x_position' => 'sometimes|required|integer|min:0',
            'y_position' => 'sometimes|required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'field_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'field_options' => 'nullable',
        ]);

        $field->update($validated);

        return response()->json([
            'success' => true,
            'data' => $field,
            'message' => '필드가 수정되었습니다.',
        ]);
    }

    /**
     * 필드 삭제
     */
    public function destroy(int $id): JsonResponse
    {
        $field = FormField::findOrFail($id);

        // 연관된 청구 값이 있는지 확인
        if ($field->claimFieldValues()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => '이 필드에 입력된 데이터가 있어 삭제할 수 없습니다.',
            ], 400);
        }

        $field->delete();

        return response()->json([
            'success' => true,
            'message' => '필드가 삭제되었습니다.',
        ]);
    }

    /**
     * 필드 일괄 업데이트 (드래그앤드롭으로 좌표 변경 시)
     */
    public function bulkUpdate(Request $request, int $templateId): JsonResponse
    {
        $template = ClaimForm::findOrFail($templateId);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.form_field_id' => 'required|exists:form_field,form_field_id',
            'fields.*.x_position' => 'required|integer|min:0',
            'fields.*.y_position' => 'required|integer|min:0',
            'fields.*.width' => 'integer|min:10|max:2000',
            'fields.*.height' => 'integer|min:10|max:1000',
            'fields.*.field_order' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $templateId) {
            foreach ($validated['fields'] as $fieldData) {
                $field = FormField::where('form_field_id', $fieldData['form_field_id'])
                    ->where('claim_form_id', $templateId)
                    ->first();

                if ($field) {
                    $updateData = [
                        'x_position' => $fieldData['x_position'],
                        'y_position' => $fieldData['y_position'],
                    ];

                    if (isset($fieldData['width'])) {
                        $updateData['width'] = $fieldData['width'];
                    }
                    if (isset($fieldData['height'])) {
                        $updateData['height'] = $fieldData['height'];
                    }
                    if (isset($fieldData['field_order'])) {
                        $updateData['field_order'] = $fieldData['field_order'];
                    }

                    $field->update($updateData);
                }
            }
        });

        $fields = $template->formFields()->orderBy('field_order')->get();

        return response()->json([
            'success' => true,
            'data' => $fields,
            'message' => '필드 위치가 저장되었습니다.',
        ]);
    }
}
