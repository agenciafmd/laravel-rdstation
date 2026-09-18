<?php

declare(strict_types=1);

namespace Agenciafmd\Rdstation\Providers;

use Illuminate\Support\ServiceProvider;

final class RdstationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        $this->loadConfigs();
    }

    private function loadConfigs(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-rdstation.php', 'laravel-rdstation');
    }
}
