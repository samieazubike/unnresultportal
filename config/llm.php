<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Recommendation phrasing driver
    |--------------------------------------------------------------------------
    |
    | README §8 step 3: an LLM phrases the advice from facts the SQL layer has
    | already computed. Step 5 requires the system to keep working with no
    | internet, so any failure here falls back to deterministic rule-based
    | phrasing rather than surfacing an error.
    |
    | Set 'driver' to 'null' to skip the model entirely and always use the
    | fallback.
    |
    */

    'driver' => env('LLM_DRIVER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2'),

        // Kept short: a slow or absent model must not stall a dashboard.
        'timeout' => (int) env('OLLAMA_TIMEOUT', 8),
    ],

];
