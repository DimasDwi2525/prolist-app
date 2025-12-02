<?php

namespace App\Http\Controllers\API;

use App\Events\AdminBroadcastEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminBroadcastController extends Controller
{
    public function broadcastToAll(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = Auth::user();

        // Fire the broadcast event to all users
        broadcast(new AdminBroadcastEvent(
            $request->message,
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            null, // null means broadcast to all
            'broadcast'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Message broadcasted to all users successfully',
        ]);
    }

    public function sendToSpecificUsers(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();

        // Verify that the target users exist
        $targetUsers = User::whereIn('id', $request->user_ids)->pluck('id')->toArray();

        if (empty($targetUsers)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid users found',
            ], 400);
        }

        // Fire the broadcast event to specific users
        broadcast(new AdminBroadcastEvent(
            $request->message,
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            $targetUsers,
            'private'
        ));

        return response()->json([
            'success' => true,
            'message' => 'Message sent to specific users successfully',
            'sent_to_users' => count($targetUsers),
        ]);
    }
}
