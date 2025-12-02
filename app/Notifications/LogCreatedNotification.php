<?php

namespace App\Notifications;

use App\Models\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LogCreatedNotification extends Notification implements ShouldQueue
{
   use Queueable;

    protected $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // ✅ simpan ke DB + broadcast realtime
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Log Created',
            'message' => "A new log has been created for Project {$this->log->project->project_number} - {$this->log->project->project_name}",
            'log_id'  => $this->log->id,
            'project' => $this->log->project->project_number,
            'type' => 'log_created',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => [
                'title' => 'Log Created',
                'message' => "A new log has been created for Project {$this->log->project->project_number} - {$this->log->project->project_name}",
                'log_id'  => $this->log->id,
                'project' => $this->log->project->project_number,
                'type' => 'log_created',
            ],
        ];
    }
}
