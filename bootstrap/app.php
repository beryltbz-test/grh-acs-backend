<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,      
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Non authentifié'], 401);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('rapports:rappel-midi-vendredi')
            ->fridays()
            ->at('12:15')
            ->timezone('Africa/Porto-Novo')
            ->withoutOverlapping();

        $schedule->command('rapports:verifier-lundi')
            ->mondays()
            ->at('09:05')
            ->timezone('Africa/Porto-Novo')
            ->withoutOverlapping();
    })
    ->create();