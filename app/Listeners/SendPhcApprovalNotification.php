<?php

namespace App\Listeners;

use App\Events\PhcApprovalUpdated;
use App\Models\User;
use App\Notifications\PhcApprovalNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPhcApprovalNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PhcApprovalUpdated $event)
    {
        $phc = $event->phc;
        $userIds = array_values(array_unique(array_filter($event->userIds ?? [])));

        if (empty($userIds)) {
            return;
        }

        $users = User::whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $user->notify(new PhcApprovalNotification($phc, $phc->status));
        }
    }
}
