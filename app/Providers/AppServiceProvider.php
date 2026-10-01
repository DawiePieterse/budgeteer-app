<?php

namespace App\Providers;

use App\Models\Transaction;
use Illuminate\Support\Facades\View;
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
        // The Review tab shows how many transactions are still to be put somewhere.
        View::composer('layouts.app', function ($view) {
            $view->with('reviewCount', auth()->check() ? Transaction::query()->toReview()->count() : 0);
        });
    }
}
