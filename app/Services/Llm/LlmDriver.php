<?php

namespace App\Services\Llm;

interface LlmDriver
{
    /**
     * Turn a set of already-computed facts into prose.
     *
     * Implementations receive the system prompt and the fact payload only.
     * They never see raw result rows — see RecommendationService.
     *
     * @throws LlmUnavailable when the model cannot be reached or replies badly
     */
    public function complete(string $systemPrompt, string $userPrompt): string;

    public function isConfigured(): bool;
}
