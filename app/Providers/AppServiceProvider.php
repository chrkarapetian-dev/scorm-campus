<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('assistant', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json(
                ['error' => 'Հարցումների սահմանաչափը լրացել է։ Սպասեք մեկ րոպե և փորձեք կրկին։'],
                429,
            )));
    }
}
