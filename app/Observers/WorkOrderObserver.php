<?php

namespace App\Observers;

use App\Events\DashboardUpdatedEvent;
use App\Events\WorkOrderCreatedEvent;
use App\Events\WorkOrderUpdatedEvent;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

class WorkOrderObserver
{
    protected $originalData = null;
    public function created(WorkOrder $workOrder)
    {

        event(new DashboardUpdatedEvent());

        // Get user IDs for notification (only project managers)
        $rolesToNotify = ['project manager'];
        $users = \App\Models\User::whereHas('role', function ($query) use ($rolesToNotify) {
            $query->whereIn('name', $rolesToNotify);
        })->pluck('id')->toArray();


        event(new WorkOrderCreatedEvent($workOrder, $users));
    }

    public function updating(WorkOrder $workOrder)
    {
        // Store original data before update
        $this->originalData = $workOrder->getOriginal();
    }

    public function updated(WorkOrder $workOrder)
    {
        event(new DashboardUpdatedEvent());

        // Only fire update event if there are actual changes
        if ($this->hasSignificantChanges($workOrder)) {
            // Get user IDs for notification (only project managers)
            $rolesToNotify = ['project manager'];
            $users = \App\Models\User::whereHas('role', function ($query) use ($rolesToNotify) {
                $query->whereIn('name', $rolesToNotify);
            })->pluck('id')->toArray();

            event(new WorkOrderUpdatedEvent($workOrder, $users));
        }
    }

    private function hasSignificantChanges(WorkOrder $workOrder)
    {
        $original = $this->originalData;
        $current = $workOrder->getAttributes();

        // Check for significant changes (exclude timestamps and calculated fields)
        $significantFields = [
            'wo_date',
            'location',
            'vehicle_no',
            'driver',
            'add_work',
            'start_work_time',
            'stop_work_time',
            'continue_date',
            'continue_time',
            'client_note',
            'scheduled_start_working_date',
            'scheduled_end_working_date',
            'actual_start_working_date',
            'actual_end_working_date',
            'accomodation',
            'material_required',
            'client_approved'
        ];

        foreach ($significantFields as $field) {
            if (isset($original[$field]) && isset($current[$field])) {
                if ($original[$field] != $current[$field]) {
                    return true;
                }
            }
        }

        return false;
    }
}
