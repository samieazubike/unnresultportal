<?php

namespace App\Providers;

use App\Models\Result;
use App\Observers\ResultObserver;
use App\Services\Llm\LlmDriver;
use App\Services\Llm\NullDriver;
use App\Services\Llm\OllamaDriver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmDriver::class, function (): LlmDriver {
            $ollama = config('llm.ollama');

            return match (config('llm.driver')) {
                'ollama' => new OllamaDriver($ollama['url'], $ollama['model'], $ollama['timeout']),
                default => new NullDriver,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Append-only audit trail for every score write and status transition.
        Result::observe(ResultObserver::class);

        // Results travel over TLS everywhere but local development.
        if (! app()->environment('local', 'testing')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
