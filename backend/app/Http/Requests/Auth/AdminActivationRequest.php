<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AdminActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email'))
                ? strtolower(trim($this->input('email')))
                : $this->input('email'),
            'token' => is_string($this->input('token')) ? trim($this->input('token')) : $this->input('token'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string', 'min:32'],
            'invitation' => ['sometimes', 'integer', 'min:1'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
