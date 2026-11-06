<?php

use App\Http\Controllers\CourseContentController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Course files load in bulk and need no session, so they stay outside the web group.
            Route::get('/content/{course}/{path}', CourseContentController::class)
                ->where(['course' => '[a-f0-9]{16}', 'path' => '.+'])
                ->name('course.content');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Comma-separated proxy IPs, or "*", when running behind a TLS-terminating proxy.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }

        $middleware->api(append: ['cache.headers:no_store']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['error' => 'Հարց տալու համար մուտք գործեք ձեր հաշիվ։'], 401);
            }
        });

        $exceptions->render(fn (PostTooLargeException $e, Request $request) => response()->json(
            ['error' => 'Փոխանցվող ֆայլը գերազանցում է թույլատրելի չափը։'],
            413,
        ));

        $notFound = function (HttpExceptionInterface $e, Request $request) {
            return $request->is('api/*')
                ? response()->json(['error' => 'Այս հասցեն հասանելի չէ։'], 404)
                : response('Չի գտնվել։', 404)->header('Content-Type', 'text/plain; charset=utf-8');
        };
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $notFound($e, $request));
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $notFound($e, $request));

        // Runs on the finished response, so exceptions carrying their own response (like a 429) keep it.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->is('api/*') && $response->getStatusCode() >= 500 && ! config('app.debug')) {
                return response()->json(['error' => 'Հարցումը հնարավոր չեղավ մշակել։'], 500);
            }

            return $response;
        });
    })->create();
