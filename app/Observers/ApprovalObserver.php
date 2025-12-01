<?php

namespace App\Observers;

use App\Models\Approval;
use App\Events\SidebarCounterUpdate;

class ApprovalObserver
{
    public function created(Approval $approval)
    {
        // Trigger sidebar counter update for the user who needs to approve
        event(new SidebarCounterUpdate($approval->user_id));
    }

    public function updated(Approval $approval)
    {
        // Trigger sidebar counter update for the user who needs to approve
        event(new SidebarCounterUpdate($approval->user_id));
    }

    public function deleted(Approval $approval)
    {
        // Trigger sidebar counter update for the user who needs to approve
        event(new SidebarCounterUpdate($approval->user_id));
    }
}
