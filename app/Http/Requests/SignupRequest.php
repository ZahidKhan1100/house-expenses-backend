<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            // allow re-signup if the previous account was soft-deleted
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|string|min:6',
            'house_code' => 'nullable|string|max:10',
            'mode' => 'nullable|string|in:trip,house',
        ];
    }
}