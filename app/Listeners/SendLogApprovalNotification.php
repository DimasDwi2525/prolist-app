<?php

namespace App\Listeners;

use App\Events\LogApprovalUpdated;
use App\Notifications\LogApprovalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendLogApprovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(LogApprovalUpdated $event)
    {
        $log = $event->log;
        $approverId = $log->closing_users;

        // Kirim notifikasi ke user yang membuat log (users_id),
        // kecuali jika user tersebut adalah yang melakukan approval
        if ($log->user && $log->user->id !== $approverId) {
            $log->user->notify(new LogApprovalNotification($log, $log->status));
        }
    }
}
