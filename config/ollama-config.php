<?php
/**
 * TrackMate - Ollama AI Configuration
 * Configure your Ollama server URL and model settings here
 */

// Ollama Server Configuration
define('OLLAMA_API_URL', 'https://ollama1.bw404.com/api/generate');
define('OLLAMA_MODEL', 'qwen3-vl:2b'); // Options: qwen3-vl:2b, llava:7b, llava:13b, etc.

// Activity Categories
define('ACTIVITY_CATEGORIES', [
    1 => 'Using phone',
    2 => 'Working',
    3 => 'Using phone while working',
    4 => 'Sleeping',
    5 => 'Eating',
    6 => 'Drinking',
    7 => 'Other'
]);

// Performance Settings
define('OLLAMA_TIMEOUT', 45); // Request timeout in seconds
define('OLLAMA_CONNECT_TIMEOUT', 10); // Connection timeout in seconds
define('OLLAMA_NUM_PREDICT', 100); // Max tokens for response
define('OLLAMA_TEMPERATURE', 0.3); // Lower = more focused responses (0.0-1.0)

// SSL/TLS Settings
define('OLLAMA_SSL_VERIFY_PEER', false); // Set to true in production with valid SSL
define('OLLAMA_SSL_VERIFY_HOST', 0); // Set to 2 in production with valid SSL

// Image Processing
define('IMAGE_MAX_SIZE', 320); // Max width/height in pixels (smaller = faster)
define('IMAGE_QUALITY', 80); // JPEG quality 1-100 (lower = faster)

// Caching
define('ANALYSIS_INTERVAL', 2000); // Minimum ms between new analyses
define('CACHE_DURATION', 0); // Seconds to cache identical images (set to 0 to disable)
?>
