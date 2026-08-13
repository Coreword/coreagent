<?php

namespace App\Providers;

use App\Services\Agent\Tools\CheckVideoStatusTool;
use App\Services\Agent\Tools\GenerateVideoTool;
use App\Services\Agent\Tools\GetCaseTool;
use App\Services\Agent\Tools\ListCasesTool;
use App\Services\Agent\ToolRegistry;
use App\Services\Llm\AnthropicProvider;
use App\Services\Llm\OpenAiCompatibleProvider;
use App\Services\Llm\ProviderRouter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            return (new ToolRegistry())
                ->register(new ListCasesTool())
                ->register(new GetCaseTool())
                ->register($app->make(GenerateVideoTool::class))
                ->register($app->make(CheckVideoStatusTool::class));
        });

        $this->app->singleton(ProviderRouter::class, function () {
            return new ProviderRouter([
                'openai' => new OpenAiCompatibleProvider('openai', config('providers.openai')),
                'deepseek' => new OpenAiCompatibleProvider('deepseek', config('providers.deepseek')),
                'claude' => new AnthropicProvider(config('providers.claude')),
                'qwen_gpu' => new OpenAiCompatibleProvider('qwen_gpu', config('providers.qwen_gpu')),
                'qwen_lora' => new OpenAiCompatibleProvider('qwen_lora', config('providers.qwen_lora')),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
