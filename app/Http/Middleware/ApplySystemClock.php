<?php

namespace App\Http\Middleware;

use App\Support\SystemClock;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the admin has enabled a date/time override, set Carbon's "now" to the
 * effective time for this request. Scoped to admin users only so live cashier
 * sales are never backdated. Affects now() and Eloquent created_at/updated_at.
 */
class ApplySystemClock
{
    public function handle(Request $request, Closure $next): Response
    {
        $apply = SystemClock::isActive() && $request->user()?->hasRole('admin');

        if ($apply) {
            Carbon::setTestNow(Carbon::createFromTimestamp(time() + SystemClock::offsetSeconds()));
        }

        try {
            return $next($request);
        } finally {
            if ($apply) {
                Carbon::setTestNow();
            }
        }
    }
}
