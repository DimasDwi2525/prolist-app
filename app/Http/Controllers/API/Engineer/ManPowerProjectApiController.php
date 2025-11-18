<?php

namespace App\Http\Controllers\API\Engineer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManPowerProjectApiController extends Controller
{
    //
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->name ?? null;
        $userId = $user->id;

        $allowedRoles = [
            'engineer_supervisor',
            'engineer',
            'drafter',
            'electrician_supervisor',
            'electrician',
            'site_engineer'
        ];

        // Kalau rolenya tidak termasuk, return forbidden
        if (!in_array($role, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized role',
            ], 403);
        }

        $projects = Project::query()
            ->where(function ($q) use ($userId) {
                // hanya ambil project yang user ini terlibat di manpower allocation
                $q->whereHas('manPowerAllocations', function ($sub) use ($userId) {
                    $sub->where('user_id', $userId);
                });
            })
            ->orWhere(function ($q) use ($userId, $role) {
                // atau user ini terlibat di PHC sesuai role
                $q->whereHas('phc', function ($sub) use ($userId, $role) {
                    if (in_array($role, ['engineer', 'engineer_supervisor'])) {
                        $sub->where('pic_engineering_id', $userId)
                            ->orWhere('ho_engineering_id', $userId);
                    }

                    if ($role === 'drafter') {
                        $sub->where('drafter_id', $userId);
                    }

                    if (in_array($role, ['electrician', 'electrician_supervisor'])) {
                        $sub->where('pic_electrician_id', $userId)
                            ->orWhere('ho_electrician_id', $userId);
                    }
                });
            })
            ->with(['category', 'quotation.client', 'client', 'statusProject'])
            ->latest()
            ->get();


        return response()->json([
            'success' => true,
            'data' => $projects,
        ]);
    }
}
