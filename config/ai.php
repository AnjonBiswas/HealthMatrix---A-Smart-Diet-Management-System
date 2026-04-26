<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!defined('OPENROUTER_API_KEY')) {
    define('OPENROUTER_API_KEY', '-----------------------');
}

if (!defined('OPENROUTER_MODEL')) {
    define('OPENROUTER_MODEL', 'openai/gpt-oss-120b:free');
}

if (!defined('OPENROUTER_FALLBACK_MODEL')) {
    define('OPENROUTER_FALLBACK_MODEL', 'google/gemma-3-27b-it:free');
}

if (!defined('OPENROUTER_URL')) {
    define('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions');
}
