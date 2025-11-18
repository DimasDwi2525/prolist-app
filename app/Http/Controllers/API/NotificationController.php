<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Get unread notifications (for polling)
    public function index(Request $request)
    {
        return response()->json([
            'notifications' => $request->user()->unreadNotifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ];
            })
        ]);
    }

    // Get all notifications (read and unread)
    public function all(Request $request)
    {
        return response()->json([
            'notifications' => $request->user()->notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'updated_at' => $notification->updated_at,
                ];
            })
        ]);
    }

    // Get count of unread notifications
    public function count(Request $request)
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications->count()
        ]);
    }

    // Mark specific notification as read
    public function markAsRead(Request $request, $id)
    {
        // Delete notifications older than 7 days
        $request->user()->notifications()->where('created_at', '<', now()->subDays(7))->delete();

        $notification = $request->user()
            ->unreadNotifications()
            ->find($id);

        if ($notification) {
            $notification->update(['read_at' => now()]);
            return response()->json(['message' => 'Notification marked as read']);
        }

        return response()->json(['message' => 'Notification not found or already read'], 404);
    }

    // Mark all notifications as read
    public function markAllAsRead(Request $request)
    {
        // Delete notifications older than 7 days
        $request->user()->notifications()->where('created_at', '<', now()->subDays(7))->delete();

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    // Delete a specific notification
    public function destroy(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->find($id);

        if ($notification) {
            $notification->delete();
            return response()->json(['message' => 'Notification deleted']);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }
}
