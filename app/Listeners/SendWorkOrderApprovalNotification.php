<?php

namespace App\Listeners;

use App\Events\WorkOrderApprovalUpdated;
use App\Notifications\WorkOrderApprovalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWorkOrderApprovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(WorkOrderApprovalUpdated $event)
    {
        $workOrder = $event->workOrder;

        // Kirim notifikasi hanya ke user yang membuat Work Order
        if ($workOrder->creator) {
            $workOrder->creator->notify(new WorkOrderApprovalNotification($workOrder, $workOrder->status));
        }
    }
}
