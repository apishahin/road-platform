<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;

class NotificationsController extends Controller
{
    /** List the user's notifications, newest first; marks all as read. */
    public function index()
    {
        $user = auth()->user();

        $items = UserNotification::with('statusEvent')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);

        UserNotification::where('user_id', $user->id)->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('notifications', ['items' => $items]);
    }
}
