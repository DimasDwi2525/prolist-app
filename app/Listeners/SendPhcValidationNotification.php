<?php

namespace App\Listeners;

use App\Events\PhcCreatedEvent;
use App\Models\User;
use App\Notifications\PhcValidationRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendPhcValidationNotification
{
    public function handle(PhcCreatedEvent $event)
    {
        $phc = $event->phc;
        $userIds = $event->userIds;

        // Pastikan userIds unik untuk menghindari duplikasi notifikasi
        $uniqueUserIds = array_unique($userIds);

        // Kirim notifikasi ke user IDs yang sudah ditentukan
        $users = User::whereIn('id', $uniqueUserIds)->get();

        foreach ($users as $user) {
            $user->notify(new PhcValidationRequested($phc));
        }

        // Kirim notifikasi ke HO Engineering berdasarkan ho_engineering_id di PHC
        // hanya jika belum ada di daftar userIds
        if ($phc->ho_engineering_id && !in_array($phc->ho_engineering_id, $uniqueUserIds)) {
            $hoEngineer = User::find($phc->ho_engineering_id);
            if ($hoEngineer) {
                $hoEngineer->notify(new PhcValidationRequested($phc));
            }
        }
    }
}
