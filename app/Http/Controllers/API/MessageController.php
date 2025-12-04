<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Events\UserMessageEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Send a message to a specific user
     */
    public function sendToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Broadcast the message
        broadcast(new UserMessageEvent(
            $request->message,
            $user->id,
            null
        ));

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully'
        ]);
    }

    /**
     * Send a message to all users with a specific role
     */
    public function sendToRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_type' => 'required|string',
            'message' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = User::where('role_type', $request->role_type)->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No users found with this role'
            ], 404);
        }

        // Broadcast the message to all users with this role
        broadcast(new UserMessageEvent(
            $request->message,
            null,
            $request->role_type
        ));

        return response()->json([
            'success' => true,
            'message' => 'Message sent to ' . $users->count() . ' users successfully'
        ]);
    }

    /**
     * Mark a message as read (using cache)
     */
    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();
        $cacheKey = "user_messages_read_{$userId}";

        $readMessages = Cache::get($cacheKey, []);
        $readMessages[] = $request->message_id;

        // Keep only the last 100 read messages to prevent cache bloat
        $readMessages = array_slice(array_unique($readMessages), -100);

        Cache::put($cacheKey, $readMessages, now()->addDays(7));

        return response()->json([
            'success' => true,
            'message' => 'Message marked as read'
        ]);
    }

    /**
     * Get unread messages count (for UI indicators)
     */
    public function getUnreadCount()
    {
        // Since messages are not stored in database, we can't track unread count
        // This could be implemented with a different approach if needed
        return response()->json([
            'success' => true,
            'unread_count' => 0
        ]);
    }
}
