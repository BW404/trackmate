<?php
/**
 * TrackMate - Ollama AI Configuration
 * Configure your Ollama server URL and model settings here
 */

// Ollama Server Configuration
define('OLLAMA_API_URL', 'http://100.105.118.56:11433/api/generate');
define('OLLAMA_MODEL', 'qwen3-vl:2b'); // Options: qwen3-vl:2b, llava:7b, llava:13b, etc.

// Performance Settings
define('OLLAMA_TIMEOUT', 45); // Request timeout in seconds
define('OLLAMA_NUM_PREDICT', 100); // Max tokens for response
define('OLLAMA_TEMPERATURE', 0.3); // Lower = more focused responses (0.0-1.0)

// Image Processing
define('IMAGE_MAX_SIZE', 320); // Max width/height in pixels (smaller = faster)
define('IMAGE_QUALITY', 80); // JPEG quality 1-100 (lower = faster)

// Caching
define('ANALYSIS_INTERVAL', 2000); // Minimum ms between new analyses
define('CACHE_DURATION', 3); // Seconds to cache identical images
?>
