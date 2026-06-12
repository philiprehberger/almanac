<?php

namespace App\Providers;

use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\Contracts\EmbedProvider;
use App\Services\Llm\LlmAdapterFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LlmAdapterFactory::class);

        $this->app->bind(ChatProvider::class, function ($app) {
            return $app->make(LlmAdapterFactory::class)->chat();
        });

        $this->app->bind(EmbedProvider::class, function ($app) {
            return $app->make(LlmAdapterFactory::class)->embed();
        });
    }

    public function boot(): void
    {
        //
    }
}
