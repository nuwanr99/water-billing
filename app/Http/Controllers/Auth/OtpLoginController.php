<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SriLankanPhoneNumber;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * Passwordless login: a one-time code is generated for the account matching
 * the given phone number, delivered over WhatsApp, and exchanged for an
 * authenticated session.
 */
class OtpLoginController extends Controller
{
    private const CODE_TTL_MINUTES = 5;

    public function __construct(protected WhatsAppService $whatsapp) {}

    /**
     * Generate a one-time code and deliver it via WhatsApp.
     */
    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', new SriLankanPhoneNumber],
        ]);

        $user = $this->findUserByPhone($validated['phone']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => __('We could not find an account with that phone number.'),
            ]);
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user), Hash::make($code), now()->plus(minutes: self::CODE_TTL_MINUTES));

        $sent = $this->whatsapp->sendText(
            $user->phone,
            __(':app login code: :code (expires in :minutes minutes). Do not share this code with anyone.', [
                'app' => config('app.name'),
                'code' => $code,
                'minutes' => self::CODE_TTL_MINUTES,
            ]),
        );

        if (! $sent) {
            Cache::forget($this->cacheKey($user));

            throw ValidationException::withMessages([
                'phone' => __('We could not deliver the code over WhatsApp. Please try again or log in with your password.'),
            ]);
        }

        return back()->with('status', __('We sent a login code to your WhatsApp number.'));
    }

    /**
     * Exchange a valid one-time code for an authenticated session.
     */
    public function verify(Request $request): mixed
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', new SriLankanPhoneNumber],
            'code' => ['required', 'digits:6'],
        ]);

        $user = $this->findUserByPhone($validated['phone']);
        $hashedCode = $user === null ? null : Cache::get($this->cacheKey($user));

        if ($hashedCode === null || ! Hash::check($validated['code'], $hashedCode)) {
            throw ValidationException::withMessages([
                'code' => __('The code is invalid or has expired.'),
            ]);
        }

        Cache::forget($this->cacheKey($user));

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return app(LoginResponseContract::class)->toResponse($request);
    }

    /**
     * Match a phone number against users' phone numbers in bare
     * (7XXXXXXXX), local (0XXXXXXXXX), and international (+94XXXXXXXXX)
     * forms.
     */
    private function findUserByPhone(string $phone): ?User
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $subscriber = match (true) {
            str_starts_with($digits, '94') => substr($digits, 2),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        $variants = [$subscriber, '0'.$subscriber, '94'.$subscriber, '+94'.$subscriber];

        return User::query()->whereIn('phone', $variants)->first();
    }

    private function cacheKey(User $user): string
    {
        return "login-otp:{$user->id}";
    }
}
