<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatically ensure database schema migrations are applied on deployment
        try {
            if (
                (Schema::hasTable('products') && ! Schema::hasColumn('products', 'product_type')) ||
                (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'invoice_type'))
            ) {
                Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            Log::warning('AppServiceProvider auto-migrate notice: ' . $e->getMessage());
        }
    }
}
