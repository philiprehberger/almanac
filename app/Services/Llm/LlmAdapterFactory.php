<?php

namespace App\Services\Llm;

use App\Services\Llm\Adapters\AnthropicChatProvider;
use App\Services\Llm\Adapters\MockChatProvider;
use App\Services\Llm\Adapters\MockEmbedProvider;
use App\Services\Llm\Adapters\OllamaChatProvider;
use App\Services\Llm\Adapters\OpenAiChatProvider;
use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\Contracts\EmbedProvider;

class LlmAdapterFactory
{
    public function chat(?string $provider = null): ChatProvider
    {
        $provider ??= config('almanac.default_chat_provider', 'mock');

        return match ($provider) {
            'mock' => new MockChatProvider(),
            'openai' => new OpenAiChatProvider(
                apiKey: (string) config('almanac.openai_api_key'),
                model: (string) config('almanac.openai_chat_model', 'gpt-4o-mini'),
            ),
            'anthropic' => new AnthropicChatProvider(
                apiKey: (string) config('almanac.anthropic_api_key'),
                model: (string) config('almanac.anthropic_chat_model', 'claude-sonnet-4-6'),
            ),
            'ollama' => new OllamaChatProvider(
                baseUrl: (string) config('almanac.ollama_base_url'),
                model: (string) config('almanac.ollama_chat_model', 'llama3'),
            ),
            default => throw new \InvalidArgumentException("Unknown chat provider: {$provider}"),
        };
    }

    public function embed(?string $provider = null): EmbedProvider
    {
        $provider ??= config('almanac.default_embed_provider', 'mock');

        return match ($provider) {
            'mock' => new MockEmbedProvider(),
            // OpenAI text-embedding-3-small adapter omitted for portfolio
            // demo since mock-mode is the default. Self-hosted deploys can
            // wire it via a sibling adapter following the OpenAiChatProvider
            // shape.
            default => throw new \InvalidArgumentException("Unknown embed provider: {$provider}"),
        };
    }
}
