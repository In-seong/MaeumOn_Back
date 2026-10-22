<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AwardPlan;
use App\Services\PdfToImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class AdminAwardPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AwardPlan::query();

        if ($request->has('year') && $request->has('month')) {
            $query->where('target_year', $request->year)
                  ->where('target_month', $request->month);
        }

        $items = $query->orderBy('target_year', 'desc')
            ->orderBy('target_month', 'desc')
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function months(): JsonResponse
    {
        $months = AwardPlan::selectRaw('target_year, target_month, COUNT(*) as count')
            ->groupBy('target_year', 'target_month')
            ->orderBy('target_year', 'desc')
            ->orderBy('target_month', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $months,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'target_year' => 'required|integer|min:2020|max:2099',
            'target_month' => 'required|integer|min:1|max:12',
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:jpeg,png,jpg,pdf|max:20480',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $created = [];
        $files = $request->file('files');
        $sortOrder = $request->input('sort_order', 0);

        foreach ($files as $index => $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $uniqueId = time() . '_' . uniqid();

            $filePath = $file->storeAs(
                'award-plans',
                "award_{$uniqueId}.{$ext}",
                's3'
            );

            $thumbnailPath = null;

            if ($ext === 'pdf') {
                $thumbnailPath = $this->generatePdfThumbnail($file, $uniqueId);
            } else {
                $thumbnailPath = $this->generateImageThumbnail($file, $uniqueId, $ext);
            }

            $title = $request->input('title');
            if (count($files) > 1) {
                $title = $title . ' (' . ($index + 1) . ')';
            }

            $item = AwardPlan::create([
                'title' => $title,
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'thumbnail_path' => $thumbnailPath,
                'target_year' => $request->input('target_year'),
                'target_month' => $request->input('target_month'),
                'sort_order' => $sortOrder + $index,
            ]);

            $created[] = $item;
        }

        return response()->json([
            'success' => true,
            'data' => $created,
            'message' => count($created) . '건의 시상안이 등록되었습니다.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = AwardPlan::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'target_year' => 'sometimes|required|integer|min:2020|max:2099',
            'target_month' => 'sometimes|required|integer|min:1|max:12',
            'sort_order' => 'nullable|integer|min:0',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:20480',
        ]);

        $data = array_intersect_key($validated, array_flip(['title', 'target_year', 'target_month', 'sort_order']));

        if ($request->hasFile('file')) {
            if ($item->file_path) Storage::disk('s3')->delete($item->file_path);
            if ($item->thumbnail_path) Storage::disk('s3')->delete($item->thumbnail_path);

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            $uniqueId = time() . '_' . uniqid();

            $data['file_path'] = $file->storeAs('award-plans', "award_{$uniqueId}.{$ext}", 's3');
            $data['file_name'] = $file->getClientOriginalName();
            $data['thumbnail_path'] = $ext === 'pdf'
                ? $this->generatePdfThumbnail($file, $uniqueId)
                : $this->generateImageThumbnail($file, $uniqueId, $ext);
        }

        $item->update($data);

        return response()->json([
            'success' => true,
            'data' => $item->fresh(),
            'message' => '시상안이 수정되었습니다.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $item = AwardPlan::findOrFail($id);

        if ($item->file_path) Storage::disk('s3')->delete($item->file_path);
        if ($item->thumbnail_path) Storage::disk('s3')->delete($item->thumbnail_path);

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => '시상안이 삭제되었습니다.',
        ]);
    }

    private function generatePdfThumbnail($file, string $uniqueId): ?string
    {
        try {
            $pdfService = app(PdfToImageService::class);
            $result = $pdfService->convertToImages($file->path(), 150);

            $firstPage = $result['images'][0] ?? null;
            if (!$firstPage) {
                $pdfService->cleanup($result['tempDir']);
                return null;
            }

            $thumb = Image::read($firstPage)->scaleDown(width: 400);
            $thumbFilename = "award_thumb_{$uniqueId}.png";
            $thumbPath = "award-plans/thumbnails/{$thumbFilename}";

            Storage::disk('s3')->put($thumbPath, $thumb->toPng()->toString());
            $pdfService->cleanup($result['tempDir']);

            return $thumbPath;
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateImageThumbnail($file, string $uniqueId, string $ext): ?string
    {
        try {
            $thumb = Image::read($file->path())->scaleDown(width: 400);
            $thumbFilename = "award_thumb_{$uniqueId}.{$ext}";
            $thumbPath = "award-plans/thumbnails/{$thumbFilename}";

            $encoded = $ext === 'png' ? $thumb->toPng()->toString() : $thumb->toJpeg(85)->toString();
            Storage::disk('s3')->put($thumbPath, $encoded);

            return $thumbPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
