<?php

namespace App\Actions\Trip;

use App\Models\Trip;
use Illuminate\Support\Str;

class CreateTrip
{
    public function execute(array $data, $adminId): Trip
    {
        // Auto-generate a trip code, retrying on the rare collision against the unique constraint
        do {
            $code = strtoupper(Str::random(6));
        } while (Trip::where('code', $code)->exists());

        return Trip::create([
            'name' => $data['name'],
            'code' => $code,
            'admin_id' => $adminId,
            'currency' => $data['currency'] ?? '$',
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'active',
            'description' => $data['description'] ?? null,
            'budget' => $data['budget'] ?? null,
        ]);
    }
}