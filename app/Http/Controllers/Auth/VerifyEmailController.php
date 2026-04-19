<?php

namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

use Illuminate\Auth\Events\Verified;
use App\Mail\VerifyEmail;



class VerifyEmailController
{
    public function verify($token)
    {
        $user = User::where('email_verification_token', $token)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->view('emails.verified', [
                'status' => 'already',
                'name' => $user->name,
                'deepLink' => null,
            ]);
        }

        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ]);

        $plain = $user->createToken('mobile')->plainTextToken;
        $deepLink = 'habimate://verified?token='.rawurlencode($plain);

        return response()->view('emails.verified', [
            'status' => 'success',
            'name' => $user->name,
            'deepLink' => $deepLink,
        ]);
    }

    public function resend(Request $request)
    {
        $request->validate([
            "email" => "required|email"
        ]);

        $user = User::where("email", $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json([
                "message" => "Email already verified"
            ]);
        }

        $user->email_verification_token = Str::random(60);
        $user->save();

        Mail::to($user->email)->send(new VerifyEmail($user));

        return response()->json([
            "message" => "Verification email resent"
        ]);
    }
}