<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgentInquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = AgentInquiry::where('agent_id', $agentId)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $inquiries = $query->paginate(
            min(max((int) $request->get('per_page', 15), 1), 100)
        );

        return response()->json([
            'success' => true,
            'data' => $inquiries,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string|max:5000',
            'category' => 'required|string|in:system_error,feature_request,business,other',
            'files' => 'nullable|array|max:3',
            'files.*' => 'file|max:10240',
        ]);

        $inquiry = AgentInquiry::create([
            'agent_id' => $agentId,
            'category' => $validated['category'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => 'pending',
        ]);

        if ($request->hasFile('files')) {
            $attachments = [];
            foreach ($request->file('files') as $file) {
                $path = $file->store('inquiries/' . $agentId, 's3');
                $attachments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ];
            }
            $inquiry->attachments = $attachments;
            $inquiry->save();
        }

        return response()->json([
            'success' => true,
            'data' => $inquiry,
            'message' => '문의가 등록되었습니다.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $inquiry = AgentInquiry::with('answeredByAdmin:admin_id,name')
            ->where('agent_id', $agentId)
            ->where('inquiry_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $inquiry,
        ]);
    }
}
