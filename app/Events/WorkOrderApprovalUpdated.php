<?php

namespace App\Events;

use App\Models\WorkOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as LogFacade;

class WorkOrderApprovalUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $workOrder;

    public $userIds;

    public function __construct(WorkOrder $workOrder, array $userIds)
    {
        $this->workOrder = $workOrder;
        $this->userIds = $userIds;
    }

    public function broadcastOn()
    {
        return new Channel('work_order.approval.updated'); // public channel
    }

    public function broadcastAs(): string
    {
        return 'work_order.approval.updated';
    }

    public function broadcastWith(): array
    {
        $projectNumber = $this->workOrder->project ? $this->workOrder->project->project_number : 'Unknown';
        return [
            'work_order_id' => $this->workOrder->id,
            'status' => $this->workOrder->status,
            'message' => "Work Order approval for project {$projectNumber} has been updated.",
            'created_at' => now()->toISOString(),
            'title' => 'Work Order Approval Updated',
        ];
    }
}
