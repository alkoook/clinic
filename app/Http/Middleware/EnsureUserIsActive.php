<?php

namespace App\Http\Middleware;

use App\Models\LoginActivity;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        $activityId = $request->session()->get('login_activity_id');

        LoginActivity::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('logout_at')
            ->when(
                is_numeric($activityId),
                fn (Builder $query): Builder => $query->whereKey((int) $activityId),
                fn (Builder $query): Builder => $query->where('session_identifier', $request->session()->getId()),
            )
            ->latest('login_at')
            ->first()
            ?->update(['logout_at' => now()]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'تم تعطيل هذا الحساب.'], Response::HTTP_FORBIDDEN);
        }

        return redirect('/login')->with('error', 'تم تعطيل هذا الحساب. راجع إدارة العيادة.');
    }
}
