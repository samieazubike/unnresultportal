<?php

namespace App\Services\Llm;

use RuntimeException;

/**
 * Raised whenever phrasing cannot be obtained. Always recoverable: the caller
 * falls back to deterministic rule-based text (README §8.5).
 */
class LlmUnavailable extends RuntimeException {}
