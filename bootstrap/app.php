<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \viki\Service\ServiceBaseServiceProvider::class,
        \App\Providers\Filament\ServicePanelProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Register Viki middleware
        $middleware->alias([
            'application.permission' => \viki\Service\Http\Middleware\ApplicationPermission::class,
            'admin.permission' => \viki\Service\Http\Middleware\AdminPermission::class,
            'check.role' => \viki\Service\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
