<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\House;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterUser
{
    public function execute(array $data)
    {
        // =====================================================
        // 🧠 1. FIND OR RESTORE USER (SOFT DELETE SAFE)
        // =====================================================
        $user = User::withTrashed()
            ->where('email', strtolower($data['email']))
            ->first();

        if ($user) {

            if ($user->trashed()) {
                $user->restore();
            }

            $user->update([
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'status' => 'pending',
                'role' => 'mate',
                'active_mode' => 'house',
                'email_verification_token' => Str::random(60),
            ]);

        } else {

            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'role' => 'mate',
                'status' => 'pending',
                'email_verification_token' => Str::random(60),
                'active_mode' => 'house',
            ]);
        }

        // =====================================================
        // 📧 SEND VERIFICATION EMAIL
        // =====================================================
        $verificationUrl = url("/api/v1/verify-email/{$user->email_verification_token}");

        $html = view('emails.verify-email', [
            'name' => $user->name,
            'verificationUrl' => $verificationUrl
        ])->render();

        sendMailgunEmail($user->email, 'Verify Your Email', $html);

        // =====================================================
        //  2. JOIN HOUSE VIA CODE (IF PROVIDED)
        // =====================================================
        $houseCode = $data['houseCode'] ?? $data['house_code'] ?? null;

        if (!empty($houseCode)) {

            $house = House::where('code', strtoupper($houseCode))->firstOrFail();

            $user->update([
                'house_id' => $house->id,
                'role' => 'mate',
                'status' => 'approved',
                'active_mode' => 'house',
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

        // =====================================================
        //  3. CREATE NEW HOUSE
        // =====================================================
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
            'active_mode' => 'house',
        ]);

        // Default categories
        foreach ([
            ['name' => 'Grocery', 'icon' => 'shopping-basket'],
            ['name' => 'Rent', 'icon' => 'home'],
        ] as $cat) {
            $house->categories()->create($cat);
        }

        // =====================================================
        //  TOKEN + RESPONSE
        // =====================================================
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