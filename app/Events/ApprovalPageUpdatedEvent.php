<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalPageUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $approvalType;
    public $approvalId;
    public $status;
    public $approvableType;
    public $approvableId;

    /**
     * Create a new event instance.
     *
     * @param string $approvalType Type of approval (PHC, WorkOrder, Log)
     * @param int $approvalId ID of the approval record
     * @param string $status Status of the approval (approved, rejected)
     * @param string $approvableType Type of the approvable model
     * @param int $approvableId ID of the approvable model
     */
    public function __construct(string $approvalType, int $approvalId, string $status, string $approvableType, int $approvableId)
    {
        $this->approvalType = $approvalType;
        $this->approvalId = $approvalId;
        $this->status = $status;
        $this->approvableType = $approvableType;
        $this->approvableId = $approvableId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel
     */
    public function broadcastOn()
    {
        return new Channel('approval.page.updated');
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'approval.page.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'approval_type' => $this->approvalType,
            'approval_id' => $this->approvalId,
            'status' => $this->status,
            'approvable_type' => $this->approvableType,
            'approvable_id' => $this->approvableId,
            'message' => "Approval for {$this->approvalType} has been {$this->status}.",
            'updated_at' => now()->toISOString(),
        ];
    }
}
