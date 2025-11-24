<?php

namespace App\Http\Controllers\API\Engineer;

use App\Events\DashboardUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Libraries\WorkOrderPdf;
use App\Models\Approval;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;

class WorkOrderApiController extends Controller
{
    //
    /**
     * GET Work Orders by Project PN Number
     */
    public function index($pn_number)
    {
        $project = Project::where('pn_number', $pn_number)->firstOrFail();

        $workOrders = WorkOrder::with(['pics.user', 'pics.role', 'descriptions', 'purpose'])
            ->where('project_id', $project->pn_number)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $workOrders,
        ]);

    }

    /**
     * CREATE new Work Order
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,pn_number',
            'purpose_id' => 'nullable|exists:purpose_work_orders,id',
            'wo_date' => 'required|date',
            'wo_count' => 'nullable|integer|min:1',
            'add_work' => 'boolean',
            'location' => 'nullable|string',
            'vehicle_no' => 'nullable|string',
            'driver' => 'nullable|string',

            'pics' => 'array',
            'pics.*.user_id' => 'required|exists:users,id',
            'pics.*.role_id' => 'nullable|exists:roles,id',

            'descriptions' => 'array',
            'descriptions.*.description' => 'nullable|string',
            'descriptions.*.result' => 'nullable|string',

            'start_work_time' => 'nullable',
            'stop_work_time' => 'nullable',

            'continue_date' => 'nullable|date',
            'continue_time' => 'nullable',

            'client_note' => 'nullable|string',

            'scheduled_start_working_date' => 'nullable|date',
            'scheduled_end_working_date' => 'nullable|date',
            'actual_start_working_date' => 'nullable|date',
            'actual_end_working_date' => 'nullable|date',

            'accomodation' => 'nullable|string',
            'material_required' => 'nullable|string',

            'client_approved' => 'nullable|boolean',
        ]);

        $woCount = $data['wo_count'] ?? 1;
        $year = now()->format('y');

        // cari nomor terakhir di project yang sama & tahun berjalan
        $lastWo = WorkOrder::where('project_id', $request->project_id)
            ->whereYear('created_at', now()->year)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastWo) {
            $parts = explode('/', $lastWo->wo_kode_no);
            if (!empty($parts[0])) {
                $num = (int) filter_var($parts[0], FILTER_SANITIZE_NUMBER_INT);
                $nextNumber = $num + 1;
            }
        }

        $workOrders = DB::transaction(function () use ($data, $nextNumber, $year, $woCount, $request) {
            $results = [];
            $baseDate = Carbon::parse($data['wo_date']);

            for ($i = 0; $i < $woCount; $i++) {
                $woData = $data;
                $woData['wo_date'] = $baseDate->copy()->addDays($i);
                // $woData['status'] = WorkOrder::STATUS_WAITING_APPROVAL;
                $woData['status'] = WorkOrder::STATUS_FINISHED;

                $lastInProject = WorkOrder::where('project_id', $data['project_id'])
                    ->max('wo_number_in_project');
                $woNumberInProject = ($lastInProject ?? 0) + 1;

                $projectCode = substr($data['project_id'], -3);
                $woData['wo_number_in_project'] = $woNumberInProject;
                $woData['wo_kode_no'] = sprintf(
                    "WO/%s/%s/%03d",
                    $year,
                    $projectCode,
                    $woNumberInProject
                );

                $wo = WorkOrder::create([
                    'project_id'           => $woData['project_id'],
                    'purpose_id'           => $woData['purpose_id'] ?? null,
                    'wo_date'              => $woData['wo_date'],
                    'wo_number_in_project' => $woData['wo_number_in_project'],
                    'wo_kode_no'           => $woData['wo_kode_no'],
                    'location'             => $woData['location'] ?? null,
                    'vehicle_no'           => $woData['vehicle_no'] ?? null,
                    'driver'               => $woData['driver'] ?? null,
                    'total_mandays_eng'    => 0,
                    'total_mandays_elect'  => 0,
                    'add_work'             => $woData['add_work'] ?? false,
                    'start_work_time'      => $woData['start_work_time'] ?? null,
                    'stop_work_time'       => $woData['stop_work_time'] ?? null,
                    'continue_date'        => $woData['continue_date'] ?? null,
                    'continue_time'        => $woData['continue_time'] ?? null,
                    'client_note'          => $woData['client_note'] ?? null,
                    'scheduled_start_working_date' => $woData['scheduled_start_working_date'] ?? null,
                    'scheduled_end_working_date'   => $woData['scheduled_end_working_date'] ?? null,
                    'actual_start_working_date'    => $woData['actual_start_working_date'] ?? null,
                    'actual_end_working_date'      => $woData['actual_end_working_date'] ?? null,
                    'accomodation'         => $woData['accomodation'] ?? null,
                    'material_required'    => $woData['material_required'] ?? null,
                    'wo_count'             => $woCount,
                    'client_approved'      => $woData['client_approved'] ?? false,
                    'created_by'           => $request->user()->id,
                    'status'               => $woData['status'],
                ]);

                // PIC
                if (!empty($data['pics'])) {
                    $wo->pics()->createMany($data['pics']);
                }

                // Descriptions
                if (!empty($data['descriptions'])) {
                    $wo->descriptions()->createMany($data['descriptions']);
                }

                $mandaysEngineerRoles = [
                    'Engineer',
                    'Project Manager',
                    'Site Manager',
                    'Site Supervisor',
                    'site_admin',
                    'Foreman',
                    'Project Controller',
                    'Document Controller',
                    'HSE',
                    'Quality Control',
                    'Site Warehouse',
                ];
                // Hitung mandays
               $totalEng = $wo->pics()
                    ->whereHas('role', fn($q) => $q->whereIn('name', $mandaysEngineerRoles))
                    ->count();

                $totalElect = $wo->pics()
                    ->whereHas('role', fn($q) => $q->where('name', 'electrician'))
                    ->count();

                $wo->update([
                    'total_mandays_eng'   => $totalEng,
                    'total_mandays_elect' => $totalElect,
                ]);

                // Buat approval untuk role tertentu
                // $approvalRoles = ['project manager', 'engineering_director'];
                // $users = User::whereHas('role', fn($q) => $q->whereIn('name', $approvalRoles))->get();

                // foreach ($users as $user) {
                //     Approval::create([
                //         'approvable_type' => WorkOrder::class,
                //         'approvable_id'   => $wo->id,
                //         'user_id'         => $user->id,
                //         'status'          => 'pending',
                //         'type'            => 'Work Order',
                //     ]);
                // }

                $results[] = $wo->load(['pics.user', 'pics.role', 'descriptions', 'purpose']);
            }

            return $results;
        });

        // Dispatch event for realtime dashboard update
        event(new DashboardUpdatedEvent());

        return response()->json([
            'status' => 'success',
            'data'   => $workOrders,
        ], 201);
    }

    /**
     * UPDATE Work Order
     */
    public function update(Request $request, $id)
    {
        $workOrder = WorkOrder::with(['pics', 'descriptions', 'purpose'])->findOrFail($id);

        $data = $request->validate([
            'total_mandays_eng' => 'sometimes|integer|min:0',
            'total_mandays_elect' => 'sometimes|integer|min:0',
            'add_work' => 'sometimes|boolean',

            'pics' => 'array',
            'pics.*.user_id' => 'required|exists:users,id',
            'pics.*.role_id' => 'nullable|exists:roles,id',

            'descriptions' => 'array',
            'descriptions.*.description' => 'nullable|string',
            'descriptions.*.result' => 'nullable|string',

            'location' => 'nullable|string',
            'vehicle_no' => 'nullable|string',
            'driver' => 'nullable|string',

            'wo_date' => 'sometimes|date',

            'start_work_time' => 'nullable',
            'stop_work_time' => 'nullable',

            'continue_date' => 'nullable|date',
            'continue_time' => 'nullable',

            'client_note' => 'nullable|string',

            'scheduled_start_working_date' => 'nullable|date',
            'scheduled_end_working_date' => 'nullable|date',
            'actual_start_working_date' => 'nullable|date',
            'actual_end_working_date' => 'nullable|date',

            'accomodation' => 'nullable|string',
            'material_required' => 'nullable|string',
        ]);

        // Deteksi perubahan pada fields tertentu
        // $hasChanges = false;

        // if (isset($data['wo_date']) && $data['wo_date'] != $workOrder->wo_date->format('Y-m-d')) {
        //     $hasChanges = true;
        // }

        // if (isset($data['pics'])) {
        //     $currentPics = $workOrder->pics->map(fn($p) => ['user_id' => $p->user_id, 'role_id' => $p->role_id])->sortBy(['user_id', 'role_id'])->values()->toArray();
        //     $newPics = collect($data['pics'])->sortBy(['user_id', 'role_id'])->values()->toArray();
        //     if ($currentPics != $newPics) {
        //         $hasChanges = true;
        //     }
        // }

        // if (isset($data['descriptions'])) {
        //     $currentDescs = $workOrder->descriptions->pluck('description')->sort()->values()->toArray();
        //     $newDescs = collect($data['descriptions'])->pluck('description')->sort()->values()->toArray();
        //     if ($currentDescs != $newDescs) {
        //         $hasChanges = true;
        //     }
        // }

        // DB::transaction(function () use ($workOrder, $data, $hasChanges) {
        //     // Update Work Order utama
        //     $workOrder->update(Arr::except($data, ['pics', 'descriptions']));

        //     // Update PICs
        //     if (isset($data['pics'])) {
        //         $workOrder->pics()->delete();
        //         $workOrder->pics()->createMany($data['pics']);
        //     }

        //     // Update Descriptions
        //     if (isset($data['descriptions'])) {
        //         $workOrder->descriptions()->delete();

        //         $descriptions = collect($data['descriptions'])->map(function ($desc) use ($workOrder) {
        //             return [
        //                 'description' => $desc['description'] ?? null,
        //                 // boleh isi result kalau status WAITING CLIENT APPROVAL atau APPROVED
        //                 'result'      => in_array($workOrder->status, [WorkOrder::STATUS_WAITING_CLIENT, WorkOrder::STATUS_APPROVED])
        //                                     ? ($desc['result'] ?? null)
        //                                     : null,
        //             ];
        //         })->toArray();

        //         $workOrder->descriptions()->createMany($descriptions);
        //     }

        //     $mandaysEngineerRoles = [
        //             'engineer',
        //             'project_manager',
        //             'site_manager',
        //             'site_supervisor',
        //             'site_admin',
        //             'foreman',
        //             'project_controller',
        //             'document_controller',
        //             'hse',
        //             'quality_control',
        //             'site_warehouse',
        //         ];

        //         // Hitung mandays
        //        $totalEng = $workOrder->pics()
        //             ->whereHas('role', fn($q) => $q->whereIn('name', $mandaysEngineerRoles))
        //             ->count();

        //         $totalElect = $workOrder->pics()
        //             ->whereHas('role', fn($q) => $q->where('name', 'electrician'))
        //             ->count();

        //         $workOrder->update([
        //             'total_mandays_eng'   => $totalEng,
        //             'total_mandays_elect' => $totalElect,
        //         ]);

        //     // Jika ada perubahan dan status approved, kirim approval baru
        //     if ($hasChanges && $workOrder->status === WorkOrder::STATUS_APPROVED) {
        //         $approvalRoles = ['project manager', 'engineering_director'];
        //         $users = User::whereHas('role', fn($q) => $q->whereIn('name', $approvalRoles))->get();

        //         foreach ($users as $user) {
        //             Approval::create([
        //                 'approvable_type' => WorkOrder::class,
        //                 'approvable_id'   => $workOrder->id,
        //                 'user_id'         => $user->id,
        //                 'status'          => 'pending',
        //                 'type'            => 'Work Order Update',
        //             ]);
        //         }

        //         $workOrder->update(['status' => WorkOrder::STATUS_WAITING_CLIENT]);
        //     }
        // });

        DB::transaction(function () use ($workOrder, $data) {
            // Update Work Order utama
            $workOrder->update(Arr::except($data, ['pics', 'descriptions']));

            // Update PICs
            if (isset($data['pics'])) {
                $workOrder->pics()->delete();
                $workOrder->pics()->createMany($data['pics']);
            }

            // Update Descriptions
            if (isset($data['descriptions'])) {
                $workOrder->descriptions()->delete();

                $descriptions = collect($data['descriptions'])->map(function ($desc) use ($workOrder) {
                    return [
                        'description' => $desc['description'] ?? null,
                        // boleh isi result kalau status WAITING CLIENT APPROVAL atau APPROVED
                        // 'result'      => in_array($workOrder->status, [WorkOrder::STATUS_WAITING_CLIENT, WorkOrder::STATUS_APPROVED])
                        //                     ? ($desc['result'] ?? null)
                        //                     : null,
                        'result' => $desc['result'] ?? null,

                    ];
                })->toArray();

                $workOrder->descriptions()->createMany($descriptions);
            }

            $mandaysEngineerRoles = [
                    'engineer',
                    'project_manager',
                    'site_manager',
                    'site_supervisor',
                    'site_admin',
                    'foreman',
                    'project_controller',
                    'document_controller',
                    'hse',
                    'quality_control',
                    'site_warehouse',
                ];

                // Hitung mandays
               $totalEng = $workOrder->pics()
                    ->whereHas('role', fn($q) => $q->whereIn('name', $mandaysEngineerRoles))
                    ->count();

                $totalElect = $workOrder->pics()
                    ->whereHas('role', fn($q) => $q->where('name', 'electrician'))
                    ->count();

                $workOrder->update([
                    'total_mandays_eng'   => $totalEng,
                    'total_mandays_elect' => $totalElect,
                ]);
            $workOrder->update(['status' => WorkOrder::STATUS_FINISHED]);
        });

        // Dispatch event for realtime dashboard update
        event(new DashboardUpdatedEvent());

        return response()->json([
            'status' => 'success',
            'data'   => $workOrder->fresh(['pics.user', 'pics.role', 'descriptions']),
        ]);
    }


    public function getWoSummary()
    {
        $projects = Project::with(['quotation.client', 'statusProject', 'client']) // load relasi
            ->withCount('workOrders') // hitung total wo
            ->orderByRaw('CAST(LEFT(CAST(pn_number AS VARCHAR), 2) AS INT) DESC')
            ->orderByRaw('CAST(SUBSTRING(CAST(pn_number AS VARCHAR), 3, LEN(CAST(pn_number AS VARCHAR)) - 2) AS INT) DESC')
            ->get();

        $summary = $projects->map(function ($project) {
            return [
                'pn_number'      => $project->pn_number,
                'project_number' => $project->project_number,
                'project_name'   => $project->project_name,
                'client_name' => $project->client?->name ?? $project->quotation->client->name,
                'total_wo'       => $project->work_orders_count, // hasil dari withCount
                'status_project' => $project->statusProject ? [
                    'id'   => $project->statusProject->id,
                    'name' => $project->statusProject->name,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $summary,
        ]);
    }

    public function show($id)
    {
        $workOrder = WorkOrder::with(['pics.user', 'pics.role', 'descriptions', 'project.quotation.client', 'project.client', 'creator', 'purpose','acceptor',
            'approver'])->find($id);

        if (!$workOrder) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Work Order not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $workOrder,
        ]);
    }

    public function downloadPdf($id)
    {
        $workOrder = WorkOrder::with([
            'descriptions',
            'project.client',
            'pics.user',
            'pics.role',
            'purpose',
            'creator',
            'acceptor',
            'approver'
        ])->findOrFail($id);

        // Ambil PIC dari relasi WorkOrderPic
        $pics = $workOrder->pics->map(function ($pic, $i) {
            return [
                'no'   => $i + 1,
                'name' => $pic->user->name ?? '-',
                'role' => $pic->role->name ?? '-',
            ];
        })->toArray();

        // Ambil data detail pekerjaan
        $data = $workOrder->descriptions->map(function ($d) {
            return [
                'desc' => $d->description,
                'result' => $d->result,
            ];
        })->toArray();

        $pdf = new WorkOrderPdf($workOrder, $data, $pics);
        $pdf->build();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->Output('S');
        }, 'Work-Order-' . $workOrder->wo_number . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }





}
