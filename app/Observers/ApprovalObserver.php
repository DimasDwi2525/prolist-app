<?php

namespace App\Observers;

use App\Models\Approval;
use App\Events\ApprovalPageUpdatedEvent;

class ApprovalObserver
{
    /**
     * Handle the Approval "created" event.
     */
    public function created(Approval $approval): void
    {
        event(new ApprovalPageUpdatedEvent(
            $approval->type,
            $approval->id,
            $approval->status,
            $approval->approvable_type,
            $approval->approvable_id
        ));
    }

    /**
     * Handle the Approval "updated" event.
     */
    public function updated(Approval $approval): void
    {
        event(new ApprovalPageUpdatedEvent(
            $approval->type,
            $approval->id,
            $approval->status,
            $approval->approvable_type,
            $approval->approvable_id
        ));
    }
}
