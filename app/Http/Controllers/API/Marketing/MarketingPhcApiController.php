<?php

namespace App\Http\Controllers\API\Marketing;

use App\Events\ApprovalPageUpdatedEvent;
use App\Events\PhcCreatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\PHC;
use App\Models\Project;
use App\Models\Retention;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MarketingPhcApiController extends Controller
{
    //
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,pn_number',
            'handover_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'target_finish_date' => 'nullable|date',
            'client_pic_name' => 'nullable|string',
            'client_mobile' => 'nullable|string',
            'client_reps_office_address' => 'nullable|string',
            'client_site_representatives' => 'nullable|string',
            'client_site_address' => 'nullable|string',
            'site_phone_number' => 'nullable|string',
            'pic_marketing_id' => 'required|exists:users,id',
            'pic_engineering_id' => 'nullable|exists:users,id',
            'ho_marketings_id' => 'nullable|exists:users,id',
            'ho_engineering_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'retention' => 'nullable|boolean',
            'warranty' => 'nullable|boolean',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
            'retention_months' => 'nullable|integer|min:0',
            'warranty_date' => 'nullable|date',
            'penalty' => 'nullable',
            'boq_file_path' => 'nullable|file|max:10240',
        ]);

        $mapRadio = function ($value) {
            return $value === 'A' ? 1 : 0;
        };

        $validated['costing_by_marketing'] = $mapRadio($request->costing_by_marketing);
        $validated['boq'] = $mapRadio($request->boq);
        $validated['retention'] = $request->boolean('retention');
        $validated['warranty'] = $request->boolean('warranty');
        $validated['penalty'] = $request->penalty;
        $validated['boq_file_path'] = $this->storeBoqFile($request);

        $validated['created_by'] = Auth::id();
        // waiting approval (stored as `pending` for DB compatibility)
        $validated['status'] = PHC::STATUS_WAITING_APPROVAL;

        // 🔹 Buat PHC
        $phc = PHC::create($validated);

        // 🔹 Update tanggal PHC di project terkait
        $phc->project()->update([
            'phc_dates' => now(),
        ]);

        // 🔹 Handle retention jika retention applicable
        if ($validated['retention']) {
            $retentionData = [
                'project_id' => $validated['project_id'],
            ];

            if (isset($validated['retention_percentage']) && $validated['retention_percentage'] > 0) {
                // Ambil project_value dari project po_value
                $project = Project::find($validated['project_id']);
                $projectValue = $project->po_value ?? 0;
                $retentionData['retention_value'] = ($validated['retention_percentage'] / 100) * $projectValue;
            }

            Retention::create($retentionData);
        }
        // ================================
        // 4. TENTUKAN USER PENERIMA NOTIF
        // ================================
        $notifyUsers = [];
        $approverIds = [];

        // PIC Marketing wajib approve
        $picMarketingId = $validated['pic_marketing_id'];
        $approverIds[] = $picMarketingId;
        $notifyUsers[] = $picMarketingId;

        // Engineering logic
        $hoEngineeringId = $validated['ho_engineering_id'] ?? null;

        // Jika HO Engineering sudah ditentukan, kirim approval langsung ke user tsb
        if ($hoEngineeringId) {
            $approverIds[] = $hoEngineeringId;
            $notifyUsers[] = $hoEngineeringId;
        } else {
            // Jika HO Engineering kosong, kirim approval ke semua PM + PC
            $engineeringFallbackRoles = ['project manager', 'project controller'];
            $engineeringUsers = User::whereHas('role', fn($q) =>
                $q->whereIn('name', $engineeringFallbackRoles)
            )->pluck('id')->toArray();

            foreach ($engineeringUsers as $eid) {
                $approverIds[] = $eid;
                $notifyUsers[] = $eid;
            }
        }

        // Bersihkan hasil
        $approverIds = array_values(array_unique($approverIds));
        $notifyUsers = array_values(array_unique($notifyUsers));


        // ================================
        // 5. SIMPAN APPROVALS
        // ================================
        foreach ($approverIds as $uid) {
            $approval = Approval::create([
                'approvable_type' => PHC::class,
                'type'            => 'PHC',
                'approvable_id'   => $phc->id,
                'user_id'         => $uid,
                'status'          => 'pending',
            ]);

            // Fire event to update approval page
            event(new ApprovalPageUpdatedEvent(
                'PHC',
                $approval->id,
                'pending',
                PHC::class,
                $phc->id
            ));
        }


        // ================================
        // 6. KIRIM NOTIFIKASI KE USER
        // ================================
        // Notifikasi dikirim melalui event listener untuk menghindari duplikasi

        // ================================
        // 7. FIRE EVENT REALTIME
        // ================================
        event(new PhcCreatedEvent($phc, $notifyUsers));

     

        return response()->json([
            'status'  => 'success',
            'message' => 'PHC created successfully, approvals assigned.',
            'data'    => [
                'phc'        => $phc,
                'approvers'  => $approverIds,
            ]
        ]);
    }

    public function show($id)
    {
        $phc = PHC::with([
            'project.quotation',
            'hoMarketing',
            'hoEngineering',
            'picMarketing',
            'picEngineering',
            'approvals'
        ])->find($id);

        if (!$phc) {
            return response()->json([
                'success' => false,
                'message' => 'PHC tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $phc
        ]);
    }

    public function viewBoqFile($id)
    {
        $phc = PHC::find($id);

        if (!$phc) {
            return response()->json([
                'success' => false,
                'message' => 'PHC tidak ditemukan'
            ], 404);
        }

        if (!$phc->boq_file_path || !Storage::disk('public')->exists($phc->boq_file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File BOQ tidak ditemukan'
            ], 404);
        }

        $filePath = Storage::disk('public')->path($phc->boq_file_path);
        $mimeType = $this->boqFileMimeType($filePath);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($phc->boq_file_path) . '"',
        ]);
    }

    public function update(Request $request, $id)
    {
        $phc = PHC::find($id);

        if (!$phc) {
            return response()->json([
                'success' => false,
                'message' => 'PHC tidak ditemukan'
            ], 404);
        }

        $validated = $request->validate([
            'handover_date' => 'required|date',
            'start_date' => 'required|date',
            'target_finish_date' => 'required|date',
            'client_pic_name' => 'nullable|string',
            'client_mobile' => 'nullable|string',
            'client_reps_office_address' => 'nullable|string',
            'client_site_representatives' => 'nullable|string',
            'client_site_address' => 'nullable|string',
            'site_phone_number' => 'nullable|string',
            'pic_marketing_id' => 'nullable|exists:users,id',
            'pic_engineering_id' => 'nullable|exists:users,id',
            'ho_marketings_id' => 'nullable|exists:users,id',
            'ho_engineering_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'retention' => 'nullable|boolean',
            'warranty' => 'nullable|boolean',
            'retention_percentage' => 'nullable|numeric|min:0|max:100',
            'retention_months' => 'nullable|integer|min:0',
            'warranty_date' => 'nullable|date',
            'penalty' => 'nullable|string',
            'boq' => 'nullable|string',
            'costing_by_marketing' => 'nullable|string',
            'boq_file_path' => 'nullable|file|max:10240',
        ]);

        // Mapping radio
        $mapRadio = fn($v) => $v === 'A' ? 1 : 0;
        foreach (['boq','costing_by_marketing'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = $mapRadio($validated[$field]);
            }
        }

        if ($this->hasBoqFile($request)) {
            $validated['boq_file_path'] = $this->replaceBoqFile($request, $phc);
        }

         $phc->update($validated);

        // 🔹 Handle retention jika retention applicable
        if ($validated['retention']) {
            $retentionData = [
                'project_id' => $phc->project_id,
            ];

            if (isset($validated['retention_percentage']) && $validated['retention_percentage'] > 0) {
                // Ambil project_value dari project po_value
                $project = Project::find($phc->project_id);
                $projectValue = $project->po_value ?? 0;
                $retentionData['retention_value'] = ($validated['retention_percentage'] / 100) * $projectValue;
            }

            Retention::updateOrCreate(
                ['project_id' => $phc->project_id],
                $retentionData
            );
        } else {
            // Jika retention tidak applicable, hapus retention jika ada
            Retention::where('project_id', $phc->project_id)->delete();
        }


        // $newApprovers = [];

        // $hasMarketing = !empty($validated['ho_marketings_id']) || !empty($validated['pic_marketing_id']);
        // $hasEngineering = !empty($validated['ho_engineering_id']);

        // if ($hasMarketing || $hasEngineering) {
        //     // HO & PIC Marketing
        //     foreach (['ho_marketings_id','pic_marketing_id'] as $field) {
        //         if (!empty($validated[$field]) && !$phc->approvals()->where('user_id', $validated[$field])->exists()) {
        //             Approval::create([
        //                 'approvable_type' => PHC::class,
        //                 'type' => 'PHC',
        //                 'approvable_id' => $phc->id,
        //                 'user_id' => $validated[$field],
        //                 'status' => 'pending',
        //             ]);
        //             $newApprovers[] = $validated[$field];
        //         }
        //     }

        //     // HO Engineering
        //     if ($hasEngineering && !$phc->approvals()->where('user_id', $validated['ho_engineering_id'])->exists()) {
        //         Approval::create([
        //             'approvable_type' => PHC::class,
        //             'type' => 'PHC',
        //             'approvable_id' => $phc->id,
        //             'user_id' => $validated['ho_engineering_id'],
        //             'status' => 'pending',
        //         ]);
        //         User::find($validated['ho_engineering_id'])?->notify(new PhcValidationRequested($phc));
        //         $newApprovers[] = $validated['ho_engineering_id'];
        //     } elseif ($hasMarketing && !$hasEngineering) {
        //         // Jika marketing ada tapi engineering kosong → kirim ke semua role engineering
        //         $engineeringUsers = User::whereHas('role', function($q){
        //             $q->whereIn('name', ['project manager', 'project controller', 'engineering_director']);
        //         })->get();

        //         foreach ($engineeringUsers as $user) {
        //             if (!$phc->approvals()->where('user_id', $user->id)->exists()) {
        //                 Approval::create([
        //                     'approvable_type' => PHC::class,
        //                     'type' => 'PHC',
        //                     'approvable_id' => $phc->id,
        //                     'user_id' => $user->id,
        //                     'status' => 'pending',
        //                 ]);
        //                 $user->notify(new PhcValidationRequested($phc));
        //                 $newApprovers[] = $user->id;
        //             }
        //         }
        //     }
        // }
       
        return response()->json([
            'success' => true,
            'message' => 'PHC berhasil diperbarui',
            'data' => $phc
        ]);
    }

    private function storeBoqFile(Request $request): ?string
    {
        if (!$this->hasBoqFile($request)) {
            return null;
        }

        return $this->boqFile($request)->store('phc_boq_files', 'public');
    }

    private function replaceBoqFile(Request $request, PHC $phc): string
    {
        if ($phc->boq_file_path && Storage::disk('public')->exists($phc->boq_file_path)) {
            Storage::disk('public')->delete($phc->boq_file_path);
        }

        return $this->boqFile($request)->store('phc_boq_files', 'public');
    }

    private function hasBoqFile(Request $request): bool
    {
        return $request->hasFile('boq_file_path') || $request->hasFile('boq_file');
    }

    private function boqFile(Request $request): \Illuminate\Http\UploadedFile
    {
        return $request->file('boq_file_path') ?? $request->file('boq_file');
    }

    private function boqFileMimeType(string $filePath): string
    {
        $mimeType = mime_content_type($filePath) ?: null;

        if ($mimeType && $mimeType !== 'application/x-empty') {
            return $mimeType;
        }

        return match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $mimeType ?: 'application/octet-stream',
        };
    }

    public function delegateHoEngineering(Request $request, $id)
    {
        $phc = PHC::find($id);

        if (!$phc) {
            return response()->json([
                'success' => false,
                'message' => 'PHC tidak ditemukan'
            ], 404);
        }

        if (!$phc->ho_engineering_id) {
            return response()->json([
                'success' => false,
                'message' => 'Delegation tidak diperlukan karena HO Engineering belum ditentukan'
            ], 422);
        }

        if ($phc->status === PHC::STATUS_APPROVED) {
            return response()->json([
                'success' => false,
                'message' => 'PHC sudah approved'
            ], 422);
        }

        

        $delegateUserIds = User::whereHas('role', function ($q) {
            $q->whereIn('name', ['project manager', 'project controller']);
        })->pluck('id')->all();

        if (empty($delegateUserIds)) {
            return response()->json([
                'success' => false,
                'message' => 'User project manager / project controller tidak ditemukan'
            ], 422);
        }

        // Hapus approval pending milik HO Engineering agar approval engineering bisa diwakilkan.
        Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phc->id)
            ->where('user_id', $phc->ho_engineering_id)
            ->where('status', 'pending')
            ->delete();

        $createdApprovals = collect();
        foreach ($delegateUserIds as $delegateUserId) {
            $alreadyHasPendingOrApproved = Approval::where('approvable_type', PHC::class)
                ->where('approvable_id', $phc->id)
                ->where('user_id', $delegateUserId)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($alreadyHasPendingOrApproved) {
                continue;
            }

            $approval = Approval::create([
                'approvable_type' => PHC::class,
                'type'            => 'PHC',
                'approvable_id'   => $phc->id,
                'user_id'         => $delegateUserId,
                'status'          => 'pending',
            ]);

            $createdApprovals->push($approval);

            event(new ApprovalPageUpdatedEvent(
                'PHC',
                $approval->id,
                'pending',
                PHC::class,
                $phc->id
            ));
        }

        $notifyUsers = $createdApprovals->pluck('user_id')->values()->all();
        if (!empty($notifyUsers)) {
            event(new PhcCreatedEvent($phc, $notifyUsers));
        }

        return response()->json([
            'success' => true,
            'message' => 'Delegation approval berhasil dikirim ke Project Manager dan Project Controller',
            'data' => [
                'phc_id' => $phc->id,
                'ho_engineering_id' => $phc->ho_engineering_id,
                'delegated_user_ids' => $notifyUsers,
            ],
        ]);
    }


}
