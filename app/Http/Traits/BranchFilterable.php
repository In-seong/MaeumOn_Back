<?php

namespace App\Http\Traits;

use App\Models\Admin;
use Illuminate\Http\Request;

trait BranchFilterable
{
    protected function resolveBranchId(Request $request): ?int
    {
        $admin = Admin::where('account_id', $request->user()->account_id)->first();

        if (!$admin) {
            return null;
        }

        if (!$admin->isSuperAdmin()) {
            return $admin->branch_id;
        }

        if ($request->filled('branch_id')) {
            return (int) $request->branch_id;
        }

        return null;
    }

    protected function applyAgentBranchFilter($query, ?int $branchId, string $relation = 'branches')
    {
        if ($branchId !== null) {
            $query->whereHas($relation, function ($q) use ($branchId) {
                $q->where('branch.branch_id', $branchId);
            });
        }

        return $query;
    }
}
