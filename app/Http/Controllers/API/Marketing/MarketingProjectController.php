<?php

namespace App\Http\Controllers\API\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as FacadesLog;



class MarketingProjectController extends Controller
{
    //

    public function index(Request $request)
    {
        $yearParam = $request->query('year');
        $availableYears = $this->getAvailableYears();
        $year = $yearParam ? (int)$yearParam : (!empty($availableYears) ? end($availableYears) : now()->year);

        $rangeType = $request->query('range_type', 'yearly'); // yearly, monthly, weekly, custom
        $monthParam = $request->query('month'); // 1-12
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Target date filter parameters
        $enableTargetDateFilter = $request->boolean('enable_target_date_filter', false); // NEW PARAMETER
        $targetDateRangeType = $request->query('target_date_range_type'); // yearly, monthly, weekly, custom
        $targetDateMonthParam = $request->query('target_date_month'); // 1-12
        $targetDateFrom = $request->query('target_date_from');
        $targetDateTo = $request->query('target_date_to');


        // Inisialisasi query dengan eager loading
        $projects = Project::with(['category', 'quotation.client', 'client', 'statusProject', 'phc']);

        // Apply PO Date Filter (Always applied)
        if ($rangeType === 'monthly' && $monthParam) {
            $projects->whereYear('po_date', $year)
                     ->whereMonth('po_date', $monthParam);
        } elseif ($rangeType === 'weekly') {
            $projects->whereBetween('po_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($rangeType === 'custom' && $fromDate && $toDate) {
            $projects->whereBetween('po_date', [$fromDate, $toDate]);
        } else {
            // default: filter berdasarkan tahun saja
            $projects->whereYear('po_date', $year);
        }

        // Apply Target Date Filter (Only if enabled)
        if ($enableTargetDateFilter && $targetDateRangeType) {
            $targetYear = $request->query('target_year') ? (int)$request->query('target_year') : now()->year;
            $targetDateRange = null;

            if ($targetDateRangeType === 'monthly' && $targetDateMonthParam) {
                $month = (int)$targetDateMonthParam;
                if ($month >= 1 && $month <= 12) {
                    $startDate = \Carbon\Carbon::createFromDate($targetYear, $month, 1)->startOfDay();
                    $endDate = \Carbon\Carbon::createFromDate($targetYear, $month, 1)->endOfMonth()->endOfDay();
                    $targetDateRange = [$startDate, $endDate];
                }
            } elseif ($targetDateRangeType === 'weekly') {
                $startDate = now()->startOfWeek()->startOfDay();
                $endDate = now()->endOfWeek()->endOfDay();
                $targetDateRange = [$startDate, $endDate];
             
            } elseif ($targetDateRangeType === 'custom' && $targetDateFrom && $targetDateTo) {
                try {
                    $startDate = \Carbon\Carbon::parse($targetDateFrom)->startOfDay();
                    $endDate = \Carbon\Carbon::parse($targetDateTo)->endOfDay();
                    $targetDateRange = [$startDate, $endDate];
                    
                } catch (\Exception $e) {
                    $targetDateRange = null;
                }
            } elseif ($targetDateRangeType === 'yearly') {
                $startDate = \Carbon\Carbon::createFromDate($targetYear, 1, 1)->startOfDay();
                $endDate = \Carbon\Carbon::createFromDate($targetYear, 12, 31)->endOfDay();
                $targetDateRange = [$startDate, $endDate];
            }


            // Apply target date filter using left join for better performance
            if ($targetDateRange) {
                $projects->leftJoin('phcs', 'projects.pn_number', '=', 'phcs.project_id')
                         ->where(function($q) use ($targetDateRange) {
                             // Project target_dates dalam range ATAU PHC target_finish_date dalam range
                             $q->where('projects.target_dates', '>=', $targetDateRange[0])
                               ->where('projects.target_dates', '<=', $targetDateRange[1])
                               ->orWhere(function($subQ) use ($targetDateRange) {
                                   $subQ->whereNotNull('phcs.target_finish_date')
                                        ->where('phcs.target_finish_date', '>=', $targetDateRange[0])
                                        ->where('phcs.target_finish_date', '<=', $targetDateRange[1]);
                               });
                         });
            }
        }

        

        // Execute query with ordering
        $projects = $projects
            ->select('projects.*') // Important: select all project columns to avoid conflicts with join
            ->orderByRaw('CAST(LEFT(CAST(pn_number AS VARCHAR), 2) AS INT) DESC')
            ->orderByRaw('CAST(SUBSTRING(CAST(pn_number AS VARCHAR), 3, LEN(CAST(pn_number AS VARCHAR)) - 2) AS INT) DESC')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Projects fetched successfully',
            'data'    => $projects,
            'filters' => [
                'year' => $year,
                'range_type' => $rangeType,
                'month' => $monthParam,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'target_year' => $request->query('target_year'),
                'target_date_range_type' => $targetDateRangeType,
                'target_date_month' => $targetDateMonthParam,
                'target_date_from' => $targetDateFrom,
                'target_date_to' => $targetDateTo,
                'enable_target_date_filter' => $enableTargetDateFilter,
                'available_years' => $availableYears,
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_name'           => 'required|string|max:255',
                'categories_project_id'  => 'nullable|exists:project_categories,id',
                'quotations_id'          => 'required|exists:quotations,quotation_number',
                'phc_dates'              => 'nullable|date',
                'mandays_engineer'       => 'nullable|integer',
                'mandays_technician'     => 'nullable|integer',
                'target_dates'           => 'nullable|date',
                'status_project_id'      => 'nullable|exists:status_projects,id',
                'po_date'                => 'nullable|date',
                'sales_weeks'            => 'nullable|string|max:50',
                'po_number'              => 'nullable|string|max:100',
                'po_value'               => 'nullable|numeric',
                'is_confirmation_order'  => 'nullable|boolean',
                'parent_pn_number'       => 'nullable|exists:projects,pn_number',
                'client_id'              => 'nullable|exists:clients,id',
            ]);

            // Set default false jika checkbox tidak dicentang
            $validated['is_confirmation_order'] = $request->boolean('is_confirmation_order', false);

             // ✅ default category = 1 kalau tidak ada input
            $validated['status_project_id'] = $request->input('status_project_id', 1);

            $project = Project::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Project created successfully',
                'data'    => $project,
            ]);
        } catch (\Throwable $e) {
            FacadesLog::error($e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create project',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Project $project)
    {
        $project->load([
            'variants.statusProject',
            'parent.statusProject',
            'statusProject',
            'category',
            'phc.approvals.user',
            'client',
            'quotation.client'
        ]);

        $pendingApprovals = $project->phc
            ? $project->phc->approvals()->where('status', 'pending')->with('user')->get()
            : collect();

        return response()->json([
            'status'  => 'success',
            'message' => 'Project detail fetched successfully',
            'data'    => [
                'project'          => $project,
                'pendingApprovals' => $pendingApprovals,
            ],
        ]);
    }

    public function update(Request $request, Project $project)
    {
        try {
            $validated = $request->validate([
                'project_name'           => 'sometimes|string|max:255',
                'categories_project_id'  => 'sometimes|exists:project_categories,id',
                'quotations_id'          => 'sometimes|exists:quotations,quotation_number',
                'phc_dates'              => 'sometimes|date',
                'mandays_engineer'       => 'sometimes|nullable|integer',
                'mandays_technician'     => 'sometimes|nullable|integer',
                'target_dates'           => 'sometimes|nullable|date',
                'material_status'        => 'sometimes|nullable|string',
                'dokumen_finish_date'    => 'sometimes|nullable|date',
                'engineering_finish_date'=> 'sometimes|nullable|date',
                'jumlah_invoice'         => 'sometimes|nullable|integer',
                'status_project_id'      => 'sometimes|exists:status_projects,id',
                'project_progress'       => 'sometimes|nullable|integer|min:0|max:100',
                'po_date'                => 'sometimes|nullable|date',
                'sales_weeks'            => 'sometimes|string|max:50',
                'po_number'              => 'nullable|nullable|string|max:100',
                'po_value'               => 'sometimes|nullable|numeric',
                'is_confirmation_order'  => 'sometimes|nullable|boolean',
                'parent_pn_number'       => 'sometimes|exists:projects,pn_number',
                'client_id'              => 'nullable|exists:clients,id',
            ]);

            $validated['is_confirmation_order'] = $request->boolean('is_confirmation_order', false);

            $project->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Project updated successfully',
                'data'    => $project,
            ]);
        } catch (\Throwable $e) {
            FacadesLog::error($e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update project',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Project $project)
    {
        try {
            \App\Models\PhcApproval::whereIn('phc_id', $project->phcs()->pluck('id'))->delete();
            $project->phcs()->delete();
            $project->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Project deleted successfully',
            ]);
        } catch (\Throwable $e) {
            FacadesLog::error($e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to delete project',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function generateNumber(Request $request)
    {
        $isCO = $request->boolean('is_co', false);
        $currentProject = $request->input('current_project');
        $simple = $request->boolean('simple', false);

        // kalau sudah ada project number dan bukan CO, return existing
        if ($currentProject && !$isCO) {
            $data = [
                'project_number' => $currentProject,
            ];
            return $simple
                ? response()->json($data)
                : response()->json([
                    'status'  => 'success',
                    'message' => 'Keep existing project number',
                    'data'    => $data,
                ]);
        }

        // generate baru
        $pnNumber = Project::generatePnNumber();
        $projectNumber = Project::generateProjectNumber($pnNumber, $isCO);

        $data = [
            'pn_number' => $pnNumber,
            'project_number' => $projectNumber,
        ];

        return $simple
            ? response()->json($data)
            : response()->json([
                'status'  => 'success',
                'message' => 'Generated project number',
                'data'    => $data,
            ]);
    }

    public function nextProjectNumber()
    {
        $yearShort = now()->format('y'); // '25'
        $start = (int) ($yearShort . '000');
        $end = (int) ($yearShort . '999');

        $last = Project::whereBetween('pn_number', [$start, $end])
            ->whereRaw('pn_number % 1000 != 0') // exclude pn_number ending with 000
            ->orderByDesc('pn_number')
            ->first();

        if ($last) {
            $lastNumber = (int) substr((string) $last->pn_number, 2);
            $next = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return response()->json([
            'next_number' => str_pad($next, 3, '0', STR_PAD_LEFT),
            'year' => $yearShort
        ]);
    }

    public function storeCustom(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_name'           => 'required|string|max:255',
                'categories_project_id'  => 'nullable|exists:project_categories,id',
                'quotations_id'          => 'required|exists:quotations,quotation_number',
                'phc_dates'              => 'nullable|date',
                'mandays_engineer'       => 'nullable|integer',
                'mandays_technician'     => 'nullable|integer',
                'target_dates'           => 'nullable|date',
                'status_project_id'      => 'nullable|exists:status_projects,id',
                'po_date'                => 'nullable|date',
                'sales_weeks'            => 'nullable|string|max:50',
                'po_number'              => 'nullable|string|max:100',
                'po_value'               => 'nullable|numeric',
                'is_confirmation_order'  => 'nullable|boolean',
                'parent_pn_number'       => 'nullable|exists:projects,pn_number',
                'client_id'              => 'nullable|exists:clients,id',
                'project_no'             => 'nullable|integer|min:1|max:999',
            ]);

            // Set default false jika checkbox tidak dicentang
            $validated['is_confirmation_order'] = $request->boolean('is_confirmation_order', false);

            // default category = 1 kalau tidak ada input
            $validated['status_project_id'] = $request->input('status_project_id', 1);

            // Handle custom project number
            $yearShort = now()->format('y');
            if ($request->has('project_no') && $request->project_no) {
                $projectNo = $request->project_no;
            } else {
                // Auto generate next
                $start = (int) ($yearShort . '000');
                $end = (int) ($yearShort . '999');
                $last = Project::whereBetween('pn_number', [$start, $end])
                    ->whereRaw('pn_number % 1000 != 0') // exclude pn_number ending with 000
                    ->orderByDesc('pn_number')
                    ->first();
                if ($last) {
                    $lastNumber = (int) substr((string) $last->pn_number, 2);
                    $projectNo = $lastNumber + 1;
                } else {
                    $projectNo = 1;
                }
            }

            $validated['pn_number'] = (int) ($yearShort . str_pad($projectNo, 3, '0', STR_PAD_LEFT));
            $validated['project_number'] = Project::generateProjectNumber($validated['pn_number'], false);

            $project = Project::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Project created successfully',
                'data'    => $project,
            ]);
        } catch (\Throwable $e) {
            FacadesLog::error($e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create project',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableYears(): array
    {
        // Dari Project po_date
        $projectYears = Project::selectRaw('YEAR(po_date) as year')
            ->whereNotNull('po_date')
            ->distinct()
            ->pluck('year')
            ->map(fn($y) => (int)$y) // pastikan integer
            ->toArray();

        // Gabungkan dan unique
        return collect($projectYears)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

}
