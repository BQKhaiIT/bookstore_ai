<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw new HttpResponseException(response()->json([
                'message' => 'Email hoặc mật khẩu không hợp lệ.',
                'code' => 'INVALID_CREDENTIALS',
                'errors' => (object) [],
            ], 422));
        }

        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null && $user->status === UserStatus::Blocked) {
            Auth::logout();

            throw new HttpResponseException(response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa.',
                'code' => 'ACCOUNT_BLOCKED',
                'errors' => (object) [],
            ], 422));
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => ["Quá nhiều lần đăng nhập. Vui lòng thử lại sau {$seconds} giây."],
        ])->status(429);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
