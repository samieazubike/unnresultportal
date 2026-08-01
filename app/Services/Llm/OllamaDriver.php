<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phrasing via a local Ollama server.
 *
 * Local by design: README §8.5 requires the system to work with no internet,
 * and a model running on the same machine keeps student data on the machine
 * too. If Ollama is not installed or not running, this throws and the caller
 * renders the rule-based fallback instead.
 */
class OllamaDriver implements LlmDriver
{
    public function __construct(
        private readonly string $url,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post(rtrim($this->url, '/').'/api/generate', [
                    'model' => $this->model,
                    'system' => $systemPrompt,
                    'prompt' => $userPrompt,
                    'stream' => false,
                    'options' => [
                        // Low temperature: this is advice grounded in fixed
                        // facts, not creative writing.
                        'temperature' => 0.2,
                        'num_predict' => 220,
                    ],
                ]);
        } catch (Throwable $exception) {
            throw new LlmUnavailable("Ollama request failed: {$exception->getMessage()}", previous: $exception);
        }

        if ($response->failed()) {
            throw new LlmUnavailable("Ollama returned HTTP {$response->status()}.");
        }

        $text = trim((string) $response->json('response'));

        if ($text === '') {
            throw new LlmUnavailable('Ollama returned an empty completion.');
        }

        return $text;
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->model !== '';
    }
}
