<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\BranchFilterable;
use App\Models\HospitalReservation;
use App\Services\ReservationNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReservationController extends Controller
{
    use BranchFilterable;

    public function __construct(private ReservationNotifier $notifier)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = HospitalReservation::with(['hospital', 'healthCenter']);

        $branchId = $this->resolveBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->whereHas('hospital', fn ($hq) => $hq->where('branch_id', $branchId))
                  ->orWhereHas('healthCenter', fn ($cq) => $cq->where('branch_id', $branchId));
            });
        }

        // 정렬
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (in_array($sortField, ['created_at', 'reservation_date', 'status'])) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('reservation_type')) {
            $query->where('reservation_type', $request->input('reservation_type'));
        }

        if ($request->filled('date')) {
            $query->where('reservation_date', $request->input('date'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                  ->orWhere('patient_phone', 'like', "%{$search}%");
            });
        }

        $reservations = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reservations,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled,completed',
        ]);

        $reservation = HospitalReservation::findOrFail($id);
        $reservation->update(['status' => $validated['status']]);

        $this->notifier->onStatusChanged($reservation->load(['hospital', 'healthCenter']), $validated['status']);

        return response()->json([
            'success' => true,
            'data' => $reservation,
        ]);
    }
}
