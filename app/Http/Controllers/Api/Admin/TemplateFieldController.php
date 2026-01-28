<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimFormTemplate;
use App\Models\TemplatePage;
use App\Models\TemplateField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateFieldController extends Controller
{
    /**
     * 양식의 필드 목록
     */
    public function index(int $templateId): JsonResponse
    {
        $template = ClaimFormTemplate::findOrFail($templateId);
        $fields = $template->templateFields()->orderBy('sort_order')->get();

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
        $page = TemplatePage::findOrFail($pageId);
        $fields = $page->templateFields()->orderBy('sort_order')->get();

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
        $template = ClaimFormTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'template_page_id' => 'nullable|exists:template_pages,id',
            'field_name' => 'required|string|max:50|unique:template_fields,field_name,NULL,id,claim_form_template_id,' . $templateId,
            'field_label' => 'required|string|max:100',
            'field_type' => 'required|in:text,date,number,resident_number,phone,textarea',
            'x_position' => 'required|integer|min:0',
            'y_position' => 'required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'sort_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
        ]);

        $validated['claim_form_template_id'] = $templateId;

        // 페이지 ID가 없으면 첫 번째 페이지에 연결
        if (empty($validated['template_page_id'])) {
            $firstPage = $template->templatePages()->first();
            if ($firstPage) {
                $validated['template_page_id'] = $firstPage->id;
            }
        }

        // sort_order가 없으면 마지막으로
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = $template->templateFields()->max('sort_order') + 1;
        }

        $field = TemplateField::create($validated);

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
        $page = TemplatePage::findOrFail($pageId);
        $templateId = $page->claim_form_template_id;

        $validated = $request->validate([
            'field_name' => 'required|string|max:50|unique:template_fields,field_name,NULL,id,claim_form_template_id,' . $templateId,
            'field_label' => 'required|string|max:100',
            'field_type' => 'required|in:text,date,number,resident_number,phone,textarea',
            'x_position' => 'required|integer|min:0',
            'y_position' => 'required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'sort_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
        ]);

        $validated['claim_form_template_id'] = $templateId;
        $validated['template_page_id'] = $pageId;

        // sort_order가 없으면 마지막으로
        if (!isset($validated['sort_order'])) {
            $validated['sort_order'] = $page->templateFields()->max('sort_order') + 1;
        }

        $field = TemplateField::create($validated);

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
        $field = TemplateField::findOrFail($id);

        $validated = $request->validate([
            'template_page_id' => 'sometimes|nullable|exists:template_pages,id',
            'field_name' => 'sometimes|required|string|max:50|unique:template_fields,field_name,' . $id . ',id,claim_form_template_id,' . $field->claim_form_template_id,
            'field_label' => 'sometimes|required|string|max:100',
            'field_type' => 'sometimes|required|in:text,date,number,resident_number,phone,textarea',
            'x_position' => 'sometimes|required|integer|min:0',
            'y_position' => 'sometimes|required|integer|min:0',
            'width' => 'integer|min:10|max:2000',
            'height' => 'integer|min:10|max:1000',
            'font_size' => 'integer|min:8|max:72',
            'font_color' => 'string|regex:/^#[0-9A-Fa-f]{6}$/',
            'is_required' => 'boolean',
            'sort_order' => 'integer|min:0',
            'placeholder' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
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
        $field = TemplateField::findOrFail($id);

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
        $template = ClaimFormTemplate::findOrFail($templateId);

        $validated = $request->validate([
            'fields' => 'required|array',
            'fields.*.id' => 'required|exists:template_fields,id',
            'fields.*.x_position' => 'required|integer|min:0',
            'fields.*.y_position' => 'required|integer|min:0',
            'fields.*.width' => 'integer|min:10|max:2000',
            'fields.*.height' => 'integer|min:10|max:1000',
            'fields.*.sort_order' => 'integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $templateId) {
            foreach ($validated['fields'] as $fieldData) {
                $field = TemplateField::where('id', $fieldData['id'])
                    ->where('claim_form_template_id', $templateId)
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
                    if (isset($fieldData['sort_order'])) {
                        $updateData['sort_order'] = $fieldData['sort_order'];
                    }

                    $field->update($updateData);
                }
            }
        });

        $fields = $template->templateFields()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => $fields,
            'message' => '필드 위치가 저장되었습니다.',
        ]);
    }
}
