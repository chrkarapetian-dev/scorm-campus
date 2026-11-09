<?php

namespace App\Providers;

use App\Services\CourseTextRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request, so the controller and the search share one cache read.
        $this->app->scoped(CourseTextRepository::class);
    }

    public function boot(): void
    {
        // Per-minute limits stop bursts; daily caps bound what one account can spend on the API,
        // since unanswerable questions and identical failed retries don't use up any allowance.
        RateLimiter::for('assignments', fn (Request $request) => array_map(
            fn (Limit $limit) => $limit->response(fn () => response()->json(['error' => __('assignment.rate_limited')], 429)),
            [
                Limit::perMinute(5)->by('assignment:'.$request->user()->id),
                Limit::perDay(30)->by('assignment-day:'.$request->user()->id),
            ],
        ));

        RateLimiter::for('assistant', fn (Request $request) => array_map(
            fn (Limit $limit) => $limit->response(fn () => response()->json(
                ['error' => 'Հարցումների սահմանաչափը լրացել է։ Սպասեք և փորձեք կրկին ավելի ուշ։'],
                429,
            )),
            [
                Limit::perMinute(20)->by('assistant:'.($request->user()?->id ?: $request->ip())),
                Limit::perDay(100)->by('assistant-day:'.($request->user()?->id ?: $request->ip())),
            ],
        ));
    }
}
