<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminFormDownloadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FormDownload::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('company_name', 'like', "%{$request->search}%");
        }

        $items = $query->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('company_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:100',
            'category' => 'required|string|in:non-life,life,cooperative',
            'claim_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'dental_confirm' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = [
            'company_name' => $validated['company_name'],
            'category' => $validated['category'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ];

        if ($request->hasFile('claim_form')) {
            $file = $request->file('claim_form');
            $filename = 'form_download_claim_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $data['claim_form_path'] = $file->storeAs('form-downloads', $filename, 's3');
            $data['claim_form_name'] = $file->getClientOriginalName();
        }

        if ($request->hasFile('dental_confirm')) {
            $file = $request->file('dental_confirm');
            $filename = 'form_download_dental_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $data['dental_confirm_path'] = $file->storeAs('form-downloads', $filename, 's3');
            $data['dental_confirm_name'] = $file->getClientOriginalName();
        }

        $item = FormDownload::create($data);

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => '양식이 등록되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = FormDownload::findOrFail($id);

        $validated = $request->validate([
            'company_name' => 'sometimes|required|string|max:100',
            'category' => 'sometimes|required|string|in:non-life,life,cooperative',
            'claim_form' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'dental_confirm' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:20480',
            'sort_order' => 'nullable|integer|min:0',
            'remove_claim_form' => 'nullable|boolean',
            'remove_dental_confirm' => 'nullable|boolean',
        ]);

        $data = array_intersect_key($validated, array_flip(['company_name', 'category', 'sort_order']));

        if ($request->boolean('remove_claim_form') && $item->claim_form_path) {
            Storage::disk('s3')->delete($item->claim_form_path);
            $data['claim_form_path'] = null;
            $data['claim_form_name'] = null;
        }

        if ($request->boolean('remove_dental_confirm') && $item->dental_confirm_path) {
            Storage::disk('s3')->delete($item->dental_confirm_path);
            $data['dental_confirm_path'] = null;
            $data['dental_confirm_name'] = null;
        }

        if ($request->hasFile('claim_form')) {
            if ($item->claim_form_path) {
                Storage::disk('s3')->delete($item->claim_form_path);
            }
            $file = $request->file('claim_form');
            $filename = 'form_download_claim_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $data['claim_form_path'] = $file->storeAs('form-downloads', $filename, 's3');
            $data['claim_form_name'] = $file->getClientOriginalName();
        }

        if ($request->hasFile('dental_confirm')) {
            if ($item->dental_confirm_path) {
                Storage::disk('s3')->delete($item->dental_confirm_path);
            }
            $file = $request->file('dental_confirm');
            $filename = 'form_download_dental_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $data['dental_confirm_path'] = $file->storeAs('form-downloads', $filename, 's3');
            $data['dental_confirm_name'] = $file->getClientOriginalName();
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'data' => $item->fresh(),
            'message' => '양식이 수정되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = FormDownload::findOrFail($id);

        if ($item->claim_form_path) {
            Storage::disk('s3')->delete($item->claim_form_path);
        }
        if ($item->dental_confirm_path) {
            Storage::disk('s3')->delete($item->dental_confirm_path);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => '양식이 삭제되었습니다.',
        ]);
    }

    public function download(int $id, string $type): JsonResponse
    {
        $item = FormDownload::findOrFail($id);

        $pathField = $type === 'claim' ? 'claim_form_path' : 'dental_confirm_path';
        $nameField = $type === 'claim' ? 'claim_form_name' : 'dental_confirm_name';

        if (!$item->$pathField) {
            return response()->json([
                'success' => false,
                'message' => '파일이 없습니다.',
            ], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($item->$pathField, now()->addMinutes(60));

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $url,
                'filename' => $item->$nameField,
            ],
        ]);
    }
}
