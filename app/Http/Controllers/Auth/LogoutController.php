<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $this->closeLoginActivity($request, $userId);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginUrl = Route::has('login') ? route('login') : url('/login');

        return redirect($loginUrl)->with('success', 'تم تسجيل الخروج بأمان.');
    }

    private function closeLoginActivity(Request $request, int $userId): void
    {
        $activityId = $request->session()->get('login_activity_id');

        LoginActivity::query()
            ->where('user_id', $userId)
            ->whereNull('logout_at')
            ->when(
                is_numeric($activityId),
                fn (Builder $query): Builder => $query->whereKey((int) $activityId),
                fn (Builder $query): Builder => $query->where('session_identifier', $request->session()->getId()),
            )
            ->latest('login_at')
            ->first()
            ?->update(['logout_at' => now()]);
    }
}
