<?php

namespace App\Notifications;

use App\Models\RequestInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestInvoiceCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $requestInvoice;

    public function __construct($requestInvoice)
    {
        $this->requestInvoice = $requestInvoice;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // ✅ simpan ke DB + broadcast realtime
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Request Invoice Created',
            'message' => "A new Request Invoice has been created for Project {$this->requestInvoice->project->project_number}",
            'request_invoice_id' => $this->requestInvoice->id,
            'project' => $this->requestInvoice->project->project_number,
            'request_number' => $this->requestInvoice->request_number,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'Request Invoice Created',
                'message' => "A new Request Invoice has been created for Project {$this->requestInvoice->project->project_number}",
                'request_invoice_id' => $this->requestInvoice->id,
                'project' => $this->requestInvoice->project->project_number,
                'request_number' => $this->requestInvoice->request_number,
            ],
        ];
    }
}
