<?php

namespace Agenciafmd\Rdstation\Providers;

use Illuminate\Support\ServiceProvider;

class RdstationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // 
    }

    public function register()
    {
        $this->loadConfigs();
    }

    protected function loadConfigs()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-rdstation.php', 'laravel-rdstation');
    }
}
