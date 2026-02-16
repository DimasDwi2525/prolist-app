<?php

namespace App\Http\Controllers\API;

use App\Events\ApprovalPageUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Log;
use App\Models\PHC;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Notifications\DatabaseNotification;

class ApprovallController extends Controller
{
    public function index(Request $request)
    {
        $query = Approval::where('user_id', $request->user()->id)
            ->with('approvable.project.phc', 'approvable.project');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $approvals = $query->latest()->get();
        return response()->json($approvals);
    }

    public function show(Request $request, $id)
    {
        $approval = Approval::where('user_id', $request->user()->id)
            ->with('approvable')
            ->findOrFail($id);

        return response()->json($approval);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'pin' => 'required|string',
        ]);

        $approval = Approval::where('user_id', $request->user()->id)
            ->where('approvable_type', PHC::class)
            ->with('user', 'approvable')
            ->findOrFail($id);

        $user = $approval->user;
        $pinDb = $user->pin; // <-- gunakan kolom 'pin' di table users

        if (str_starts_with($pinDb, '$2y$') && strlen($pinDb) === 60) {
            $isValid = Hash::check($request->pin, $pinDb);
        } else {
            $isValid = $request->pin === $pinDb;
        }

        // Jika valid, hash PIN di user supaya selanjutnya aman
        if (!$isValid) {
            return response()->json(['message' => 'PIN tidak valid'], 403);
        }

        if (!str_starts_with($pinDb, '$2y$') || strlen($pinDb) !== 60) {
            $user->pin = Hash::make($request->pin); // hash dari input user
            $user->save();
        }

        // Update status approval
        $approval->update([
            'status' => $request->status,
            'validated_at' => now(),
        ]);

        // PHC approval flow:
        // 1) PIC Marketing approve
        // 2) HO Engineering approve
        //    Jika HO Engineering belum ditentukan, PM/PC pertama yang approve akan menjadi HO Engineering.
        $phc = $approval->approvable;
        $roleName = strtolower($user->role->name ?? '');
        $isPmOrPc = in_array($roleName, ['project manager', 'project controller'], true);
        $isDelegatedEngineeringApprover = $request->status === 'approved'
            && (bool) $phc->ho_engineering_id
            && $isPmOrPc
            && (int) $user->id !== (int) $phc->ho_engineering_id;

        if ($request->status === 'approved' && !$phc->ho_engineering_id && $isPmOrPc) {
            $phc->update(['ho_engineering_id' => $user->id]);

            $pendingPmPcApprovals = Approval::where('approvable_type', PHC::class)
                ->where('approvable_id', $phc->id)
                ->where('status', 'pending')
                ->where('user_id', '!=', $user->id)
                ->whereHas('user.role', function ($q) {
                    $q->whereIn('name', ['project manager', 'project controller']);
                })
                ->get();

            $removedUserIds = $pendingPmPcApprovals->pluck('user_id')->all();
            foreach ($pendingPmPcApprovals as $pendingApproval) {
                $pendingApproval->delete();
            }

            $this->deletePhcValidationNotifications($phc->id, $removedUserIds);
        }

        // Jika approval engineering didelegasikan ke PM/PC,
        // simpan approver yang approve dan hapus pending approval PM/PC lain.
        if ($isDelegatedEngineeringApprover) {
            $pendingPmPcApprovals = Approval::where('approvable_type', PHC::class)
                ->where('approvable_id', $phc->id)
                ->where('status', 'pending')
                ->where('user_id', '!=', $user->id)
                ->whereHas('user.role', function ($q) {
                    $q->whereIn('name', ['project manager', 'project controller']);
                })
                ->get();

            $removedUserIds = $pendingPmPcApprovals->pluck('user_id')->all();
            foreach ($pendingPmPcApprovals as $pendingApproval) {
                $pendingApproval->delete();
            }

            $this->deletePhcValidationNotifications($phc->id, $removedUserIds);
        }

        $picMarketingApproved = false;
        if ($phc->pic_marketing_id) {
            $picMarketingApproved = Approval::where('approvable_type', PHC::class)
                ->where('approvable_id', $phc->id)
                ->where('user_id', $phc->pic_marketing_id)
                ->where('status', 'approved')
                ->exists();
        }

        $hoEngineeringApproved = false;
        if ($phc->ho_engineering_id) {
            $hoEngineeringApproved = Approval::where('approvable_type', PHC::class)
                ->where('approvable_id', $phc->id)
                ->where('user_id', $phc->ho_engineering_id)
                ->where('status', 'approved')
                ->exists();
        }

        $delegatedEngineeringApproved = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phc->id)
            ->where('status', 'approved')
            ->where('user_id', '!=', $phc->ho_engineering_id)
            ->whereHas('user.role', function ($q) {
                $q->whereIn('name', ['project manager', 'project controller']);
            })
            ->exists();

        $engineeringApproved = $hoEngineeringApproved || $delegatedEngineeringApproved;

        if ($picMarketingApproved && $engineeringApproved) {
            $phc->update(['status' => PHC::STATUS_APPROVED]);
        } else {
            $phc->update(['status' => PHC::STATUS_WAITING_APPROVAL]);
        }

        $statusNotificationRecipients = array_values(array_unique(array_filter([
            $phc->created_by,
            $phc->pic_marketing_id,
            $phc->ho_engineering_id,
        ])));

        // Fire event for notification
        event(new \App\Events\PhcApprovalUpdated($phc, $statusNotificationRecipients));

        // Fire event to update approval page
        event(new ApprovalPageUpdatedEvent(
            'PHC',
            $approval->id,
            $request->status,
            PHC::class,
            $phc->id
        ));

        return response()->json([
            'message' => "Approval berhasil {$request->status}",
            'approval' => $approval,
            'phc_status' => $phc->status,
            'phc_status_label' => $phc->display_status,
            'pic_marketing_approved' => $picMarketingApproved,
            'ho_engineering_approved' => $hoEngineeringApproved,
            'delegated_engineering_approved' => $delegatedEngineeringApproved,
            'engineering_approved' => $engineeringApproved,
        ]);
    }

    private function deletePhcValidationNotifications(int $phcId, array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        $notifications = DatabaseNotification::query()
            ->whereIn('notifiable_id', $userIds)
            ->where('type', \App\Notifications\PhcValidationRequested::class)
            ->get();

        foreach ($notifications as $notification) {
            $payload = is_array($notification->data) ? $notification->data : [];

            if ((int) ($payload['phc_id'] ?? 0) === $phcId) {
                $notification->delete();
            }
        }
    }

    // Update status approval WO
    public function updateStatusWo(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'pin' => 'required|string',
        ]);

        $approval = Approval::where('user_id', $request->user()->id)
            ->where('approvable_type', WorkOrder::class)
            ->with('user', 'approvable.project')
            ->findOrFail($id);

        $user = $approval->user;
        $pinDb = $user->pin;

        // cek PIN
        if (str_starts_with($pinDb, '$2y$') && strlen($pinDb) === 60) {
            $isValid = Hash::check($request->pin, $pinDb);
        } else {
            $isValid = $request->pin === $pinDb;
        }

        if (!$isValid) {
            return response()->json(['message' => 'PIN tidak valid'], 403);
        }

        // hash PIN jika belum di-hash
        if (!str_starts_with($pinDb, '$2y$') || strlen($pinDb) !== 60) {
            $user->pin = Hash::make($request->pin);
            $user->save();
        }

        // update status approval
        $approval->update([
            'status' => $request->status,
            'validated_at' => now(),
        ]);

        $wo = $approval->approvable;

        if ($request->status === 'approved') {
            // Hapus semua approval pending user lain untuk WO ini
            Approval::where('approvable_type', WorkOrder::class)
                ->where('approvable_id', $wo->id)
                ->where('status', 'pending')
                ->where('user_id', '!=', $user->id)
                ->delete();

            // Jika status WO adalah waiting client approval, ini adalah approval kedua
            if ($wo->status === WorkOrder::STATUS_WAITING_CLIENT) {
                $wo->update([
                    'accepted_by' => $user->id,
                    'status' => WorkOrder::STATUS_FINISHED,
                ]);
            } else {
                // Approval pertama
                $wo->update([
                    'approved_by' => $user->id,
                    'status' => WorkOrder::STATUS_APPROVED,
                ]);
            }
        } elseif ($request->status === 'rejected') {
            // Jika rejected, tetap tunggu approval lain
            $wo->update(['status' => WorkOrder::STATUS_WAITING_APPROVAL]);
        }

        // Fire event for notification
        // Fire event for notification only if the user who approved is different from the creator
        // As per PHC creation event logic:
        // Do not notify the user who created the work order
        // So exclude creator from notifications and events even if they are the approver

        if ($wo->creator && $wo->creator->id !== $user->id) {
            event(new \App\Events\WorkOrderApprovalUpdated($wo, [$wo->creator->id]));
        }

        // Fire event to update approval page
        event(new ApprovalPageUpdatedEvent(
            'WorkOrder',
            $approval->id,
            $request->status,
            WorkOrder::class,
            $wo->id
        ));

        return response()->json([
            'message' => "Approval berhasil {$request->status}",
            'approval' => $approval,
            'wo_status' => $wo->status,
            'approved_by' => $wo->approved_by,
        ]);
    }

    // Update status approval Log
    public function updateStatusLog(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
            'pin' => 'required|string',
        ]);

        $approval = Approval::where('user_id', $request->user()->id)
            ->where('approvable_type', Log::class)
            ->with('user', 'approvable.project')
            ->findOrFail($id);

        $user = $approval->user;
        $pinDb = $user->pin;

        // 🔐 Validasi PIN
        if (str_starts_with($pinDb, '$2y$') && strlen($pinDb) === 60) {
            $isValid = Hash::check($request->pin, $pinDb);
        } else {
            $isValid = $request->pin === $pinDb;
        }

        if (!$isValid) {
            return response()->json(['message' => 'PIN tidak valid'], 403);
        }

        // 🔒 Hash PIN jika belum di-hash
        if (!str_starts_with($pinDb, '$2y$') || strlen($pinDb) !== 60) {
            $user->pin = Hash::make($request->pin);
            $user->save();
        }

        // ✅ Update status approval
        $approval->update([
            'status' => $request->status,
            'validated_at' => now(),
        ]);

        // 🔄 Ambil model Log yang di-approve
        $log = $approval->approvable;

        // ✅ Jika disetujui → update status log
        if ($request->status === 'approved') {
            $log->update([
                'status' => 'approved',
                'closing_date' => now(),
                'closing_users' => $user->id,
            ]);
        }

        // ❌ Jika ditolak → ubah status log jadi 'rejected'
        if ($request->status === 'rejected') {
            $log->update([
                'status' => 'rejected',
            ]);
        }

        // Event LogApprovalUpdated akan di-trigger otomatis oleh LogObserver
        // ketika status log berubah (approved/rejected)
        // Tidak perlu manual fire event di sini untuk menghindari duplicate

        // Fire event to update approval page
        event(new ApprovalPageUpdatedEvent(
            'Log',
            $approval->id,
            $request->status,
            Log::class,
            $log->id
        ));

        return response()->json([
            'message' => "Approval berhasil {$request->status}",
            'approval' => $approval,
            'log_status' => $log->status,
            'closing_user' => $log->closing_users,
            'closing_date' => $log->closing_date,
        ]);
    }






}
