<?php

namespace App\Listeners;

use App\Events\WorkOrderUpdatedEvent;
use App\Models\User;
use App\Notifications\WorkOrderUpdatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWorkOrderUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(WorkOrderUpdatedEvent $event): void
    {
        $workOrder = $event->workOrder;

        // Notify only project managers
        $rolesToNotify = ['project manager'];

        $users = User::whereHas('role', function ($query) use ($rolesToNotify) {
            $query->whereIn('name', $rolesToNotify);
        })->get();

        foreach ($users as $user) {
            $user->notify(new WorkOrderUpdatedNotification($workOrder));
        }
    }
}
