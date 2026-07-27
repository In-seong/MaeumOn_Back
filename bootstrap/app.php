<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 미들웨어 alias 등록
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'api-key' => \App\Http\Middleware\CheckApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API 예외를 JSON으로 응답
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => '리소스를 찾을 수 없습니다.',
                ], 404);
            }
        });
    })->create();
