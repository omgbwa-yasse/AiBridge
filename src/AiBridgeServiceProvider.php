<?php

namespace AiBridge;

use Illuminate\Support\ServiceProvider;

class AiBridgeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/AiBridge.php', 'aibridge');
        $this->app->singleton('AiBridge', function ($app) {
            $configRepo = $app['config'] ?? null;
            $cfg = (is_object($configRepo) && method_exists($configRepo, 'get'))
                ? $configRepo->get('aibridge')
                : [];
            return new AiBridgeManager($cfg);
        });
    }

    public function boot()
    {
        if (method_exists($this->app, 'configPath')) {
            $target = $this->app->configPath('aibridge.php');
        } elseif (method_exists($this->app, 'basePath')) {
            $target = $this->app->basePath('config'.DIRECTORY_SEPARATOR.'aibridge.php');
        } else {
            $target = __DIR__.'/../config/aibridge.php';
        }

        $this->publishes([
            __DIR__.'/../config/AiBridge.php' => $target,
        ], 'config');
    }
}
