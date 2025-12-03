<?php

namespace App\Observers;

use App\Events\DashboardUpdatedEvent;
use App\Events\LogApprovalUpdated;
use App\Events\LogCreatedEvent;
use App\Models\Log;

class LogObserver
{
    /**
     * Handle the Log "created" event.
     */
    public function created(Log $log): void
    {
        // Jika log butuh approval, kirim event dan notifikasi
        if ($log->need_response) {
            // Kirim notifikasi ke user yang perlu response
            $userIds = [$log->response_by];
            event(new LogCreatedEvent($log, $userIds));
        }

        event(new DashboardUpdatedEvent());
    }

    /**
     * Handle the Log "updated" event.
     */
    public function updated(Log $log): void
    {
        // Jika status log berubah (approved/rejected), kirim event approval
        if ($log->wasChanged('status') && in_array($log->status, ['approved', 'rejected'])) {
            event(new LogApprovalUpdated($log, [$log->users_id]));
        }

        event(new DashboardUpdatedEvent());
    }

    /**
     * Handle the Log "deleted" event.
     */
    public function deleted(Log $log): void
    {
        //
    }

    /**
     * Handle the Log "restored" event.
     */
    public function restored(Log $log): void
    {
        //
    }

    /**
     * Handle the Log "force deleted" event.
     */
    public function forceDeleted(Log $log): void
    {
        //
    }
}
