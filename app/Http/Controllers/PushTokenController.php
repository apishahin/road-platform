<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    /** Store the browser's FCM token for the logged-in user. */
    public function store(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'max:500']]);

        PushToken::updateOrCreate(
            ['token' => $request->input('token')],
            ['user_id' => $request->user()->id]
        );

        return response()->json(['ok' => true]);
    }

    /** Remove a token (user turned push off in the browser). */
    public function destroy(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'max:500']]);

        PushToken::where('token', $request->input('token'))
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
