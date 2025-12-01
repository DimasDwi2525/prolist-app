<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SidebarCounterUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;

    public function __construct($userId = null)
    {
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('sidebar.counter.updated');
    }

    public function broadcastAs(): string
    {
        return 'sidebar.counter.updated';
    }

    public function broadcastWith(): array
    {
        $user = \App\Models\User::find($this->userId);

        if (!$user) {
            return [
                'notificationUnread' => 0,
                'approvalPending' => 0,
                'requestInvoice' => 0,
            ];
        }

        // Count unread notifications
        $notificationUnread = $user->unreadNotifications()->count();

        // Count pending approvals for the user
        $approvalPending = \App\Models\Approval::where('user_id', $this->userId)
            ->where('status', 'pending')
            ->count();

        // Count pending request invoices (assuming status 'pending')
        $requestInvoice = \App\Models\RequestInvoice::where('status', 'pending')
            ->where('requested_by', $this->userId)
            ->count();

        return [
            'notificationUnread' => $notificationUnread,
            'approvalPending' => $approvalPending,
            'requestInvoice' => $requestInvoice,
        ];
    }
}
