<?php

namespace FunnyDev\Ninepay;

use App\Http\Middleware\NinepayMiddleware;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class NinepayServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__.'/../config/ninepay.php' => config_path('ninepay.php'),
            __DIR__.'/../app/Http/Controllers/NinepayController.php' => app_path('Http/Controllers/NinepayController.php'),
            __DIR__.'/../app/Http/Middleware/NinepayMiddleware.php' => app_path('Http/Middleware/NinepayMiddleware.php'),
        ], 'ninepay');

        try {
            if (!file_exists(config_path('ninepay.php'))) {
                $this->commands([
                    \Illuminate\Foundation\Console\VendorPublishCommand::class,
                ]);

                Artisan::call('vendor:publish', ['--provider' => 'FunnyDev\\Ninepay\\NinepayServiceProvider', '--tag' => ['ninepay']]);
            }
        } catch (\Exception $e) {}
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/ninepay.php', 'ninepay'
        );
        $this->app->singleton(\FunnyDev\Ninepay\NinepaySdk::class, function ($app) {
            $merchant = $app['config']['ninepay.merchant'];
            $secret = $app['config']['ninepay.secret'];
            $sum = $app['config']['ninepay.sum'];
            $server = $app['config']['ninepay.server'];
            return new \FunnyDev\Ninepay\NinepaySdk($merchant, $secret, $sum, $server);
        });
    }
}
