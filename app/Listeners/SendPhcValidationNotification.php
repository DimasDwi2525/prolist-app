<?php

namespace App\Listeners;

use App\Events\PhcCreatedEvent;
use App\Models\User;
use App\Notifications\PhcValidationRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPhcValidationNotification
{
    public function handle(PhcCreatedEvent $event)
    {
        $phc = $event->phc;
        $userIds = $event->userIds;

        // Kirim notifikasi sesuai list approver dari flow PHC
        $uniqueUserIds = array_unique($userIds);

        $users = User::whereIn('id', $uniqueUserIds)->get();

        foreach ($users as $user) {
            $user->notify(new PhcValidationRequested($phc));
        }
    }
}
