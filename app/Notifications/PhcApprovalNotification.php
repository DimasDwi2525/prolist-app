<?php

namespace App\Notifications;

use App\Models\PHC;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhcApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $phc;
    protected $status;

    public function __construct(PHC $phc, $status)
    {
        $this->phc = $phc;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'PHC Approval Updated',
            'message' => "Your PHC approval for Project {$this->phc->project->project_name} has been {$this->status}",
            'phc_id' => $this->phc->id,
            'project' => $this->phc->project->project_number,
            'status' => $this->status,
            'type' => 'phc_update',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'PHC Approval Updated',
                'message' => "Your PHC approval for Project {$this->phc->project->project_name} has been {$this->status}",
                'phc_id' => $this->phc->id,
                'project' => $this->phc->project->project_number,
                'status' => $this->status,
                'type' => 'phc_update',
            ],
        ];
    }
}
