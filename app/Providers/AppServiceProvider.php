<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Tag;
use App\Policies\TenantPolicy;
use Illuminate\Support\Facades\Gate;
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
        foreach ([Product::class, PurchaseOrder::class, Stock::class, Supplier::class, Tag::class] as $model) {
            Gate::policy($model, TenantPolicy::class);
        }
    }
}
