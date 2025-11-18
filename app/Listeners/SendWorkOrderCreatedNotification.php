<?php

namespace App\Listeners;

use App\Events\WorkOrderCreatedEvent;
use App\Models\User;
use App\Notifications\WorkOrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendWorkOrderCreatedNotification implements ShouldQueue
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
    public function handle(WorkOrderCreatedEvent $event): void
    {
        $workOrder = $event->workOrder;

        // Notify only project managers
        $rolesToNotify = ['project manager'];

        $users = User::whereHas('role', function ($query) use ($rolesToNotify) {
            $query->whereIn('name', $rolesToNotify);
        })->get();


        foreach ($users as $user) {
            $user->notify(new WorkOrderCreatedNotification($workOrder));
        }
    }
}
