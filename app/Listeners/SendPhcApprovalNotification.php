<?php

namespace App\Listeners;

use App\Events\PhcApprovalUpdated;
use App\Notifications\PhcApprovalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPhcApprovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PhcApprovalUpdated $event)
    {
        $phc = $event->phc;
        $approverId = $phc->ho_engineering_id;

        // Kirim notifikasi ke user yang membuat PHC (created_by),
        // kecuali jika user tersebut adalah pembuat PHC sendiri
        if ($phc->createdBy && $phc->createdBy->id !== $approverId && $phc->createdBy->id !== $phc->created_by) {
            $phc->createdBy->notify(new PhcApprovalNotification($phc, $phc->status));
        }
    }
}
