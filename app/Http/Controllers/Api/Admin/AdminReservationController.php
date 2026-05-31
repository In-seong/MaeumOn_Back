<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HospitalReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HospitalReservation::with(['hospital', 'healthCenter'])
            ->orderByDesc('created_at');

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

        return response()->json([
            'success' => true,
            'data' => $reservation->load(['hospital', 'healthCenter']),
        ]);
    }
}
