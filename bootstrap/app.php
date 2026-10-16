<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Unknown route, or abort(404). Hides the attempted path.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        // Model not found (findOrFail / route-model binding). Callbacks run
        // BEFORE Laravel converts this to a 404, so it must be caught here.
        // Hides the model class name and id.
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        // Wrong HTTP verb. Hides the "Supported methods: ..." list.
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Method not allowed.'], 405);
            }
        });

        // Safety net: anything unexpected becomes a generic 500, even if
        // APP_DEBUG is accidentally true. The real error is still logged
        // (reporting is separate from rendering).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Let Laravel handle the ones that already return safe responses.
            if ($e instanceof ValidationException           // 422
                || $e instanceof AuthenticationException    // 401
                || $e instanceof AuthorizationException     // 403
                || $e instanceof TokenMismatchException     // 419
                || $e instanceof HttpResponseException      // abort(response()->json(...))
                || $e instanceof HttpExceptionInterface) {  // 429, abort(4xx), etc.
                return null;
            }

            return response()->json(['message' => 'Server error.'], 500);
        });
    })->create();