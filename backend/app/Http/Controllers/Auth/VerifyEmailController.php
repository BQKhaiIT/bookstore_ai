<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if (! $request->hasValidSignature()) {
            return redirect()->away($frontendUrl.'/email-verified?status=invalid');
        }

        $user = User::find($id);

        if ($user === null) {
            return redirect()->away($frontendUrl.'/email-verified?status=invalid');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->away($frontendUrl.'/email-verified?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($frontendUrl.'/email-verified?status=already');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->away($frontendUrl.'/email-verified?status=success');
    }
}
