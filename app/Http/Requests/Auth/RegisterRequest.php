<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            'password' => [
                'required',
                'confirmed',
                'string',
                Password::min(8)->numbers(),
            ],

            'phone_country_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,4}$/'],
            'phone_number' => ['required', 'string', 'max:32', 'regex:/^[0-9 \-]{6,}$/'],

            // The checkbox is a declaration, not the gate. The real 18+ check
            // is a server-side comparison against the date of birth when the
            // member reaches the Personal step of the profile builder — a
            // checkbox proves nothing and can be ticked by anyone.
            'is_adult' => ['accepted'],
            'accepts_terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password needs at least 8 characters, including one number.',
            'password.numbers' => 'Password needs at least 8 characters, including one number.',
            'password.confirmed' => 'The two passwords do not match.',
            'phone_number.regex' => 'Enter your mobile number using digits only.',
            'phone_country_code.regex' => 'Choose a country code.',
            'is_adult.accepted' => 'RealVows is for members aged 18 and over.',
            'accepts_terms.accepted' => 'Please accept the Terms of Service and Privacy Policy to continue.',
        ];
    }
}
