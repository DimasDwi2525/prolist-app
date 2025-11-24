<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkOrderApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $workOrder;
    protected $status;

    public function __construct(WorkOrder $workOrder, $status)
    {
        $this->workOrder = $workOrder;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Work Order Approval Updated',
            'message' => "Your Work Order approval for Project {$this->workOrder->project->project_name} has been {$this->status}",
            'work_order_id' => $this->workOrder->id,
            'project' => $this->workOrder->project->project_number,
            'status' => $this->status,
            'type' => 'work_order_update',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'Work Order Approval Updated',
                'message' => "Your Work Order approval for Project {$this->workOrder->project->project_name} has been {$this->status}",
                'work_order_id' => $this->workOrder->id,
                'project' => $this->workOrder->project->project_number,
                'status' => $this->status,
                'type' => 'work_order_update',
            ],
        ];
    }
}
