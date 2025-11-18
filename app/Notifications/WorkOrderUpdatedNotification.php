<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkOrderUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $workOrder;

    /**
     * Create a new notification instance.
     */
    public function __construct(WorkOrder $workOrder)
    {
        $this->workOrder = $workOrder;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('A Work Order has been updated.')
                    ->action('View Work Order', url('/work-orders/' . $this->workOrder->id))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Work Order Updated',
            'message' => 'Work Order ' . $this->workOrder->wo_kode_no . ' has been updated for project ' . $this->workOrder->project->project_name,
            'work_order_id' => $this->workOrder->id,
            'project_id' => $this->workOrder->project_id,
            'type' => 'work_order_updated',
        ];
    }
}
