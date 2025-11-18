<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RequestInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Request invoice count
        $requestInvoiceCount = RequestInvoice::where('status', 'pending')->count();

        // Request invoice list with details
        $requestInvoiceList = RequestInvoice::where('status', 'pending')
            ->with(['project', 'requestedBy', 'documents.documentPreparation.document'])
            ->get()
            ->map(function ($invoice) {
                return [
                    'request_number' => $invoice->request_number,
                    'project_name' => $invoice->project ? $invoice->project->project_name : null,
                    'status' => $invoice->status,
                    'requested_by_name' => $invoice->requestedBy ? $invoice->requestedBy->name : null,
                    'documents' => $invoice->documents->map(function ($doc) {
                        return [
                            'name' => $doc->documentPreparation && $doc->documentPreparation->document ? $doc->documentPreparation->document->name : null,
                            'attachment_path' => $doc->documentPreparation ? $doc->documentPreparation->attachment_path : null,
                            'notes' => $doc->notes,
                        ];
                    }),
                ];
            });

        // Jumlah PN (projects)
        $projectCount = Project::count();

        // Total delivery order count
        $totalDeliveryOrder = DeliveryOrder::count();

        // Total invoice value
        $totalInvoice = Invoice::sum('total_invoice');

        // Invoice outstanding (total invoice - total payments)
        $invoiceOutstanding = Invoice::with('payments')->get()->sum(function ($invoice) {
            return $invoice->expected_payment - $invoice->payments->sum('payment_amount');
        });

        // Invoice due date - count of invoices past due
        $invoiceDueCount = Invoice::where('invoice_due_date', '<', now())->count();

        // Summary invoice vs PN with payment < 100%
        $incompletePayments = Project::with(['invoices.payments', 'client', 'quotation.client'])
            ->get()
            ->filter(function ($project) {
                $totalInvoice = $project->invoices->sum('total_invoice');
                $totalPayment = $project->invoices->flatMap->payments->sum('payment_amount');
                return $totalInvoice > 0 && ($totalPayment / $totalInvoice) < 1;
            })
            ->map(function ($project) {
                $totalInvoice = $project->invoices->sum('total_invoice');
                $totalPayment = $project->invoices->flatMap->payments->sum('payment_amount');
                $percentage = $totalInvoice > 0 ? ($totalPayment / $totalInvoice) * 100 : 0;

                // Get client name with fallback
                $clientName = $project->client ? $project->client->name : ($project->quotation && $project->quotation->client ? $project->quotation->client->name : null);

                return [
                    'pn_number' => $project->pn_number,
                    'project_name' => $project->project_name,
                    'client_name' => $clientName,
                    'total_invoice' => $totalInvoice,
                    'total_payment' => $totalPayment,
                    'payment_percentage' => round($percentage, 2),
                ];
            });

        return response()->json([
            'request_invoice' => $requestInvoiceCount,
            'request_invoice_list' => $requestInvoiceList,
            'jumlah_pn' => $projectCount,
            'total_delivery_order' => $totalDeliveryOrder,
            'total_invoice' => $totalInvoice,
            'invoice_outstanding' => $invoiceOutstanding,
            'invoice_due_count' => $invoiceDueCount,
            'incomplete_payments_summary' => $incompletePayments,
        ]);
    }
}
