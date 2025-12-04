<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $userId;
    public $role;
    public $messageId;

    public function __construct($message, $userId = null, $role = null)
    {
        $this->message = $message;
        $this->userId = $userId;
        $this->role = $role;
        $this->messageId = uniqid('msg_', true);
    }

    public function broadcastOn()
    {
        $channels = [];

        if ($this->userId) {
            $channels[] = new PrivateChannel('user.' . $this->userId);
        }

        if ($this->role) {
            $channels[] = new PrivateChannel('role.' . $this->role);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'user.message';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'message_id' => $this->messageId,
            'timestamp' => now()->toISOString(),
            'type' => $this->userId ? 'user' : 'role',
            'target' => $this->userId ?: $this->role,
        ];
    }
}
