<?php

namespace App\Observers;

use App\Models\RequestInvoice;
use App\Events\SidebarCounterUpdate;

class RequestInvoiceObserver
{
    public function created(RequestInvoice $requestInvoice)
    {
        // Trigger sidebar counter update for the user who requested the invoice
        event(new SidebarCounterUpdate($requestInvoice->requested_by));
    }

    public function updated(RequestInvoice $requestInvoice)
    {
        // Trigger sidebar counter update for the user who requested the invoice
        event(new SidebarCounterUpdate($requestInvoice->requested_by));
    }

    public function deleted(RequestInvoice $requestInvoice)
    {
        // Trigger sidebar counter update for the user who requested the invoice
        event(new SidebarCounterUpdate($requestInvoice->requested_by));
    }
}
