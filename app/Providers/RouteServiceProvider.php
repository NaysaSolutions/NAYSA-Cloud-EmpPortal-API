<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | General API Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Used for normal Employee Portal API requests.
        | Higher limit because many requests can happen when dashboards load.
        |
        */
        RateLimiter::for('api', function (Request $request) {

            $userKey =
                $request->user()?->getAuthIdentifier()
                ?? $request->input('empno')
                ?? $request->input('empNo')
                ?? $request->ip();

            return Limit::perMinute(1000)->by(
                'api|' . strtolower((string) $userKey)
            );
        });


        /*
        |--------------------------------------------------------------------------
        | Login Rate Limiter
        |--------------------------------------------------------------------------
        |
        | Keep login protected against brute-force attempts.
        | Every employee + IP combination gets its own limit.
        |
        */
        RateLimiter::for('login', function (Request $request) {

            $empno = strtolower(
                (string) (
                    $request->input('empno')
                    ?? $request->input('empNo')
                    ?? 'unknown'
                )
            );

            return Limit::perMinute(20)->by(
                'login|' . $empno . '|' . $request->ip()
            );
        });


        $this->routes(function () {

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}