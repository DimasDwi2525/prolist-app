<?php

namespace App\Notifications;

use App\Models\PHC;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhcCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $phc;

    public function __construct(PHC $phc)
    {
        $this->phc = $phc;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "PHC berhasil dibuat untuk Project {$this->phc->project->project_number}",
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
                'message' => "PHC berhasil dibuat untuk Project {$this->phc->project->project_number}",
                'phc_id'  => $this->phc->id,
                'project_number' => $this->phc->project->project_number,
                'pn_number' => $this->phc->project->pn_number,
                'type' => 'phc'
            ],
        ];
    }
}
