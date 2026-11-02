<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Episode 22. Turns an N+1 from a silent slowdown into a loud exception,
        // immediately, on the machine of whoever wrote it.
        //
        // Off in production deliberately: at the till, a slow response beats a
        // 500. Fail loudly where it is cheap, gracefully where it is not.
        Model::preventLazyLoading(! app()->isProduction());
    }
}
