<?php

namespace App\Http\Controllers\API\SUC;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\PackingList;
use App\Models\Project;
use Illuminate\Http\Request;

class PackingListApiController extends Controller
{
    //

    public function projectList()
    {
        $projects = Project::with(['client', 'quotation.client'])->get()->map(function ($project) {
            return [
                'pn_number' => $project->pn_number,
                'project_number' => $project->project_number,
                'project_name' => $project->project_name,
                'client_name' => $project->client->name ?? ($project->quotation->client->name ?? 'N/A'),
            ];
        });

        return response()->json($projects);
    }

    public function index(Request $request)
    {
        // Get filter parameters
        $yearParam = $request->query('year');
        $rangeType = $request->query('range_type', 'yearly'); // yearly, monthly, custom
        $monthParam = $request->query('month'); // 1-12
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        // Get available years from pl_date
        $availableYears = PackingList::selectRaw('YEAR(pl_date) as year')
            ->whereNotNull('pl_date')
            ->distinct()
            ->pluck('year')
            ->map(fn($y) => (int)$y)
            ->sort()
            ->values()
            ->toArray();

        $year = $yearParam ? (int)$yearParam : (!empty($availableYears) ? end($availableYears) : now()->year);

        // Build query with eager loading
        $lists = PackingList::with(['project', 'expedition', 'plType', 'intPic', 'creator', 'destination']);

        // Apply pl_date filter
        if ($rangeType === 'monthly' && $monthParam) {
            $month = (int)$monthParam;
            if ($month >= 1 && $month <= 12) {
                $lists->whereYear('pl_date', $year)
                      ->whereMonth('pl_date', $month);
            }
        } elseif ($rangeType === 'weekly') {
            // Filter by current week
            $lists->whereBetween('pl_date', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($rangeType === 'custom' && $fromDate && $toDate) {
            $lists->whereBetween('pl_date', [$fromDate, $toDate]);
        } else {
            // default: filter by year only
            $lists->whereYear('pl_date', $year);
        }

        // Get results and sort by pl_number
        $lists = $lists->get()
            ->sortByDesc(function ($item) {
                if (preg_match('/^PL\/(\d{3})\/(\d{4})$/', $item->pl_number, $matches)) {
                    // parse the numeric part as integer for sorting by largest number
                    return (int) $matches[1];
                }
                return 0;
            })->values(); // reindex collection after sorting

        return response()->json([
            'data' => $lists,
            'filters' => [
                'year' => $year,
                'range_type' => $rangeType,
                'month' => $monthParam,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'available_years' => $availableYears,
            ],
        ]);
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
            'year' => 'nullable|integer|min:2000|max:2100', // allow custom year for pl_number
        ]);

        // If pl_number is provided in request, use that; otherwise generate
        if (!empty($validated['pl_number'])) {
            $pl_number = $validated['pl_number'];

            // Attempt to parse number part and year from given pl_number
            if (preg_match('/^PL\/(\d{3})\/(\d{4})$/', $pl_number, $matches)) {
                $numberFormatted = $matches[1];
                $year = $matches[2];
            } else {
                // If format incorrect, use custom year or fall back to current year
                $year = $validated['year'] ?? now()->format('Y');
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
            // auto-generate pl_number - use custom year if provided, otherwise current year
            $year = $validated['year'] ?? now()->format('Y');
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
        $this->createFinanceDeliveryOrder($packingList);

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

    public function showByPnNumber($pn_number)
    {
        $project = Project::where('pn_number', $pn_number)->firstOrFail();

        $packingLists = PackingList::with(['project', 'expedition', 'plType', 'intPic', 'creator', 'destination'])
            ->where('pn_id', $project->pn_number)
            ->orderByDesc('pl_date')
            ->orderByDesc('created_at')
            ->paginate(5);

        return response()->json([
            'project' => $project,
            'data' => $packingLists,
        ]);
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
        ]);

        // Cast only submitted foreign key fields to prevent SQL Server conversion errors.
        foreach (['pn_id', 'destination_id', 'expedition_id', 'pl_type_id', 'int_pic'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $validated[$field] = (int) $validated[$field];
            }
        }

        $packingList->update($validated);
        $this->createFinanceDeliveryOrder($packingList->fresh('plType'));

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

    public function generateNumber(Request $request)
    {
        $year = $request->input('year', now()->format('Y'));

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

        $plType = $packingList->plType;
        if (!$plType || $plType->name !== 'Finance') {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order can only be created for Finance type packing lists'
            ], 400);
        }

        $deliveryOrder = $this->createFinanceDeliveryOrder($packingList);

        return response()->json([
            'success' => true,
            'message' => 'Delivery order created successfully',
            'data' => $deliveryOrder,
        ]);
    }

    private function createFinanceDeliveryOrder(PackingList $packingList): ?DeliveryOrder
    {
        $packingList->loadMissing('plType');

        if (!$packingList->plType || $packingList->plType->name !== 'Finance') {
            return null;
        }

        $description = 'Packing List Type Finance';

        $existingDeliveryOrder = DeliveryOrder::where('pn_id', $packingList->pn_id)
            ->where('do_description', $description)
            ->first();

        if ($existingDeliveryOrder) {
            return $existingDeliveryOrder;
        }

        $year = date('y');
        $prefix = 'SP' . $year;
        $doPrefix = 'SP/' . $year . '/';

        $maxDoNumber = DeliveryOrder::where('do_number', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(do_number, LEN(?) + 1, LEN(do_number)) AS INT)) as max_num', [$prefix])
            ->value('max_num') ?? 0;

        $nextNum = $maxDoNumber + 1;
        $paddedNum = str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        return DeliveryOrder::create([
            'do_number' => $prefix . $paddedNum,
            'do_no' => $doPrefix . $paddedNum,
            'do_description' => $description,
            'pn_id' => $packingList->pn_id,
            'do_send' => $packingList->ship_date,
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
