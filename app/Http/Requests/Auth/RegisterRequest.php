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
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users', 'alpha_dash'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()
            ],
            'role' => ['required', 'in:user,dj'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'username.required' => 'Username is required',
            'username.unique' => 'This username is already taken',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes and underscores',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already registered',
            'password.required' => 'Password is required',
            'role.required' => 'Role is required',
            'role.in' => 'Invalid role selected',
        ];
    }
} 