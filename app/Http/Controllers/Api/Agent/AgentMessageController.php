<?php

namespace App\Http\Controllers\Api\Agent;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMessageController extends Controller
{
    /**
     * List sent messages by this agent.
     */
    public function index(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $query = Message::where('sender_id', $agentId)
            ->where('sender_type', 'AGENT');

        if ($request->filled('send_status')) {
            $query->where('send_status', $request->get('send_status'));
        }

        if ($request->filled('send_method')) {
            $query->where('send_method', $request->get('send_method'));
        }

        if ($request->filled('message_type')) {
            $query->where('message_type', $request->get('message_type'));
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate(min(max((int) $request->get('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => $messages,
            'message' => '메시지 목록을 조회했습니다.',
        ]);
    }

    /**
     * Send a message to a customer.
     */
    public function store(Request $request): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $validated = $request->validate([
            'receiver_id' => 'required|exists:customer,customer_id',
            'message_type' => 'required|string',
            'title' => 'nullable|string|max:200',
            'content' => 'required|string',
            'image_url' => 'nullable|string|max:500',
            'send_method' => 'required|in:SMS,KAKAO,PUSH',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        // Verify the receiver customer belongs to this agent
        $customer = Customer::where('customer_id', $validated['receiver_id'])
            ->where('agent_id', $agentId)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '해당 고객은 담당 고객이 아닙니다.',
            ], 403);
        }

        $messageData = [
            'receiver_id' => $validated['receiver_id'],
            'receiver_type' => 'CUSTOMER',
            'sender_id' => $agentId,
            'sender_type' => 'AGENT',
            'message_type' => $validated['message_type'],
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'image_url' => $validated['image_url'] ?? null,
            'send_method' => $validated['send_method'],
        ];

        if (!empty($validated['scheduled_at'])) {
            $messageData['send_status'] = 'scheduled';
            $messageData['scheduled_at'] = $validated['scheduled_at'];
        } else {
            $messageData['send_status'] = 'sent';
            $messageData['sent_at'] = now();
        }

        $message = Message::create($messageData);

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => '메시지를 발송했습니다.',
        ], 201);
    }

    /**
     * Show message detail.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $agentId = $request->user()->agent->agent_id;

        $message = Message::where('message_id', $id)
            ->where('sender_id', $agentId)
            ->where('sender_type', 'AGENT')
            ->first();

        if (!$message) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => '메시지를 찾을 수 없습니다.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => '메시지 상세 정보를 조회했습니다.',
        ]);
    }
}
