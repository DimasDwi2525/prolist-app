<?php

namespace App\Notifications;

use App\Models\PHC;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhcValidationRequested extends Notification
{

    protected $phc;

    public function __construct($phc)
    {
        $this->phc = $phc;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // ✅ simpan ke DB + broadcast realtime public
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'PHC Validation Requested',
            'message' => "A new PHC has been created for Project {$this->phc->project->project_number}",
            'phc_id'  => $this->phc->id,
            'project_number' => $this->phc->project->project_number,
            'pn_number' => $this->phc->project->pn_number,
            'type' => 'phc'
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'PHC Validation Requested',
                'message' => "A new PHC has been created for Project {$this->phc->project->project_number}",
                'phc_id'  => $this->phc->id,
                'project_number' => $this->phc->project->project_number,
                'pn_number' => $this->phc->project->pn_number,
                'type' => 'phc'
            ],
        ];
    }
}