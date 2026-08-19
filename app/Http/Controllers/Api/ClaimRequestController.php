<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ClaimRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClaimRequestController extends Controller
{
    /**
     * 간편 청구 신청 (공개 API - 인증 불필요)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'hospital_id' => 'nullable|integer|exists:partner_hospital,hospital_id',
            'memo' => 'nullable|string|max:2000',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240', // 10MB
            'agent_name' => 'nullable|string|max:50',
        ]);

        $agentName = $validated['agent_name'] ?? null;
        $agentId = null;
        if ($agentName) {
            $agent = Agent::where('name', $agentName)->where('is_active', true)->first();
            if ($agent) {
                $agentId = $agent->agent_id;
            }
        }

        $claimRequest = ClaimRequest::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'hospital_id' => $validated['hospital_id'] ?? null,
            'memo' => $validated['memo'] ?? null,
            'status' => $agentId ? 'assigned' : 'pending',
            'source_type' => 'distribution',
            'assigned_agent_id' => $agentId,
            'agent_name' => $agentName,
        ]);

        // 파일 업로드 처리
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = Storage::disk('s3')->put(
                    'claim-requests/' . $claimRequest->request_id,
                    $file
                );

                $claimRequest->files()->create([
                    'file_url' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $claimRequest->load('files');

        return response()->json([
            'success' => true,
            'data' => $claimRequest,
            'message' => '청구 신청이 접수되었습니다. 설계사가 연락드리겠습니다.',
        ], 201);
    }

    public function agents(): JsonResponse
    {
        $agents = Agent::where('is_active', true)
            ->select('agent_id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $agents]);
    }
}
