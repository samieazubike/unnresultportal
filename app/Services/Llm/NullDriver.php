<?php

namespace App\Services\Llm;

/**
 * Always declines, so the rule-based fallback renders. Set LLM_DRIVER=null to
 * take the model out of the loop entirely.
 */
class NullDriver implements LlmDriver
{
    public function complete(string $systemPrompt, string $userPrompt): string
    {
        throw new LlmUnavailable('No language model is configured.');
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
