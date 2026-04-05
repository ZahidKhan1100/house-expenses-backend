<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Trip;
use App\Models\House;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterUser
{
    public function execute(array $data)
    {
        $isTripMode = isset($data['mode']) && $data['mode'] === 'trip';

        // ----------------- Create User -----------------
        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => 'mate',
            'status' => 'pending',
            'email_verification_token' => Str::random(60),
            'active_mode' => $isTripMode ? 'trip' : 'house',
        ]);

        // ----------------- Send Email -----------------
        $verificationUrl = url("/api/v1/verify-email/{$user->email_verification_token}");
        $html = view('emails.verify-email', [
            'name' => $user->name,
            'verificationUrl' => $verificationUrl
        ])->render();

        sendMailgunEmail($user->email, 'Verify Your Email', $html);

        // ----------------- Trip Mode -----------------
        if ($isTripMode) {
            if (!empty($data['trip_code'])) {
                $trip = Trip::where('code', strtoupper($data['trip_code']))->firstOrFail();

                if (!$trip->members()->where('user_id', $user->id)->exists()) {
                    $trip->members()->attach($user->id);
                }

                $role = 'mate';
                $status = 'approved';
                $isNewTrip = false;
            } else {
                $trip = Trip::create([
                    'name' => $user->name . "'s Trip",
                    'admin_id' => $user->id,
                    'code' => strtoupper(Str::random(6)),
                    'currency' => '$',
                ]);
                $trip->members()->attach($user->id);

                $role = 'admin';
                $status = 'admin';
                $isNewTrip = true;
            }

            $user->update([
                'trip_id' => $trip->id,
                'role' => $role,
                'status' => $status,
                'active_mode' => 'trip',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'success' => true,
                'mode' => 'trip',
                'token' => $token,
                'user' => $user,
                'trip_code' => $trip->code,
                'is_new_trip' => $isNewTrip,
                'email_verified' => false,
            ];
        }

        // ----------------- House Mode -----------------
        // Accept both snake_case and camelCase from request
        $houseCode = $data['houseCode'] ?? $data['house_code'] ?? null;

        if (!empty($houseCode)) {
            $house = House::where('code', strtoupper($houseCode))->firstOrFail();
            $user->update([
                'house_id' => $house->id,
                'role' => 'mate',
                'status' => 'approved',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'success' => true,
                'mode' => 'house',
                'token' => $token,
                'user' => $user,
                'email_verified' => false,
            ];
        }

        // ----------------- Create New House -----------------
        $house = House::create([
            'name' => $user->name . "'s House",
            'code' => strtoupper(Str::random(6)),
            'admin_id' => $user->id,
            'currency' => '$',
        ]);

        $user->update([
            'house_id' => $house->id,
            'role' => 'admin',
            'status' => 'admin',
        ]);

        // Default categories
        $defaultCategories = [
            ['name' => 'Grocery', 'icon' => 'shopping-basket'],
            ['name' => 'Rent', 'icon' => 'home'],
        ];

        foreach ($defaultCategories as $cat) {
            $house->categories()->create($cat);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'success' => true,
            'mode' => 'house',
            'token' => $token,
            'user' => $user,
            'email_verified' => false,
        ];
    }
}