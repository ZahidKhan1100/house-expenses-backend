<?php

namespace App\Actions\Auth;

use App\Models\House;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        // Founder is permanent: first 10,000 users by id.
        if ((int) $user->id <= 10000 && ! $user->is_founder) {
            $user->is_founder = true;
            $user->save();
        }

        // =====================================================
        // 📧 SEND VERIFICATION EMAIL
        // =====================================================
        $verificationUrl = route('verify.email', [
            'token' => $user->email_verification_token,
        ]);

        $html = view('emails.verify-email', [
            'name' => $user->name,
            'verificationUrl' => $verificationUrl,
        ])->render();

        sendMailgunEmail($user->email, 'Verify Your Email', $html);

        // =====================================================
        //  2. JOIN HOUSE VIA CODE (IF PROVIDED)
        // =====================================================
        $houseCode = $data['houseCode'] ?? $data['house_code'] ?? null;

        if (! empty($houseCode)) {

            $house = House::where('code', strtoupper($houseCode))->first();
            if (! $house) {
                throw ValidationException::withMessages([
                    'house_code' => ['That house code isn’t valid. Double-check it or scan the QR again.'],
                ]);
            }

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
        //  2b. RECOVER SOFT-DELETED HOUSE (SOLO DELETE / LAST MEMBER LEFT)
        // =====================================================
        $trashedHouse = House::onlyTrashed()
            ->where('admin_id', $user->id)
            ->orderByDesc('deleted_at')
            ->first();

        if ($trashedHouse) {
            $trashedHouse->restore();

            $user->update([
                'house_id' => $trashedHouse->id,
                'role' => 'admin',
                'status' => 'admin',
                'active_mode' => 'house',
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return [
                'success' => true,
                'mode' => 'house',
                'token' => $token,
                'user' => $user->fresh(),
                'email_verified' => false,
            ];
        }

        // =====================================================
        //  3. CREATE NEW HOUSE
        // =====================================================
        $house = House::create([
            'name' => $user->name."'s House",
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

        // Karma: House Starter +100 (best-effort)
        try {
            app(KarmaService::class)->add($user, 100, 'house_starter');
        } catch (\Throwable $e) {
        }

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
