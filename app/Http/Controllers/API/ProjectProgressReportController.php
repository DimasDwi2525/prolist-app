<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectProgressReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->select([
                'pn_number',
                'project_number',
                'project_name',
                'client_id',
                'quotations_id',
                'po_value',
                'phc_dates',
                'project_progress',
            ])
            ->selectSub(function ($query) {
                $query->from('invoices')
                    ->selectRaw('COALESCE(SUM(COALESCE(total_invoice, invoice_value, 0)), 0)')
                    ->whereColumn('invoices.project_id', 'projects.pn_number');
            }, 'total_invoice_amount')
            ->selectSub(function ($query) {
                $query->from('invoices')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invoices.project_id', 'projects.pn_number');
            }, 'invoice_count')
            ->selectSub(function ($query) {
                $query->from('invoices')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invoices.project_id', 'projects.pn_number')
                    ->where('payment_status', 'partial');
            }, 'partial_invoice_count')
            ->selectSub(function ($query) {
                $query->from('invoices')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invoices.project_id', 'projects.pn_number')
                    ->where('payment_status', 'unpaid');
            }, 'unpaid_invoice_count')
            ->selectSub(function ($query) {
                $query->from('invoices')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invoices.project_id', 'projects.pn_number')
                    ->where('payment_status', 'paid');
            }, 'paid_invoice_count')
            ->with([
                'client:id,name',
                'quotation:quotation_number,client_id',
                'quotation.client:id,name',
            ]);

        $this->applyFilters($projects, $request);

        $projects = $projects
            ->orderByDesc('pn_number')
            ->paginate($this->getPerPage($request))
            ->withQueryString();

        $data = $projects->getCollection()->map(function (Project $project) {
            $poValue = (float) ($project->po_value ?? 0);
            $totalInvoice = (float) ($project->total_invoice_amount ?? 0);

            return [
                'pn' => $project->project_number,
                'pn_number' => $project->pn_number,
                'client' => $project->client->name
                    ?? $project->quotation?->client?->name,
                'project_name' => $project->project_name,
                'po_value' => $poValue,
                'phc_date' => $project->phc_dates,
                'progress_project' => $project->project_progress !== null
                    ? (float) $project->project_progress
                    : 0,
                'progress_material' => 0,
                'progress_invoice' => $poValue > 0
                    ? round(($totalInvoice / $poValue) * 100, 2)
                    : 0,
                'status_payment' => $this->resolvePaymentStatus($project),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Project progress report fetched successfully',
            'data' => $data,
            'meta' => $this->paginationMeta($projects),
            'filters' => [
                'search' => $request->query('search'),
                'client_id' => $request->query('client_id'),
                'payment_status' => $request->query('payment_status'),
                'project_progress_min' => $request->query('project_progress_min'),
                'project_progress_max' => $request->query('project_progress_max'),
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
                    });
            });
        }

        $query
            ->when($request->filled('client_id'), function ($q) use ($request) {
                $q->where('client_id', $request->query('client_id'));
            })
            ->when($request->filled('payment_status'), function ($q) use ($request) {
                $q->whereHas('invoices', function ($invoiceQuery) use ($request) {
                    $invoiceQuery->where('payment_status', $request->query('payment_status'));
                });
            })
            ->when($request->filled('project_progress_min'), function ($q) use ($request) {
                $q->where('project_progress', '>=', $request->query('project_progress_min'));
            })
            ->when($request->filled('project_progress_max'), function ($q) use ($request) {
                $q->where('project_progress', '<=', $request->query('project_progress_max'));
            });
    }

    private function resolvePaymentStatus(Project $project): ?string
    {
        if ((int) $project->invoice_count === 0) {
            return null;
        }

        if ((int) $project->partial_invoice_count > 0) {
            return 'partial';
        }

        if ((int) $project->unpaid_invoice_count > 0) {
            return 'partial';
        }

        if ((int) $project->paid_invoice_count === (int) $project->invoice_count) {
            return 'paid';
        }

        return 'unpaid';
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
