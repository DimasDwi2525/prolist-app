<?php

namespace App\Notifications;

use App\Models\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LogApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $log;
    protected $status;

    public function __construct(Log $log, $status)
    {
        $this->log = $log;
        $this->status = $status;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Log Approval Updated',
            'message' => "Your log approval for Project {$this->log->project->project_name} has been {$this->status}",
            'log_id' => $this->log->id,
            'project' => $this->log->project->project_number,
            'status' => $this->status,
            'type' => 'log_update',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'Log Approval Updated',
                'message' => "Your log approval for Project {$this->log->project->project_name} has been {$this->status}",
                'log_id' => $this->log->id,
                'project' => $this->log->project->project_number,
                'status' => $this->status,
                'type' => 'log_update',
            ],
        ];
    }
}
