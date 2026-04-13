<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $user->expo_push_token = $validated['token'];
        $user->save();

        return response()->json([
            'success' => true,
        ]);
    }
}

