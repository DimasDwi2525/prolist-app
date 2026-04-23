<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectEngineeringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->select([
                'pn_number',
                'project_number',
                'project_name',
                'categories_project_id',
                'client_id',
                'quotations_id',
                'phc_dates',
                'target_dates',
                'project_progress',
                'status_project_id',
            ])
            ->with([
                'category:id,name',
                'client:id,name',
                'quotation:quotation_number,client_id',
                'quotation.client:id,name',
                'phc:project_id,target_finish_date',
                'statusProject:id,name',
            ]);

        $this->applyFilters($projects, $request);

        $projects = $projects
            ->orderByDesc('pn_number')
            ->paginate($this->getPerPage($request))
            ->withQueryString();

        $data = $projects->getCollection()->map(function (Project $project) {
            return [
                'pn_number' => $project->pn_number,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'category' => $project->category?->name,
                'client' => $project->client->name
                    ?? $project->quotation?->client?->name,
                'phc_date' => $project->phc_dates,
                'target_date' => $project->phc?->target_finish_date
                    ?? $project->target_dates,
                'progress_project' => $project->project_progress !== null
                    ? (float) $project->project_progress
                    : 0,
                'status_project' => $project->statusProject?->name,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Project engineering data fetched successfully',
            'data' => $data,
            'meta' => $this->paginationMeta($projects),
            'filters' => [
                'search' => $request->query('search'),
                'category_id' => $request->query('category_id'),
                'client_id' => $request->query('client_id'),
                'status_project_id' => $request->query('status_project_id'),
                'target_date_from' => $request->query('target_date_from'),
                'target_date_to' => $request->query('target_date_to'),
            ],
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('project_number', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(pn_number AS VARCHAR(20)) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('quotation.client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('category', function ($categoryQuery) use ($search) {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('statusProject', function ($statusQuery) use ($search) {
                        $statusQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $query
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('categories_project_id', $request->query('category_id'));
            })
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('client_id', $request->query('client_id'));
            })
            ->when($request->filled('status_project_id'), function ($q) use ($request) {
                $q->where('status_project_id', $request->query('status_project_id'));
            })
            ->when($request->filled('target_date_from'), function ($q) use ($request) {
                $q->where(function ($targetQuery) use ($request) {
                    $targetQuery->where('target_dates', '>=', $request->query('target_date_from'))
                        ->orWhereHas('phc', function ($phcQuery) use ($request) {
                            $phcQuery->where('target_finish_date', '>=', $request->query('target_date_from'));
                        });
                });
            })
            ->when($request->filled('target_date_to'), function ($q) use ($request) {
                $q->where(function ($targetQuery) use ($request) {
                    $targetQuery->where('target_dates', '<=', $request->query('target_date_to'))
                        ->orWhereHas('phc', function ($phcQuery) use ($request) {
                            $phcQuery->where('target_finish_date', '<=', $request->query('target_date_to'));
                        });
                });
            });
    }

    private function getPerPage(Request $request): int
    {
        return min(max((int) $request->query('per_page', 15), 1), 100);
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
