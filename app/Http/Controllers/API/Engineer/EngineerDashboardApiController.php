<?php

namespace App\Http\Controllers\API\Engineer;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\StatusProject;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EngineerDashboardApiController extends Controller
{
    //
    public function index()
    {
        $now = Carbon::now();

        $kpis = $this->getKpis($now);
        $charts = $this->getCharts($now);
        $workOrders = $this->getWorkOrdersThisMonth();
        $projects = $this->getProjectLists($now);

        return response()->json(array_merge($kpis, $charts, $projects, $workOrders));
    }

    private function getKpis(Carbon $now)
    {
        $overdue = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '<', $now);
        }, true);

        $dueThisMonth = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')
              ->whereMonth('target_finish_date', $now->month)
              ->whereYear('target_finish_date', $now->year)
              ->where('target_finish_date', '>=', $now);
        }, true);

        $onTrack = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '>=', $now);
        }, true);

        $totalOutstanding = $overdue['count'] + $dueThisMonth['count'] + $onTrack['count'];

        return [
            'projectOverdue'         => $overdue['count'],
            'projectDueThisMonth'    => $dueThisMonth['count'],
            'projectOnTrack'         => $onTrack['count'],
            'totalOutstandingProjects' => $totalOutstanding,
            'totalActiveProjects'    => Project::count(),
            'totalWorkOrders'        => WorkOrder::whereMonth('wo_date', $now->month)->whereYear('wo_date', $now->year)->where('status', 'finished')->count(),
        ];
    }

    private function getProjectsByCriteria(Carbon $now, callable $phcFilter, bool $excludeFinished = false)
    {
        $query = Project::whereHas('phc', $phcFilter);

        if ($excludeFinished) {
            $query->whereHas('statusProject', function($q) {
                $q->whereNotIn('name', ['Engineering Work Completed', 'Project Finished', 'Invoice On Progress', 'Documents Completed', 'Cancelled']);
            });
        }

        $count = $query->count();

        $listQuery = clone $query;
        $list = $listQuery->with(['statusProject', 'phc', 'client', 'quotation', 'logs' => function($q) {
                $q->orderBy('created_at', 'desc');
            }])
            ->get()
            ->map(function ($p) {
                $latestLog = $p->logs->first();
                return [
                    'pn_number'     => $p->pn_number,
                    'project_number' => $p->project_number,
                    'project_name'  => $p->project_name,
                    'client_name'   => $p->client->name ?? $p->quotation->client->name ?? '-',
                    'target_dates'  => $p->phc->target_finish_date,
                    'status'        => $p->statusProject->name ?? '-',
                    'pic'           => $p->phc?->picEngineering?->name ?? '-',
                    'latest_log'    => $latestLog ? $latestLog->logs : null,
                ];
            });

        return [
            'count' => $count,
            'list'  => $list,
        ];
    }

    private function getCharts(Carbon $now)
    {
        // Get current year from pn_number (assuming pn_number starts with year like '25' for 2025)
        $currentYear = Project::selectRaw('LEFT(pn_number, 2) as year_short')
            ->distinct()
            ->orderBy('year_short', 'desc')
            ->first()
            ->year_short ?? date('y');

        $year = '20' . $currentYear; // e.g., '2025'

        // Line Chart: Completed projects per month - On Time and Late
        $onTimeProjects = Project::select([
            DB::raw("YEAR(engineering_finish_date) as year"),
            DB::raw("MONTH(engineering_finish_date) as month"),
            DB::raw("COUNT(*) as total")
        ])
        ->whereNotNull('engineering_finish_date')
        ->whereYear('engineering_finish_date', $year)
        ->whereHas('phc', function($q) {
            $q->whereRaw('engineering_finish_date <= target_finish_date');
        })
        ->groupBy(DB::raw("YEAR(engineering_finish_date), MONTH(engineering_finish_date)"))
        ->orderBy(DB::raw("MIN(engineering_finish_date)"))
        ->get()
        ->mapWithKeys(function ($row) {
            $month = sprintf("%04d-%02d", $row->year, $row->month);
            return [$month => $row->total];
        });

        $lateProjects = Project::select([
            DB::raw("YEAR(engineering_finish_date) as year"),
            DB::raw("MONTH(engineering_finish_date) as month"),
            DB::raw("COUNT(*) as total")
        ])
        ->whereNotNull('engineering_finish_date')
        ->whereYear('engineering_finish_date', $year)
        ->whereHas('phc', function($q) {
            $q->whereRaw('engineering_finish_date > target_finish_date');
        })
        ->groupBy(DB::raw("YEAR(engineering_finish_date), MONTH(engineering_finish_date)"))
        ->orderBy(DB::raw("MIN(engineering_finish_date)"))
        ->get()
        ->mapWithKeys(function ($row) {
            $month = sprintf("%04d-%02d", $row->year, $row->month);
            return [$month => $row->total];
        });

        $months = collect(range(1, 12))->map(function ($i) use ($year, $onTimeProjects, $lateProjects) {
            $month = sprintf("%04d-%02d", $year, $i);
            return [
                'label' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'onTime' => $onTimeProjects[$month] ?? 0,
                'late' => $lateProjects[$month] ?? 0,
            ];
        });

        // Pie Chart: Outstanding Projects
        $overdueCount = Project::whereHas('phc', function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '<', $now);
        })->whereHas('statusProject', function($q) {
            $q->whereNotIn('name', ['Engineering Work Completed', 'Project Finished', 'Invoice On Progress', 'Documents Completed', 'Cancelled']);
        })->count();

        $dueThisMonthCount = Project::whereHas('phc', function($q) use ($now) {
            $q->whereNotNull('target_finish_date')
              ->whereMonth('target_finish_date', $now->month)
              ->whereYear('target_finish_date', $now->year)
              ->where('target_finish_date', '>=', $now);
        })->whereHas('statusProject', function($q) {
            $q->whereNotIn('name', ['Engineering Work Completed', 'Project Finished', 'Invoice On Progress', 'Documents Completed', 'Cancelled']);
        })->count();

        $onTrackCount = Project::whereHas('phc', function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '>=', $now);
        })->whereHas('statusProject', function($q) {
            $q->whereNotIn('name', ['Engineering Work Completed', 'Project Finished', 'Invoice On Progress', 'Documents Completed', 'Cancelled']);
        })->count();

        return [
            'months'             => $months->pluck('label'),
            'onTimeProjects'     => $months->pluck('onTime'),
            'lateProjects'       => $months->pluck('late'),
            'statusLabels'       => ['Overdue', 'Due This Month', 'On Track'],
            'statusCounts'       => [$overdueCount, $dueThisMonthCount, $onTrackCount],
        ];
    }

    private function getProjectLists(Carbon $now)
    {
        // Upcoming Projects
        $upcomingProjects = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')
              ->where('target_finish_date', '>', $now)
              ->where('target_finish_date', '<=', $now->copy()->addDays(30));
        }, true)['list'];

        $projectDueThisMonthList = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')
              ->whereMonth('target_finish_date', $now->month)
              ->whereYear('target_finish_date', $now->year)
              ->where('target_finish_date', '>=', $now);
        }, true)['list'];

        $projectOnTrackList = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '>=', $now);
        }, true)['list'];

        // Top 5 Overdue Projects
        $top5Overdue = $this->getProjectsByCriteria($now, function($q) use ($now) {
            $q->whereNotNull('target_finish_date')->where('target_finish_date', '<', $now);
        }, true)['list']->map(function ($p) use ($now) {
            return [
                'pn_number'     => $p['pn_number'],
                'project_number' => $p['project_number'],
                'project_name'  => $p['project_name'],
                'client_name'   => $p['client_name'],
                'target_dates'  => $p['target_dates'],
                'delay_days'    => Carbon::parse($p['target_dates'])->diffInDays($now),
                'status'        => $p['status'],
                'pic'           => $p['pic'],
                'latest_log'    => $p['latest_log'],
            ];
        })->sortByDesc('delay_days')
        // ->take(5)
        ->values();

        return [
            'upcomingProjects' => $upcomingProjects,
            'projectDueThisMonthList' => $projectDueThisMonthList,
            'top5Overdue'      => $top5Overdue,
            'projectOnTrackList' => $projectOnTrackList,
        ];
    }

    private function getWorkOrdersThisMonth()
    {
        return [
            'workOrdersThisMonth' => WorkOrder::whereMonth('wo_date', now()->month)
                ->whereYear('wo_date', now()->year)
                ->with(['pics.user', 'descriptions', 'project.client'])
                ->get()
                ->map(function ($wo) {
                    return [
                        'wo_kode_no' => $wo->wo_kode_no,
                        'project_name' => $wo->project->project_name ?? '-',
                        'client_name' => $wo->project->client->name ?? $wo->project->quotation->client->name,
                        'created_by' => $wo->creator?->name ?? '-',
                        'pic_names' => $wo->pics->pluck('user.name')->join(', '),
                        'descriptions' => $wo->descriptions->pluck('description')->join('; '),
                        'results' => $wo->descriptions->pluck('result')->join('; '),
                        'wo_date' => $wo->wo_date,
                    ];
                }),
        ];
    }
}
