<?php

namespace RivascoTech\FedaPay;

use Illuminate\Support\ServiceProvider;

class FedaPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/fedapay.php', 'fedapay');

        // Enregistre FedaPayManager comme singleton
        $this->app->singleton(FedaPayManager::class, fn() => new FedaPayManager());
    }

    public function boot(): void
    {
        // ── Config publiable ─────────────────────────────────────────────
        $this->publishes([
            __DIR__ . '/../config/fedapay.php' => config_path('fedapay.php'),
        ], 'fedapay-config');

        // ── Migrations publiables ────────────────────────────────────────
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'fedapay-migrations');

        // ── Chargement des migrations (auto, sans publish) ───────────────
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // ── Routes webhook ───────────────────────────────────────────────
        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
    }
}
