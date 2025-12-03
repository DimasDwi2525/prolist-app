<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoicePaymentController extends Controller
{
    /**
     * Display a listing of payments for a specific invoice.
     */
    public function index(Request $request): JsonResponse
    {
        $invoiceId = $request->query('invoice_id');
        if (!$invoiceId) {
            return response()->json(['error' => 'invoice_id is required'], 400);
        }

        $payments = InvoicePayment::where('invoice_id', $invoiceId)
            ->with('invoice')
            ->orderBy('payment_number')
            ->get();

        $invoice = Invoice::findOrFail($invoiceId);
        $totalPaid = $payments->sum('payment_amount');
        $remainingPayment = $invoice->invoice_value - $totalPaid;

        return response()->json([
            'payments' => $payments,
            'total_paid' => $totalPaid,
            'remaining_payment' => $remainingPayment,
            'expected_payment' => $invoice->invoice_value
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|string|exists:invoices,invoice_id',
            'payment_date' => 'required|date',
            'payment_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'currency' => 'nullable|in:IDR,USD',
            'nomor_bukti_pembayaran' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Check if adding this payment would exceed invoice value
        $currentTotal = InvoicePayment::where('invoice_id', $request->invoice_id)->sum('payment_amount');
        if ($currentTotal + $request->payment_amount > $invoice->invoice_value) {
            return response()->json(['error' => 'Payment total exceeds invoice value'], 400);
        }

        // Get next payment number
        $nextPaymentNumber = InvoicePayment::where('invoice_id', $request->invoice_id)
            ->max('payment_number') + 1;

        $data = $request->all();
        $data['payment_number'] = $nextPaymentNumber;

        DB::transaction(function () use ($data) {
            InvoicePayment::create($data);
        });

        return response()->json(['message' => 'Payment created successfully'], 201);
    }

    /**
     * Display the specified payment.
     */
    public function show(string $id): JsonResponse
    {
        $payment = InvoicePayment::with('invoice')->findOrFail($id);
        return response()->json($payment);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $payment = InvoicePayment::findOrFail($id);

        $request->validate([
            'payment_date' => 'sometimes|required|date',
            'payment_amount' => 'sometimes|required|numeric|min:0',
            'notes' => 'nullable|string',
            'currency' => 'nullable|in:IDR,USD',
            'nomor_bukti_pembayaran' => 'nullable|string',
        ]);

        // If payment_amount is being updated, check against invoice value
        if ($request->has('payment_amount')) {
            $invoice = $payment->invoice;
            $currentTotal = InvoicePayment::where('invoice_id', $payment->invoice_id)->where('id', '!=', $id)->sum('payment_amount');
            if ($currentTotal + $request->payment_amount > $invoice->invoice_value) {
                return response()->json(['error' => 'Payment total exceeds invoice value'], 400);
            }
        }

        $payment->update($request->only(['payment_date', 'payment_amount', 'notes', 'currency', 'nomor_bukti_pembayaran']));

        return response()->json($payment);
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(string $id): JsonResponse
    {
        $payment = InvoicePayment::findOrFail($id);
        $invoice = $payment->invoice;

        $payment->delete();

        return response()->json(['message' => 'Payment deleted successfully']);
    }

    /**
     * Validate payment creation/update without actually performing the action.
     */
    public function validatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|string',
            'payment_amount' => 'required|numeric',
            'payment_id' => 'nullable|string', // for updates
        ], [], [
            'invoice_id' => 'invoice_id',
            'payment_amount' => 'payment_amount',
            'payment_id' => 'payment_id',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        // Calculate current total for the invoice
        $currentTotal = InvoicePayment::where('invoice_id', $request->invoice_id)->sum('payment_amount');

        // If updating, exclude current payment
        if ($request->payment_id) {
            $currentPayment = InvoicePayment::find($request->payment_id);
            if ($currentPayment && $currentPayment->invoice_id === $request->invoice_id) {
                $currentTotal -= $currentPayment->payment_amount;
            }
        }

        $newTotal = $currentTotal + $request->payment_amount;

        if ($newTotal > $invoice->invoice_value) {
            return response()->json([
                'valid' => false,
                'message' => 'Payment total exceeds invoice value',
                'current_total' => $currentTotal,
                'new_total' => $newTotal,
                'expected_value' => $invoice->invoice_value,
                'exceeds_by' => $newTotal - $invoice->invoice_value
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Payment amount is within invoice limits',
            'current_total' => $currentTotal,
            'new_total' => $newTotal,
            'expected_value' => $invoice->invoice_value
        ]);
    }
}
