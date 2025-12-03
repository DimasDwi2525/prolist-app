<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $sender;
    public $targetUsers; // null for all users, array of user IDs for specific users
    public $messageType; // 'broadcast' or 'private'

    public function __construct($message, $sender, $targetUsers = null, $messageType = 'broadcast')
    {
        $this->message = $message;
        $this->sender = $sender;
        $this->targetUsers = $targetUsers;
        $this->messageType = $messageType;
    }

    public function broadcastOn()
    {
        // Always send to all users via public channel
        return new Channel('admin.messages');
    }

    public function broadcastAs(): string
    {
        return 'admin.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'sender' => $this->sender,
            'timestamp' => now()->toISOString(),
            'type' => $this->messageType,
            'targetUsers' => $this->targetUsers, // Include targetUsers for private messages
        ];
    }
}
