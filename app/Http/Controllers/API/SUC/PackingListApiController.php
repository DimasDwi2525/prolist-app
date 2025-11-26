<?php

namespace App\Http\Controllers\API\SUC;

use App\Http\Controllers\Controller;
use App\Models\PackingList;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PackingListApiController extends Controller
{
    //

    public function index()
    {
        // Sorting by numeric part of pl_number (format: PL/XXX/YYYY)
        $lists = PackingList::with(['project', 'expedition', 'plType', 'intPic', 'creator', 'destination'])
            ->get()
            ->sortByDesc(function ($item) {
                if (preg_match('/^PL\/(\d{3})\/(\d{4})$/', $item->pl_number, $matches)) {
                    // parse the numeric part as integer for sorting by largest number
                    return (int) $matches[1];
                }
                return 0;
            })->values(); // reindex collection after sorting

        return response()->json($lists);
    }

    public function store(Request $request)
    {
        // Cast foreign key fields to integers before validation to prevent SQL Server conversion errors
        $request->merge([
            'pn_id' => $request->pn_id ? (int) $request->pn_id : null,
            'destination_id' => $request->destination_id ? (int) $request->destination_id : null,
            'expedition_id' => $request->expedition_id ? (int) $request->expedition_id : null,
            'pl_type_id' => $request->pl_type_id ? (int) $request->pl_type_id : null,
            'int_pic' => $request->int_pic ? (int) $request->int_pic : null,
        ]);

        $validated = $request->validate([
            'pn_id' => 'nullable|exists:projects,pn_number',
            'destination_id' => 'nullable|exists:destinations,id',
            'expedition_id' => 'nullable|exists:master_expeditions,id',
            'pl_date' => 'nullable|date',
            'ship_date' => 'nullable|date',
            'pl_type_id' => 'nullable|exists:master_type_packing_lists,id',
            'int_pic' => 'nullable|exists:users,id',
            'client_pic' => 'nullable|string',
            'receive_date' => 'nullable|date',
            'pl_return_date' => 'nullable|date',
            'remark' => 'nullable|string',
            'pl_number' => 'nullable|string', // allow pl_number to be optionally provided
        ]);

        // If pl_number is provided in request, use that; otherwise generate
        if (!empty($validated['pl_number'])) {
            $pl_number = $validated['pl_number'];

            // Attempt to parse number part and year from given pl_number
            if (preg_match('/^PL\/(\d{3})\/(\d{4})$/', $pl_number, $matches)) {
                $numberFormatted = $matches[1];
                $year = $matches[2];
            } else {
                // If format incorrect, fall back to generate new number for current year
                $year = now()->format('Y');
                $last = PackingList::whereYear('created_at', $year)
                    ->orderByDesc('created_at')
                    ->first();

                $nextNumber = 1;
                if ($last) {
                    $lastNumber = (int) substr($last->pl_number, 3, 3);
                    $nextNumber = $lastNumber + 1;
                }
                $numberFormatted = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
                $pl_number = "PL/{$numberFormatted}/{$year}";
            }
        } else {
            // auto-generate pl_number and year
            $year = now()->format('Y');
            $last = PackingList::whereYear('created_at', $year)
                ->orderByDesc('created_at')
                ->first();

            $nextNumber = 1;

            if ($last) {
                $lastNumber = (int) substr($last->pl_number, 3, 3);
                $nextNumber = $lastNumber + 1;
            }

            $numberFormatted = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $pl_number = "PL/{$numberFormatted}/{$year}";
        }

        $validated['pl_number'] = $pl_number;
        $validated['pl_id'] = $numberFormatted . $year;
        $validated['created_by'] = auth()->id();

        $packingList = PackingList::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Packing List created successfully',
            'data' => $packingList,
        ]);
    }

    public function show($id)
    {
        $packingList = PackingList::with(['project', 'expedition', 'plType', 'intPic', 'creator', 'destination'])->findOrFail($id);
        return response()->json($packingList);
    }

    public function update(Request $request, $id)
    {
        $packingList = PackingList::findOrFail($id);

        $validated = $request->validate([
            'pn_id' => 'nullable|exists:projects,pn_number',
            'destination_id' => 'nullable|exists:destinations,id',
            'expedition_id' => 'nullable|exists:master_expeditions,id',
            'pl_date' => 'nullable|date',
            'ship_date' => 'nullable|date',
            'pl_type_id' => 'nullable|exists:master_type_packing_lists,id',
            'int_pic' => 'nullable|exists:users,id',
            'client_pic' => 'nullable|string',
            'receive_date' => 'nullable|date',
            'pl_return_date' => 'nullable|date',
            'remark' => 'nullable|string',
            'pl_number' => 'nullable|string', // allow updating pl_number
        ]);

        // Cast foreign key fields to integers to prevent SQL Server conversion errors
        $validated['pn_id'] = isset($validated['pn_id']) ? (int) $validated['pn_id'] : null;
        $validated['destination_id'] = isset($validated['destination_id']) ? (int) $validated['destination_id'] : null;
        $validated['expedition_id'] = isset($validated['expedition_id']) ? (int) $validated['expedition_id'] : null;
        $validated['pl_type_id'] = isset($validated['pl_type_id']) ? (int) $validated['pl_type_id'] : null;
        $validated['int_pic'] = isset($validated['int_pic']) ? (int) $validated['int_pic'] : null;

        // If pl_number is given, parse it to generate corresponding pl_id
        if (!empty($validated['pl_number'])) {
            $pl_number = $validated['pl_number'];

            if (preg_match('/^PL\/(\d{3})\/(\d{4})$/', $pl_number, $matches)) {
                $numberFormatted = $matches[1];
                $year = $matches[2];
                $validated['pl_id'] = $numberFormatted . $year;
            } else {
                // Invalid format, remove pl_number to avoid saving bad data
                unset($validated['pl_number']);
            }
        }

        $packingList->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Packing List updated successfully',
            'data' => $packingList,
        ]);
    }

    public function destroy($id)
    {
        $packingList = PackingList::findOrFail($id);
        $packingList->delete();

        return response()->json([
            'success' => true,
            'message' => 'Packing List deleted successfully',
        ]);
    }

    public function generateNumber()
    {
        $year = now()->format('Y');

        $last = PackingList::whereYear('created_at', $year)
            ->orderByDesc('created_at')
            ->first();

        $nextNumber = 1;

        if ($last) {
            $lastNumber = (int) substr($last->pl_number, 3, 3);
            $nextNumber = $lastNumber + 1;
        }

        $numberFormatted = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

        $pl_number = "PL/{$numberFormatted}/{$year}";
        $pl_id = $numberFormatted . $year;

        return response()->json([
            'pl_number' => $pl_number,
            'pl_id' => $pl_id
        ]);
    }

    public function createDeliveryOrder(Request $request, $id)
    {
        $packingList = PackingList::with([
            'project.client',
            'project.quotation.client',
            'plType',
            'expedition',
            'intPic',
            'creator',
            'destination'
        ])->findOrFail($id);

        // Check if packing list type is Finance
        $plType = $packingList->plType;
        if (!$plType || $plType->name !== 'Finance') {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order can only be created for Finance type packing lists'
            ], 400);
        }

        // Check if ship_date is set
        if (!$packingList->ship_date) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment date must be set to create delivery order'
            ], 400);
        }

        // Check if delivery order already exists
        $existingDO = \App\Models\DeliveryOrder::where('pn_id', $packingList->pn_id)
            ->where('do_description', 'Packing List Type Finance')
            ->first();

        if ($existingDO) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order already exists for this packing list'
            ], 400);
        }

        // Generate do_number and do_no for delivery order
        $year = date('y'); // e.g., '25'
        $prefix = 'SP' . $year;
        $doPrefix = 'SP/' . $year . '/';

        // Find the max number for the current year
        $maxDoNumber = \App\Models\DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(do_number, LEN(?) + 1, LEN(do_number)) AS INT)) as max_num', [$prefix])
            ->value('max_num') ?? 0;

        $nextNum = $maxDoNumber + 1;
        $paddedNum = str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $doNumber = $prefix . $paddedNum;
        $doNo = $doPrefix . $paddedNum;

        $deliveryOrder = \App\Models\DeliveryOrder::create([
            'do_number' => $doNumber,
            'do_no' => $doNo,
            'do_description' => 'Packing List Type Finance',
            'pn_id' => $packingList->pn_id,
            'do_send' => $packingList->ship_date, // Set do_send to ship_date
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery order created successfully',
            'data' => $deliveryOrder,
        ]);
    }

    public function confirmDeliveryOrder($id)
    {
        $packingList = PackingList::with([
            'project.client',
            'project.quotation.client',
            'plType',
            'expedition',
            'intPic',
            'creator',
            'destination'
        ])->findOrFail($id);

        // Check if packing list type is Finance
        $plType = $packingList->plType;
        if (!$plType || $plType->name !== 'Finance') {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order can only be created for Finance type packing lists'
            ], 400);
        }

        // Check if ship_date is set
        if (!$packingList->ship_date) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment date must be set to create delivery order'
            ], 400);
        }

        // Check if delivery order already exists
        $existingDO = \App\Models\DeliveryOrder::where('pn_id', $packingList->pn_id)
            ->where('do_description', 'Packing List Type Finance')
            ->first();

        if ($existingDO) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order already exists for this packing list'
            ], 400);
        }

        // Get client name from project or quotation
        $clientName = $packingList->project->client->name ?? ($packingList->project->quotation->client->name ?? 'N/A');

        return response()->json([
            'success' => true,
            'data' => [
                'packing_list' => $packingList,
                'project_number' => $packingList->project->project_number ?? 'N/A',
                'type_packing_list' => $plType->name,
                'client' => $clientName,
                'ship_date' => $packingList->ship_date,
            ]
        ]);
    }

}
