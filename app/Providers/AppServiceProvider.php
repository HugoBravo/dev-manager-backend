<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Default `throttle:api` rate limiter — required by routes/api/v1.php.
        // Mirrors Laravel's stock default (60 req/min per user or IP) so the
        // throttle:api middleware on every v1 route resolves.
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Explicit policy mapping for Project (chokepoint policy). Laravel 13
        // auto-discovers App\Policies\*Policy for App\Models\* in most cases,
        // but binding it explicitly here documents the ownership contract and
        // future-proofs against namespace shifts.
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
