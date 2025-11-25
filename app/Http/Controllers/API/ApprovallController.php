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

        // Jika approval HO/validator pertama kali
        $phc = $approval->approvable;
        if ($approval->approvable_type === PHC::class && in_array($user->role->name, ['project manager', 'project controller', 'super_admin'])) {
            if (!$phc->ho_engineering_id) {
                $phc->update(['ho_engineering_id' => $user->id]);

                // Hapus semua approval validator lain yang masih pending
                Approval::where('approvable_type', PHC::class)
                    ->where('approvable_id', $phc->id)
                    ->where('status', 'pending')
                    ->where('user_id', '!=', $user->id)
                    ->delete();
            }
        }

        // Cek minimal 3 approval (HO Marketing + PIC Marketing + HO Engineering)
        $approvedCount = Approval::where('approvable_type', PHC::class)
            ->where('approvable_id', $phc->id)
            ->where('status', 'approved')
            ->count();

        if ($approvedCount >= 3) {
            $phc->update(['status' => 'ready']);
        }

        // Fire event for notification
        event(new \App\Events\PhcApprovalUpdated($phc));

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
        ]);
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
            event(new \App\Events\WorkOrderApprovalUpdated($wo));
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
