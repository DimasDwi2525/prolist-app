<?php

namespace App\Events;

use App\Models\PHC;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PhcCreatedEvent implements ShouldBroadcastNow
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
        Log::info('PhcCreatedEvent broadcastWith called', ['phc_id' => $this->phc->id, 'user_ids' => $this->userIds]);
        return new Channel('phc_created'); // ✅ public channel
    }

    public function broadcastAs(): string
    {
        return 'phc_created';
    }

    public function broadcastWith(): array
    {
        $projectNumber = $this->phc->project ? $this->phc->project->project_number : 'Unknown';
        return [
            'phc_id' => $this->phc->id,
            'status' => $this->phc->status,
            'user_ids' => $this->userIds,
            'message' => "PHC for project {$projectNumber} has been created.",
            'created_at' => now()->toISOString(),
        ];
    }
}