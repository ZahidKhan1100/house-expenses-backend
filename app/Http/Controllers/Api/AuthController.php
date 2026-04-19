<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\LoginRequest;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\LoginUser;
use Illuminate\Http\Request;

use App\Models\User;

class AuthController extends Controller
{
    public function signup(SignupRequest $request, RegisterUser $action)
    {
        $result = $action->execute($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request, LoginUser $action)
    {
        return $action->execute($request->validated());
    }

    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out'
        ]);
    }

    public function checkEmailVerified(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->hasVerifiedEmail()) {
            return response()->json([
                'email_verified' => false,
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        $house = null;
        if ($user->house_id) {
            $house = $user->house()->with(['categories'])->first();
        }

        return response()->json([
            'email_verified' => true,
            'token' => $token,
            'user' => $user,
            'house' => $house,
        ]);
    }

    
}