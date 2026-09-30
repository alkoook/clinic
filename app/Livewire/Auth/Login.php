<?php

namespace App\Livewire\Auth;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('تسجيل الدخول')]
class Login extends Component
{
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * @throws ValidationException
     */
    public function authenticate(): mixed
    {
        $this->username = $this->normalizedUsername();

        $validated = $this->validate(
            [
                'username' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'max:1024'],
                'remember' => ['boolean'],
            ],
            [
                'username.required' => 'يرجى إدخال اسم المستخدم.',
                'username.max' => 'اسم المستخدم أطول من الحد المسموح.',
                'password.required' => 'يرجى إدخال كلمة المرور.',
            ],
            [
                'username' => 'اسم المستخدم',
                'password' => 'كلمة المرور',
            ],
        );

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'username' => $validated['username'],
            'password' => $validated['password'],
            'is_active' => true,
        ], $validated['remember'])) {
            RateLimiter::hit($this->throttleKey(), $this->decaySeconds());
            $this->reset('password');

            throw ValidationException::withMessages([
                'username' => 'اسم المستخدم أو كلمة المرور غير صحيحة.',
            ]);
        }

        session()->regenerate();
        RateLimiter::clear($this->throttleKey());

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        $activity = LoginActivity::query()->create([
            'user_id' => $user->getAuthIdentifier(),
            'session_identifier' => session()->getId(),
            'login_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => Str::limit(request()->userAgent() ?? '', 2000, ''),
            'device_name' => $this->deviceDescription(request()->userAgent()),
        ]);

        session()->put('login_activity_id', $activity->getKey());

        $fallback = Route::has('dashboard') ? route('dashboard') : url('/');

        return $this->redirectIntended($fallback, navigate: true);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $this->reset('password');

        throw ValidationException::withMessages([
            'username' => "محاولات كثيرة. يرجى المحاولة مجددًا بعد {$seconds} ثانية.",
        ]);
    }

    private function normalizedUsername(): string
    {
        return Str::lower(Str::squish($this->username));
    }

    private function throttleKey(): string
    {
        return hash('sha256', $this->normalizedUsername().'|'.request()->ip());
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('clinic.auth.login.max_attempts', 5));
    }

    private function decaySeconds(): int
    {
        return max(1, (int) config('clinic.auth.login.decay_seconds', 60));
    }

    private function deviceDescription(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'جهاز غير معروف';
        }

        $device = match (true) {
            Str::contains($userAgent, ['iPad', 'Tablet']) => 'جهاز لوحي',
            Str::contains($userAgent, ['Mobile', 'Android', 'iPhone']) => 'هاتف',
            default => 'حاسوب',
        };

        $browser = match (true) {
            Str::contains($userAgent, ['Edg/']) => 'Edge',
            Str::contains($userAgent, ['OPR/', 'Opera']) => 'Opera',
            Str::contains($userAgent, ['Chrome/', 'CriOS/']) => 'Chrome',
            Str::contains($userAgent, ['Firefox/', 'FxiOS/']) => 'Firefox',
            Str::contains($userAgent, ['Safari/']) => 'Safari',
            default => 'متصفح غير معروف',
        };

        return $device.' · '.$browser;
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
