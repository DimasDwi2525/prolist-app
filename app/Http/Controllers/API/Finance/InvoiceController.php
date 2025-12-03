<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Models\HoldingTax;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceType;
use App\Models\Project;
use App\Models\Retention;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices by project_id with year filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');
        if (!$projectId) {
            return response()->json(['error' => 'project_id is required'], 400);
        }

        $invoices = Invoice::where('project_id', $projectId)
            ->with(['project', 'invoiceType', 'payments'])
            ->orderBy('invoice_number_in_project', 'asc')
            ->get()
            ->map(function ($invoice) use ($projectId) {
                $invoice->total_payment_amount = $invoice->payments->sum('payment_amount');

                // Calculate remaining project value
                $projectValue = $invoice->project ? $invoice->project->po_value : 0;
                $totalInvoiceValue = Invoice::where('project_id', $projectId)->sum('invoice_value');
                $remainingProjectValue = $projectValue - $totalInvoiceValue;

                $invoice->remaining_project_value = $remainingProjectValue;
                return $invoice;
            });

        $totalPayment = $invoices->sum('total_payment_amount');
        $totalInvoiceValue = $invoices->sum('invoice_value');
        $outstandingPayment = $totalInvoiceValue - $totalPayment;

        return response()->json([
            'invoices' => $invoices,
            'total_payment' => $totalPayment,
            'outstanding_payment' => $outstandingPayment
        ]);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|string',
            'invoice_type_id' => 'nullable|integer',
            'no_faktur' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_description' => 'nullable|string',
            'invoice_value' => 'nullable|numeric',
            'invoice_due_date' => 'nullable|date',
            'payment_status' => 'nullable|in:unpaid,partial,paid',
            'remarks' => 'nullable|string',
            'currency' => 'nullable|in:IDR,USD',
            'rate_usd' => 'nullable|numeric',
            'is_ppn' => 'nullable|boolean',
            'is_pph23' => 'nullable|boolean',
            'is_pph42' => 'nullable|boolean',
            'invoice_sequence' => 'nullable|integer|min:1',
            'nilai_ppn' => 'nullable|numeric',
            'nilai_pph23' => 'nullable|numeric',
            'nilai_pph42' => 'nullable|numeric',
        ]);

        // Fetch project to check value
        $project = Project::findOrFail($request->project_id);

        // Check if adding this invoice would exceed project value
        $currentTotal = Invoice::where('project_id', $request->project_id)->sum('invoice_value');
        if ($currentTotal + $request->invoice_value > $project->po_value) {
            return response()->json(['error' => 'Invoice total exceeds project value'], 400);
        }

        // Determine invoice sequence: custom or auto-generated
        $globalSequence = $request->has('invoice_sequence') && $request->invoice_sequence ? (int)$request->invoice_sequence : $this->getGlobalInvoiceSequence();

        // Check uniqueness globally for the year (across all invoice types)
        $year = date('y'); // e.g., '25'
        $sequencePadded = str_pad($globalSequence, 4, '0', STR_PAD_LEFT);
        $yearSequence = $year . '/' . $sequencePadded;
        if (Invoice::where('invoice_id', 'like', '%' . $yearSequence)->exists()) {
            return response()->json(['error' => 'Invoice sequence already exists'], 400);
        }

        // Generate invoice_id based on template IP25001 (global sequence)
        $invoiceId = $this->generateInvoiceIdGlobal($request->invoice_type_id, $globalSequence);

        $data = $request->all();
        $data['invoice_id'] = $invoiceId;
        $data['invoice_number_in_project'] = $globalSequence; // Keep for backward compatibility, but now global

        $invoice = Invoice::create($data);

        // Calculate taxes and totals
        $this->calculateInvoiceTaxesAndTotals($invoice);

        // Handle holding tax creation if is_pph23 or is_pph42 is true
        $this->handleHoldingTaxCreation($invoice);

        // Set default payment_status if not provided
        if (!isset($data['payment_status'])) {
            $invoice->update(['payment_status' => 'unpaid']);
        }

        return response()->json($invoice, 201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(string $id): JsonResponse
    {
        $invoice = Invoice::with(['project', 'invoiceType', 'payments'])->findOrFail($id);
        $invoice->total_payment_amount = $invoice->payments->sum('payment_amount');
        return response()->json($invoice);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'project_id' => 'sometimes|required|string',
            'invoice_type_id' => 'nullable|integer',
            'no_faktur' => 'nullable|string',
            'invoice_date' => 'nullable|date',
            'invoice_description' => 'nullable|string',
            'invoice_value' => 'nullable|numeric',
            'invoice_due_date' => 'nullable|date',
            'payment_status' => 'nullable|in:unpaid,partial,paid',
            'remarks' => 'nullable|string',
            'currency' => 'nullable|in:IDR,USD',
            'rate_usd' => 'nullable|numeric',
            'is_ppn' => 'nullable|boolean',
            'is_pph23' => 'nullable|boolean',
            'is_pph42' => 'nullable|boolean',
            'nilai_ppn' => 'nullable|numeric',
            'nilai_pph23' => 'nullable|numeric',
            'nilai_pph42' => 'nullable|numeric',
        ]);

        // Check if trying to change project_id and payments exist
        if ($request->has('project_id') && $request->project_id != $invoice->project_id && $invoice->payments()->exists()) {
            return response()->json(['error' => 'Cannot change project when payments exist'], 400);
        }

        // Determine the project_id to use for validation
        $projectIdForValidation = $request->project_id ?? $invoice->project_id;

        // Fetch the project for validation
        $projectForValidation = Project::findOrFail($projectIdForValidation);

        // Calculate current total for the project (excluding current invoice if updating)
        $currentTotal = Invoice::where('project_id', $projectIdForValidation)
            ->where('invoice_id', '!=', $id)
            ->sum('invoice_value');

        // Invoice value to check
        $invoiceValueToCheck = $request->invoice_value ?? $invoice->invoice_value;

        // Check if total would exceed project value
        if ($currentTotal + $invoiceValueToCheck > $projectForValidation->po_value) {
            return response()->json(['error' => 'Invoice total exceeds project value'], 400);
        }

        $data = $request->all();

        // Handle invoice_type_id change: regenerate invoice_id based on new type, keep sequence number
        if ($request->has('invoice_type_id') && $request->invoice_type_id != $invoice->invoice_type_id) {
            $oldInvoiceId = $invoice->invoice_id;
            $sequence = substr($oldInvoiceId, -4); // Extract last 4 characters as sequence (e.g., '0001')

            $year = date('y'); // Current year short

            $newCodeType = '00'; // default
            $newInvoiceType = InvoiceType::find($request->invoice_type_id);
            if ($newInvoiceType) {
                $newCodeType = $newInvoiceType->code_type;
            }

            $newInvoiceId = $newCodeType . '/' . $year . '/' . $sequence; // e.g., 'IP/25/0001'

            DB::transaction(function () use ($oldInvoiceId, $newInvoiceId) {
                // Update all payments to reference the new invoice_id
                InvoicePayment::where('invoice_id', $oldInvoiceId)->update(['invoice_id' => $newInvoiceId]);
                // Update the invoice's invoice_id using raw SQL since it's the primary key
                DB::statement("UPDATE invoices SET invoice_id = ? WHERE invoice_id = ?", [$newInvoiceId, $oldInvoiceId]);
            });

            // Reload the invoice with the new ID
            $invoice = Invoice::find($newInvoiceId);
            // Remove invoice_type_id from data since it's already updated
            unset($data['invoice_type_id']);
        }

        // Remove invoice_id from data to prevent manual updating
        unset($data['invoice_id']);

        $invoice->update($data);

        // Calculate taxes and totals
        $this->calculateInvoiceTaxesAndTotals($invoice);

        // Handle holding tax creation/update if is_pph23 or is_pph42 is true
        $this->handleHoldingTaxCreation($invoice);

        // Update payment_status based on payments
        $totalPaid = $invoice->payments()->sum('payment_amount');
        if ($totalPaid == 0) {
            $invoice->update(['payment_status' => 'unpaid']);
        } elseif ($totalPaid < $invoice->total_invoice ?? $invoice->invoice_value) {
            $invoice->update(['payment_status' => 'partial']);
        } else {
            $invoice->update(['payment_status' => 'paid']);
        }

        return response()->json($invoice);
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(string $id): JsonResponse
    {
        $invoice = Invoice::where('invoice_id', $id)->firstOrFail();

        DB::transaction(function () use ($invoice) {
            // Delete all related payments
            InvoicePayment::where('invoice_id', $invoice->invoice_id)->delete();

            // Delete related holding tax
            HoldingTax::where('invoice_id', $invoice->invoice_id)->delete();

            // Update retention to remove invoice_id without deleting the retention record
            Retention::where('invoice_id', $invoice->invoice_id)->update(['invoice_id' => null]);

            // Finally delete the invoice
            $invoice->delete();
        });

        return response()->json(['message' => 'Invoice deleted successfully']);
    }

    /**
     * Get invoice summary for projects with year filtering.
     */
    public function invoiceSummary(Request $request): JsonResponse
    {
        // 🔹 Ambil daftar tahun dari project (pn_number)
        $availableYears = $this->getAvailableYears();

        // 🔹 Tahun aktif
        $yearParam = $request->query('year');
        $year = $yearParam
            ? (int) $yearParam
            : (!empty($availableYears) ? end($availableYears) : now()->year);

        // 🔹 Filter & range
        $rangeType = $request->query('range_type', 'yearly');
        $month = $request->query('month');
        $from = $request->query('from_date');
        $to = $request->query('to_date');

        // 🔹 Query projects
        $projects = Project::query()
            ->with(['client', 'quotation' => function($q) { $q->with('client'); }, 'paymentRemarks', 'invoices'])
            ->orderByRaw('CAST(LEFT(CAST(pn_number AS VARCHAR), 2) AS INT) DESC') // ambil 2 digit pertama sebagai tahun
            ->orderByRaw('CAST(SUBSTRING(CAST(pn_number AS VARCHAR), 3, LEN(CAST(pn_number AS VARCHAR)) - 2) AS INT) DESC'); // ambil nomor urut

        // 🔹 Filter berdasarkan range
        switch ($rangeType) {
            case 'monthly':
                $projects->whereYearFromPn($year);
                if ($month) {
                    // For monthly, we need to filter by month from pn_number or created_at
                    // Since pn_number doesn't have month, we'll use created_at as fallback
                    $projects->whereMonth('po_date', $month);
                }
                break;

            case 'weekly':
                $projects->whereBetween('po_date', [
                    now()->startOfWeek(), now()->endOfWeek()
                ]);
                break;

            case 'custom':
                if ($from && $to) {
                    $projects->whereBetween('po_date', [$from, $to]);
                }
                break;

            default:
                $projects->whereYearFromPn($year);
                break;
        }

        $projects = $projects->get()
            ->map(function ($project) {
                
                $total_dpp = $project->invoices->sum('invoice_value');
                $invoiceTotal = $project->invoices->sum('total_invoice');
                $paymentTotal = $project->invoices->flatMap->payments->sum('payment_amount');
                $expectedPaymentTotal = $project->invoices->sum('expected_payment');
                $outstandingInvoice = $invoiceTotal - $paymentTotal;
                $outstandingAmount = $project->po_value - $paymentTotal;
                $invoiceProgress = $invoiceTotal > 0 ? round(($paymentTotal / $invoiceTotal) * 100, 2) : 0;

                // Get client name from project->client or project->quotation->client
                $clientName = $project->client ? $project->client->name :
                    ($project->quotation && $project->quotation->client ? $project->quotation->client->name : null);

                // Get remarks from payment_remarks
                $remarks = $project->paymentRemarks->pluck('remark')->implode('; ');

                return [
                    'pn_number' => $project->pn_number,
                    'project_name' => $project->project_name,
                    'client_name' => $clientName,
                    'project_value' => $project->po_value,
                    'invoice_total' => $invoiceTotal,
                    'total_dpp' => $total_dpp,
                    'expected_payment_total' => $expectedPaymentTotal,
                    'payment_total' => $paymentTotal,
                    'outstanding_invoice' => $outstandingInvoice,
                    'outstanding_amount' => $outstandingAmount,
                    'invoice_progress' => $invoiceProgress,
                    'remarks' => $remarks,
                ];
            });

        return response()->json([
            'availableYears' => $availableYears,
            'year' => $year,
            'range_type' => $rangeType,
            'month' => $month,
            'from_date' => $from,
            'to_date' => $to,
            'projects' => $projects,
        ]);
    }

    /**
     * Ambil daftar tahun yang ada di pn_number (unik, ascending)
     */
    private function getAvailableYears(): array
    {
        return Project::selectRaw('LEFT(pn_number, 2) as year_short')
            ->distinct()
            ->pluck('year_short')
            ->map(fn($y) => 2000 + (int)$y)
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Generate invoice_id based on template IP/25/0001 (global sequence)
     * code_type + / + year + / + global sequence
     */
    private function generateInvoiceIdGlobal(?int $invoiceTypeId, int $globalSequence): string
    {
        $year = date('y'); // e.g., '25'

        $codeType = '00'; // default
        if ($invoiceTypeId) {
            $invoiceType = InvoiceType::find($invoiceTypeId);
            if ($invoiceType) {
                $codeType = $invoiceType->code_type;
            }
        }

        return $codeType . '/' . $year . '/' . str_pad($globalSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next global invoice sequence for the year (across all invoice types)
     */
    private function getGlobalInvoiceSequence(): int
    {
        $year = date('y');

        // Find the highest sequence for the year across all invoice types
        $lastInvoice = Invoice::where('invoice_id', 'like', '%' . $year . '/____')
            ->orderByRaw("CAST(SUBSTRING(invoice_id, LEN(invoice_id) - 3, 4) AS INT) DESC")
            ->first();

        if ($lastInvoice) {
            $lastSequence = (int)substr($lastInvoice->invoice_id, -4);
            return $lastSequence + 1;
        }

        return 1;
    }

    /**
     * Calculate taxes, totals, and expected payment for an invoice
     */
    private function calculateInvoiceTaxesAndTotals(Invoice $invoice): void
    {
        $taxes = Tax::all()->keyBy('name');

        $ppnRate = 0;
        $pph23Rate = 0;
        $pph42Rate = 0;

        if ($invoice->is_ppn) {
            $ppnRate = optional($taxes['PPN'])->rate ?? 0.11;
        }
        if ($invoice->is_pph23) {
            $pph23Rate = optional($taxes['PPh 23'])->rate ?? 0.0265;
        }
        if ($invoice->is_pph42) {
            $pph42Rate = optional($taxes['PPh 4(2)'])->rate ?? 0.02;
        }

        $invoiceValue = $invoice->invoice_value;

        // Adjust for USD currency: convert invoice value to IDR first
        if ($invoice->currency === 'USD' && $invoice->rate_usd) {
            $invoiceValue *= $invoice->rate_usd;
        }

        // Use manual values if provided, otherwise calculate automatically
        $nilaiPpn = $invoice->nilai_ppn ?? ($invoiceValue * $ppnRate);
        $nilaiPph23 = $invoice->nilai_pph23 ?? ($invoiceValue * $pph23Rate);
        $nilaiPph42 = $invoice->nilai_pph42 ?? ($invoiceValue * $pph42Rate);

        $totalInvoice = $invoiceValue;
        if ($invoice->is_ppn) {
            $totalInvoice += $nilaiPpn;
        }

        $expectedPayment = $totalInvoice - $nilaiPph23 - $nilaiPph42;

        $invoice->update([
            'ppn_rate' => $ppnRate,
            'pph23_rate' => $pph23Rate,
            'pph42_rate' => $pph42Rate,
            'nilai_ppn' => $nilaiPpn,
            'nilai_pph23' => $nilaiPph23,
            'nilai_pph42' => $nilaiPph42,
            'total_invoice' => $totalInvoice,
            'expected_payment' => $expectedPayment,
        ]);
    }

    /**
     * Get the next invoice_id for a given invoice type.
     */
    public function nextInvoiceId(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_type_id' => 'nullable|integer',
            'invoice_sequence' => 'nullable|integer|min:1',
        ]);

        $globalSequence = $request->has('invoice_sequence') && $request->invoice_sequence ? (int)$request->invoice_sequence : $this->getGlobalInvoiceSequence();
        $nextInvoiceId = $this->generateInvoiceIdGlobal($request->invoice_type_id, $globalSequence);

        return response()->json(['next_invoice_id' => $nextInvoiceId]);
    }

    /**
     * Validate if an invoice sequence is available globally for the year.
     */
    public function validateSequence(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_type_id' => 'nullable|integer',
            'invoice_sequence' => 'required|integer|min:1',
        ]);

        $year = date('y');
        $sequencePadded = str_pad($request->invoice_sequence, 4, '0', STR_PAD_LEFT);
        $yearSequence = $year . '/' . $sequencePadded;

        // Check if any invoice has the same year + sequence (last 6 characters)
        $exists = Invoice::where('invoice_id', 'like', '%' . $yearSequence)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Sequence already exists' : 'Sequence is available'
        ]);
    }

    /**
     * Display a listing of all invoices with filtering by year and range.
     */
    public function invoiceList(Request $request): JsonResponse
    {
        // 🔹 Ambil daftar tahun dari invoices created_at
        $availableYears = $this->getAvailableYearsInvoices();

        // 🔹 Tahun aktif
        $yearParam = $request->query('year');
        $year = $yearParam
            ? (int) $yearParam
            : (!empty($availableYears) ? end($availableYears) : now()->year);

        // 🔹 Filter & range
        $rangeType = $request->query('range_type', 'yearly');
        $month = $request->query('month');
        $from = $request->query('from_date');
        $to = $request->query('to_date');

        // 🔹 Query invoices
        $invoices = Invoice::query()
            ->with([
                'project' => function ($query) {
                    $query->select('pn_number', 'project_name', 'client_id', 'quotations_id', 'project_number', 'po_value', 'po_number')
                        ->with(['client', 'quotation' => function($q) { $q->with('client'); }]);
                },
                'invoiceType' => function ($query) {
                    $query->select('id', 'code_type');
                },
                'payments' => function ($query) {
                    $query->select('invoice_id', 'payment_amount');
                }
            ])
            ->orderByRaw("CAST(RIGHT(invoice_id, 4) AS INT) DESC");

        // 🔹 Filter berdasarkan range
        switch ($rangeType) {
            case 'monthly':
                $invoices->whereYear('invoice_date', $year);
                if ($month) {
                    $invoices->whereMonth('invoice_date', $month);
                }
                break;

            case 'weekly':
                $invoices->whereBetween('invoice_date', [
                    now()->startOfWeek(), now()->endOfWeek()
                ]);
                break;

            case 'custom':
                if ($from && $to) {
                    $invoices->whereBetween('invoice_date', [$from, $to]);
                }
                break;

            default:
                $invoices->whereYear('invoice_date', $year);
                break;
        }

        $invoices = $invoices->get()
            ->map(function ($invoice) {
                $totalPaymentAmount = $invoice->payments->sum('payment_amount');
                $paymentPercentage = $invoice->invoice_value > 0 ? round(($totalPaymentAmount / $invoice->invoice_value) * 100, 2) : 0;

                return [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_number_in_project' => $invoice->invoice_number_in_project,
                    'project_id' => $invoice->project_id,
                    'project_number' => $invoice->project ? $invoice->project->project_number : null,
                    'project_name' => $invoice->project ? $invoice->project->project_name : null,
                    'client_name' => $invoice->project ? ($invoice->project->client ? $invoice->project->client->name : ($invoice->project->quotation && $invoice->project->quotation->client ? $invoice->project->quotation->client->name : null)) : null,
                    'po_value' => $invoice->project ? $invoice->project->po_value : null,
                    'po_number' => $invoice->project ? $invoice->project->po_number : null,
                    'invoice_type' => $invoice->invoiceType ? $invoice->invoiceType->code_type : null,
                    'no_faktur' => $invoice->no_faktur,
                    'invoice_date' => $invoice->invoice_date,
                    'invoice_description' => $invoice->invoice_description,
                    'invoice_value' => $invoice->invoice_value,
                    'invoice_due_date' => $invoice->invoice_due_date,
                    'payment_status' => $invoice->payment_status,
                    'total_payment_amount' => $totalPaymentAmount,
                    'payment_percentage' => $paymentPercentage,
                    'remarks' => $invoice->remarks,
                    'currency' => $invoice->currency,
                    'created_at' => $invoice->created_at,
                ];
            });

        // Calculate totals
        $totalInvoices = $invoices->count();
        $totalInvoiceValue = $invoices->sum('invoice_value');

        return response()->json([
            'status' => 'success',
            'availableYears' => $availableYears,
            'year' => $year,
            'range_type' => $rangeType,
            'month' => $month,
            'from_date' => $from,
            'to_date' => $to,
            'total_invoices' => $totalInvoices,
            'total_invoice_value' => $totalInvoiceValue,
            'data' => $invoices,
        ]);
    }

    /**
     * Ambil daftar tahun yang ada di invoices created_at (unik, ascending)
     */
    private function getAvailableYearsInvoices(): array
    {
        return Invoice::selectRaw('YEAR(invoice_date) as year')
            ->distinct()
            ->pluck('year')
            ->map(fn($y) => (int)$y)
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Preview tax calculations for invoice creation/update.
     */
    public function previewTaxes(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_value' => 'required|numeric',
            'currency' => 'nullable|in:IDR,USD',
            'rate_usd' => 'nullable|numeric',
            'is_ppn' => 'nullable|in:0,1,true,false',
            'is_pph23' => 'nullable|in:0,1,true,false',
            'is_pph42' => 'nullable|in:0,1,true,false',
        ]);

        $taxes = Tax::all()->keyBy('name');

        $ppnRate = 0;
        $pph23Rate = 0;
        $pph42Rate = 0;

        if ($request->boolean('is_ppn')) {
            $ppnRate = optional($taxes['PPN'])->rate ?? 0.11;
        }
        if ($request->boolean('is_pph23')) {
            $pph23Rate = optional($taxes['PPh 23'])->rate ?? 0.0265;
        }
        if ($request->boolean('is_pph42')) {
            $pph42Rate = optional($taxes['PPh 4(2)'])->rate ?? 0.02;
        }

        $invoiceValue = $request->invoice_value;

        // Adjust for USD currency: convert invoice value to IDR first
        if ($request->currency === 'USD' && $request->rate_usd) {
            $invoiceValue *= $request->rate_usd;
        }

        $nilaiPpn = $invoiceValue * $ppnRate;
        $nilaiPph23 = $invoiceValue * $pph23Rate;
        $nilaiPph42 = $invoiceValue * $pph42Rate;

        $totalInvoice = $invoiceValue;
        if ($request->is_ppn) {
            $totalInvoice += $nilaiPpn;
        }

        $expectedPayment = $totalInvoice - $nilaiPph23 - $nilaiPph42;

        return response()->json([
            'ppn_rate' => $ppnRate,
            'pph23_rate' => $pph23Rate,
            'pph42_rate' => $pph42Rate,
            'nilai_ppn' => $nilaiPpn,
            'nilai_pph23' => $nilaiPph23,
            'nilai_pph42' => $nilaiPph42,
            'total_invoice' => $totalInvoice,
            'expected_payment' => $expectedPayment,
        ]);
    }

    /**
     * Handle holding tax creation or update for an invoice.
     */
    private function handleHoldingTaxCreation(Invoice $invoice): void
    {
        // Delete existing holding tax for this invoice
        HoldingTax::where('invoice_id', $invoice->invoice_id)->delete();

        // Check if is_pph23 or is_pph42 is true
        if ($invoice->is_pph23 || $invoice->is_pph42) {
            $holdingTaxData = [
                'invoice_id' => $invoice->invoice_id,
                'no_bukti_potong' => null, // Can be set later
                'tanggal_wht' => null, // Can be set later
            ];

            // Set rates and values based on which taxes are enabled
            if ($invoice->is_pph23) {
                $holdingTaxData['pph23_rate'] = $invoice->pph23_rate;
                $holdingTaxData['nilai_pph23'] = $invoice->nilai_pph23;
            }

            if ($invoice->is_pph42) {
                $holdingTaxData['pph42_rate'] = $invoice->pph42_rate;
                $holdingTaxData['nilai_pph42'] = $invoice->nilai_pph42;
            }

            // Calculate nilai_potongan as sum of nilai_pph23 and nilai_pph42
            $holdingTaxData['nilai_potongan'] = ($invoice->nilai_pph23 ?? 0) + ($invoice->nilai_pph42 ?? 0);

            // Create the holding tax record
            HoldingTax::create($holdingTaxData);
        }
    }

    /**
     * Validate invoice creation/update without actually performing the action.
     */
    public function validateInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'project_id' => 'required|string',
            'invoice_value' => 'required|numeric',
            'invoice_id' => 'nullable|string', // for updates
        ], [], [
            'project_id' => 'project_id',
            'invoice_value' => 'invoice_value',
            'invoice_id' => 'invoice_id',
        ]);

        $project = Project::findOrFail($request->project_id);

        // Calculate current total for the project
        $currentTotal = Invoice::where('project_id', $request->project_id)->sum('invoice_value');

        // If updating, exclude current invoice
        if ($request->invoice_id) {
            $currentInvoice = Invoice::find($request->invoice_id);
            if ($currentInvoice && $currentInvoice->project_id === $request->project_id) {
                $currentTotal -= $currentInvoice->invoice_value;
            }
        }

        $newTotal = $currentTotal + $request->invoice_value;

        if ($newTotal > $project->po_value) {
            return response()->json([
                'valid' => false,
                'message' => 'Invoice total exceeds project value',
                'current_total' => $currentTotal,
                'new_total' => $newTotal,
                'project_value' => $project->po_value,
                'exceeds_by' => $newTotal - $project->po_value
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Invoice value is within project limits',
            'current_total' => $currentTotal,
            'new_total' => $newTotal,
            'project_value' => $project->po_value
        ]);
    }
}
