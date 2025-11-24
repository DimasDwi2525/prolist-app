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

        // Kirim notifikasi ke user yang membuat PHC (created_by) bahwa PHC telah di approve
        $phc->createdBy->notify(new PhcApprovalNotification($phc, $phc->status));
    }
}
