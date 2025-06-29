<?php


namespace viki\Service;


use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use viki\Service\Console\Commands\Archive;
use viki\Service\Facades\Service;
use Illuminate\Console\Scheduling\Schedule;

class ServiceBaseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPublishing();
        $this->registerResources();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Archive::class
            ]);
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('archive:start')->everyMinute();
        });

    }

    public function register()
    {
        
    }

    private function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'service');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'service');
        $this->registerFacades();

        $this->registerRoutes();

        $this->registerScripts();
        $this->registerStyles();

    }

    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    private function routeConfiguration()
    {
        return [
            'prefix' => Service::routePrefix(),
            'namespace' => 'viki\Service\Http\Controllers'
        ];
    }

    protected function registerPublishing()
    {
        $this->publishes([
            __DIR__ . '/../config/service.php' => config_path('service.php')
        ], 'service-config');
    }

    protected function registerFacades()
    {
        $this->app->singleton('Service', function ($app) {
            return new \viki\Service\Service();
        });
    }

    protected function registerScripts()
    {
        $this->publishes([
            __DIR__.'/../resources/assets/js' => public_path('script/service'),
        ], 'service-public');
    }

    protected function registerStyles()
    {
        $this->publishes([
            __DIR__.'/../resources/assets/css' => public_path('style/service'),
        ], 'service-public');
    }
}