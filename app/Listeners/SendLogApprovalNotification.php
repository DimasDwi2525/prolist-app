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

        // Kirim notifikasi ke user yang membuat log (users_id) bahwa log telah di approve
        $log->user->notify(new LogApprovalNotification($log, $log->status));
    }
}
