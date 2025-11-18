<?php

namespace App\Http\Controllers\API\Finance;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryOrderController extends Controller
{
    /**
     * Display a listing of delivery orders.
     */
    public function index(Request $request): JsonResponse
    {
        $deliveryOrders = DeliveryOrder::with('project')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($deliveryOrders);
    }

    /**
     * Store a newly created delivery order.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'do_description' => 'nullable|string',
            'pn_id' => 'required|integer|exists:projects,pn_number',
            'return_date' => 'nullable|date',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'do_send' => 'nullable|date',
        ]);

        // Generate do_number and do_no
        $year = date('y'); // e.g., '25'
        $prefix = 'SP' . $year;
        $doPrefix = 'SP/' . $year . '/';

        // Find the max number for the current year
        $maxDoNumber = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(do_number, LENGTH(?) + 1) AS UNSIGNED)) as max_num', [$prefix])
            ->value('max_num') ?? 0;

        $nextNum = $maxDoNumber + 1;
        $paddedNum = str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $doNumber = $prefix . $paddedNum;
        $doNo = $doPrefix . $paddedNum;

        $data = $request->all();
        $data['do_number'] = $doNumber;
        $data['do_no'] = $doNo;

        $deliveryOrder = DeliveryOrder::create($data);

        return response()->json($deliveryOrder, 201);
    }

    /**
     * Display the specified delivery order.
     */
    public function show(string $id): JsonResponse
    {
        $deliveryOrder = DeliveryOrder::with('project')->findOrFail($id);
        return response()->json($deliveryOrder);
    }

    /**
     * Update the specified delivery order.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $deliveryOrder = DeliveryOrder::findOrFail($id);

        $request->validate([
            'do_number' => 'sometimes|required|string|unique:delivery_orders,do_number,' . $id . ',do_number',
            'do_no' => 'sometimes|required|string',
            'do_description' => 'nullable|string',
            'pn_id' => 'sometimes|required|integer|exists:projects,pn_number',
            'return_date' => 'nullable|date',
            'invoice_id' => 'nullable|integer|exists:invoices,id',
            'do_send' => 'nullable|date',
        ]);

        $deliveryOrder->update($request->all());

        return response()->json($deliveryOrder);
    }

    /**
     * Remove the specified delivery order.
     */
    public function destroy(string $id): JsonResponse
    {
        $deliveryOrder = DeliveryOrder::findOrFail($id);
        $deliveryOrder->delete();

        return response()->json(['message' => 'Delivery order deleted successfully']);
    }
}
