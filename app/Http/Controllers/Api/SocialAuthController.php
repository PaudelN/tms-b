<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['google', 'github'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->assertValidProvider($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->assertValidProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (Throwable $e) {
            return redirect(config('app.frontend_url').'/login?error=social_auth_failed');
        }

        $socialAccount = \App\Models\SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            $socialAccount->update([
                'avatar' => $socialUser->getAvatar(),
                'access_token' => $socialUser->token,
            ]);
            $user = $socialAccount->user;
        } else {
            $user = User::firstOrCreate(
                ['email' => $socialUser->getEmail()],
                [
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'nickname' => $socialUser->getNickname(),
                'access_token' => $socialUser->token,
            ]);
        }

        Auth::guard('web')->login($user, remember: true);

        // Regenerate AND save the session explicitly before redirecting.
        // Without save(), the session data may not be written to storage
        // before the redirect fires, causing the next request to see no session.
        $request = request();
        $request->session()->regenerate();
        $request->session()->save();  // ← this is the critical addition

        return redirect(config('app.frontend_url').'/auth/social/callback');
    }

    private function assertValidProvider(string $provider): void
    {
        abort_unless(
            in_array($provider, self::ALLOWED_PROVIDERS, true),
            404,
            "Provider [{$provider}] is not supported."
        );
    }
}
