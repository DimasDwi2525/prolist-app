<?php

namespace App\Http\Controllers\API\SUC;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\PackingList;
use App\Models\MasterStatusMr;
use App\Models\MasterTypePackingList;
use Illuminate\Http\Request;

class SUCDashboardController extends Controller
{
    /**
     * Get SUC Dashboard data (MR outstanding, MR overdue, PL outstanding)
     */
    public function index(Request $request)
    {
        // Get status IDs for 'On Progress' and 'Waiting Approval'
        $onProgressStatus = MasterStatusMr::where('name', 'On Progress')->first();
        $waitingApprovalStatus = MasterStatusMr::where('name', 'Waiting Approval')->first();

        $outstandingStatusIds = [];
        if ($onProgressStatus) {
            $outstandingStatusIds[] = $onProgressStatus->id;
        }
        if ($waitingApprovalStatus) {
            $outstandingStatusIds[] = $waitingApprovalStatus->id;
        }

        // MR Outstanding
        $mrOutstanding = MaterialRequest::with(['project', 'creator', 'mrHandover', 'materialStatus'])
            ->whereIn('material_status_id', $outstandingStatusIds)
            ->orderBy('target_date', 'asc')
            ->get();

        // MR Overdue
        $mrOverdue = MaterialRequest::with(['project', 'creator', 'mrHandover', 'materialStatus'])
            ->whereIn('material_status_id', $outstandingStatusIds)
            ->whereNotNull('target_date')
            ->where('target_date', '<', now())
            ->orderBy('target_date', 'asc')
            ->get();

        // PL Outstanding - only type 1 & 5 that haven't been returned yet
        $plQuery = PackingList::with(['project', 'expedition', 'plType', 'intPic', 'creator', 'destination'])
            ->whereIn('pl_type_id', [1, 5])
            ->whereNull('pl_return_date');

        // Optional filter by pl_type_id (overrides default filter)
        if ($request->has('pl_type_id') && $request->pl_type_id) {
            $plQuery->where('pl_type_id', $request->pl_type_id);
        }

        $plOutstanding = $plQuery->orderBy('created_at', 'desc')->get();

        // Get available types for filter
        $availablePlTypes = MasterTypePackingList::all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'mr_outstanding' => [
                    'count' => $mrOutstanding->count(),
                    'list' => $mrOutstanding
                ],
                'mr_overdue' => [
                    'count' => $mrOverdue->count(),
                    'list' => $mrOverdue
                ],
                'pl_outstanding' => [
                    'count' => $plOutstanding->count(),
                    'list' => $plOutstanding,
                    'available_types' => $availablePlTypes
                ]
            ]
        ]);
    }
}
