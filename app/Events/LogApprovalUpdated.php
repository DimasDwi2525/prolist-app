<?php

namespace App\Events;

use App\Models\Log;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log as LogFacade;

class LogApprovalUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    public function broadcastOn()
    {
        return new Channel('log.approval.updated'); // ✅ public channel
    }

    public function broadcastAs(): string
    {
        return 'log.approval.updated';
    }

    public function broadcastWith(): array
    {
        $projectNumber = $this->log->project ? $this->log->project->project_number : 'Unknown';
        return [
            'log_id' => $this->log->id,
            'status' => $this->log->status,
            'message' => "Log approval for project {$projectNumber} has been {$this->log->status}.",
            'created_at' => now()->toISOString(),
            'title' => 'Log Approval Updated',
        ];
    }
}
