<?php

namespace App\Providers;

use App\Contracts\AssistantExplanationProvider;
use App\Models\User;
use App\Services\AssistantService;
use App\Services\LlmExplanationProvider;
use App\Services\ProductImageService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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
        $this->app->scoped(ProductImageService::class);
        $this->app->bind(AssistantExplanationProvider::class, LlmExplanationProvider::class);
        $this->app->bind(AssistantService::class, fn ($app) => new AssistantService(
            config('services.llm.enabled') && filled(config('services.llm.api_key')) && filled(config('services.llm.model'))
                ? $app->make(AssistantExplanationProvider::class) : null
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);
        Paginator::useBootstrapFive();
        RateLimiter::for('session-writes', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));
    }
}
