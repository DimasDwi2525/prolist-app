<?php

namespace App\Events;

use App\Models\PHC;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as LogFacade;

class PhcApprovalUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $phc;
    public $userIds;

    public function __construct(PHC $phc, array $userIds)
    {
        $this->phc = $phc;
        $this->userIds = $userIds;
    }

    public function broadcastOn()
    {
        return new Channel('phc.approval.updated'); // public channel
    }

    public function broadcastAs(): string
    {
        return 'phc.approval.updated';
    }

    public function broadcastWith(): array
    {
        $projectNumber = $this->phc->project ? $this->phc->project->project_number : 'Unknown';
        return [
            'phc_id' => $this->phc->id,
            'status' => $this->phc->status,
            'message' => "PHC approval for project {$projectNumber} has been updated.",
            'created_at' => now()->toISOString(),
            'title' => 'PHC Approval Updated',
        ];
    }
}
