<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//     return (int) $user->id === (int) $id;
// });


Broadcast::channel('logs.project.{projectId}', function ($user, $projectId) {
    return true;
});

Broadcast::channel('phc.notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});


Broadcast::channel('notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('approval.page.updated', function ($user) {
    return true;
});

Broadcast::channel('online-users', function ($user) {
    $user->load('role');
    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role?->name,
    ];
});

// Admin broadcast channels
Broadcast::channel('admin.messages', function ($user) {
    // Allow all authenticated users to listen to admin broadcasts
    return true;
});

Broadcast::channel('admin.messages.{userId}', function ($user, $userId) {
    // Only allow users to listen to their own private admin messages
    return (int) $user->id === (int) $userId;
});








