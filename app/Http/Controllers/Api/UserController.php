<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user()->load('house'); // eager load house relationship

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'house_id' => $user->house_id,
            'expo_push_token' => $user->expo_push_token,
            'has_push_token' => !empty($user->expo_push_token),
            'house' => $user->house ? [
                'id' => $user->house->id,
                'name' => $user->house->name,
                'currency' => $user->house->currency ?? '$',
                'code' => $user->house->code,
            ] : null,
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['users' => []]);
        }

        $users = User::where('email', 'LIKE', "%{$query}%")
            ->orWhere('name', 'LIKE', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'users' => $users
        ]);
    }

}