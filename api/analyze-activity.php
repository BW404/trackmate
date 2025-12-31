<?php
/**
 * TrackMate - Activity Analysis API
 * Directly calls Ollama API to analyze user activity from camera
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once '../config/config.php';
require_once '../config/ollama-config.php';

// Start session to get user ID (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

// Use configuration from ollama-config.php
$ACTIVITIES = ACTIVITY_CATEGORIES;

/**
 * Resize image for faster processing
 */
function resizeImageForAnalysis($imageData, $maxSize = null) {
    if ($maxSize === null) {
        $maxSize = IMAGE_MAX_SIZE;
    }
    
    try {
        // Remove data URL prefix if present
        if (strpos($imageData, ',') !== false) {
            $imageData = explode(',', $imageData)[1];
        }
        
        $imageBytes = base64_decode($imageData);
        $image = imagecreatefromstring($imageBytes);
        
        if (!$image) {
            return $imageData;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Calculate new dimensions
        if (max($width, $height) > $maxSize) {
            if ($width > $height) {
                $newWidth = $maxSize;
                $newHeight = (int)($height * ($maxSize / $width));
            } else {
                $newHeight = $maxSize;
                $newWidth = (int)($width * ($maxSize / $height));
            }
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            ob_start();
            imagejpeg($resized, null, IMAGE_QUALITY);
            $resizedBytes = ob_get_clean();
            
            imagedestroy($image);
            imagedestroy($resized);
            
            return base64_encode($resizedBytes);
        }
        
        imagedestroy($image);
        return $imageData;
        
    } catch (Exception $e) {
        error_log("Image resize error: " . $e->getMessage());
        return $imageData;
    }
}

/**
 * Parse AI response and classify activity
 */
function parseActivityResponse($responseText, $activities) {
    $resultLower = strtolower($responseText);
    
    error_log("[" . date('H:i:s') . "] PARSING: " . $responseText);
    
    // Check if response starts with a number (1-7)
    if (preg_match('/^\s*(\d+)/', $responseText, $matches)) {
        $number = (int)$matches[1];
        error_log("[" . date('H:i:s') . "] AI chose category: " . $number);
        
        // Less aggressive override - only force Working if VERY clear working indicators AND clear NOT holding phone
        if (($number == 1 || $number == 3)) {
            // Check for explicit "NOT holding phone" statements
            $clearlyNotHoldingPhone = (
                strpos($resultLower, 'not holding phone') !== false ||
                strpos($resultLower, 'phone on desk') !== false ||
                strpos($resultLower, 'phone on table') !== false ||
                strpos($resultLower, 'hands on keyboard') !== false ||
                strpos($resultLower, 'hands are on keyboard') !== false ||
                strpos($resultLower, 'both hands on keyboard') !== false
            );
            
            // Only override if explicitly stated phone is NOT in hands
            if ($clearlyNotHoldingPhone) {
                error_log("[" . date('H:i:s') . "] OVERRIDE: Changed from " . $number . " to 2 (Working) - phone not in hands");
                return [
                    'activity' => $activities[2],
                    'category' => 2
                ];
            } else {
                error_log("[" . date('H:i:s') . "] KEEPING: Category " . $number . " - no clear indicators phone NOT in hands");
            }
        }
        
        if ($number >= 1 && $number <= 7) {
            return [
                'activity' => $activities[$number],
                'category' => $number
            ];
        }
    }
    
    // Work detection
    $hasWork = (
        strpos($resultLower, 'computer') !== false ||
        strpos($resultLower, 'laptop') !== false ||
        strpos($resultLower, 'desk') !== false ||
        strpos($resultLower, 'keyboard') !== false ||
        strpos($resultLower, 'monitor') !== false ||
        strpos($resultLower, 'screen') !== false
    );
    
    // Phone detection - look for positive indicators
    $phoneInHands = (
        strpos($resultLower, 'holding phone') !== false ||
        strpos($resultLower, 'phone in hand') !== false ||
        strpos($resultLower, 'gripping phone') !== false ||
        strpos($resultLower, 'using phone') !== false ||
        strpos($resultLower, 'phone in their hand') !== false ||
        strpos($resultLower, 'hand holding phone') !== false ||
        strpos($resultLower, 'texting') !== false ||
        strpos($resultLower, 'phone to ear') !== false ||
        strpos($resultLower, 'looking at phone') !== false
    );
    
    // ONLY exclude phone if explicitly stated it's NOT in hands
    $explicitlyNotHolding = (
        strpos($resultLower, 'not holding') !== false ||
        strpos($resultLower, 'phone on desk') !== false ||
        strpos($resultLower, 'phone on table') !== false ||
        strpos($resultLower, 'phone sits') !== false ||
        strpos($resultLower, 'phone placed') !== false ||
        strpos($resultLower, 'both hands on keyboard') !== false
    );
    
    if ($explicitlyNotHolding) {
        $phoneInHands = false;
    }
    
    // Check combinations
    if ($phoneInHands && $hasWork) {
        return ['activity' => $activities[3], 'category' => 3];
    }
    
    if ($phoneInHands) {
        return ['activity' => $activities[1], 'category' => 1];
    } elseif ($hasWork) {
        return ['activity' => $activities[2], 'category' => 2];
    } elseif (strpos($resultLower, 'sleep') !== false || strpos($resultLower, 'resting') !== false || strpos($resultLower, 'eyes closed') !== false) {
        return ['activity' => $activities[4], 'category' => 4];
    } elseif (strpos($resultLower, 'eat') !== false || strpos($resultLower, 'food') !== false) {
        return ['activity' => $activities[5], 'category' => 5];
    } elseif (strpos($resultLower, 'drink') !== false || strpos($resultLower, 'cup') !== false || strpos($resultLower, 'coffee') !== false) {
        return ['activity' => $activities[6], 'category' => 6];
    } else {
        return ['activity' => $activities[7], 'category' => 7];
    }
}

/**
 * Analyze image with Ollama LLaVA
 */
function analyzeImageWithOllama($imageData) {
    global $ACTIVITIES;
    
    try {
        // Performance optimization: Skip analysis if image hash matches recent analysis
        $imageHash = md5($imageData);
        $cacheKey = 'activity_' . $imageHash;
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.json';
        $cacheLifetime = 3; // seconds
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                error_log("[" . date('H:i:s') . "] Using cached result");
                return $cached;
            }
        }
        
        // Resize image
        $resizedImage = resizeImageForAnalysis($imageData);
        
        // Simplified prompt optimized for qwen3-vl:2b (smaller model needs simpler instructions)
        $prompt = "Look at this image carefully. What is the person doing RIGHT NOW?

Choose ONE number (1-7):

1 = Using phone (person is HOLDING phone in their hand)
2 = Working ( at desk with NO phone in hand)
3 = Phone + Work (doing BOTH: person is ACTIVELY HOLDING and USING phone in their hand AND working at computer/desk)
4 = Sleeping
5 = Eating
6 = Drinking  
7 = Other


Answer: First write the number (1-7), then explain what you see.";
        
        // Prepare Ollama request
        $payload = [
            'model' => OLLAMA_MODEL,
            'prompt' => $prompt,
            'images' => [$resizedImage],
            'stream' => false,
            'options' => [
                'temperature' => 0.3,    // Slightly higher for qwen3-vl:2b
                'num_predict' => 100,    // More tokens for complete response
                'top_p' => 0.95,
                'top_k' => 50,
                'repeat_penalty' => 1.1,
                'stop' => ["\n\n", "---"]  // Stop tokens to prevent rambling
            ]
        ];
        
        error_log("[" . date('H:i:s') . "] Sending request to Ollama...");
        
        $ch = curl_init(OLLAMA_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Connection: keep-alive'  // Keep connection alive for faster subsequent requests
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, OLLAMA_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, OLLAMA_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, OLLAMA_SSL_VERIFY_PEER);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, OLLAMA_SSL_VERIFY_HOST);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);  // Disable Nagle's algorithm for faster responses
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'Cannot connect to Ollama server',
                'details' => $curlError,
                'activity' => 'Connection Error',
                'category' => 7
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => "Ollama API returned status $httpCode",
                'activity' => 'API Error',
                'category' => 7
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['response'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from Ollama',
                'activity' => 'No Response',
                'category' => 7
            ];
        }
        
        $fullResponse = $result['response'];
        error_log("[" . date('H:i:s') . "] RAW AI RESPONSE: " . $fullResponse);
        
        // Handle empty or whitespace-only responses
        if (empty(trim($fullResponse))) {
            error_log("[" . date('H:i:s') . "] Empty response detected. Defaulting to 'Working'");
            // Default to "Working" if we get empty response (most common scenario)
            return [
                'success' => true,
                'activity' => $ACTIVITIES[2],
                'category' => 2,
                'description' => 'Default: Working (empty AI response)',
                'timestamp' => date('c')
            ];
        }
        
        // Parse the response
        $parsed = parseActivityResponse($fullResponse, $ACTIVITIES);
        
        $finalResult = [
            'success' => true,
            'activity' => $parsed['activity'],
            'category' => $parsed['category'],
            'description' => $fullResponse, // Send full AI response to browser console
            'ai_raw_response' => $fullResponse, // Also include as separate field
            'timestamp' => date('c')
        ];
        
        // Cache the result
        file_put_contents($cacheFile, json_encode($finalResult));
        
        return $finalResult;
        
    } catch (Exception $e) {
        error_log("Ollama error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'activity' => 'Error',
            'category' => 7
        ];
    }
}

// Main execution
try {
    // Get the image data from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No image data provided']);
        exit();
    }
    
    // Analyze with Ollama
    $aiResult = analyzeImageWithOllama($input['image']);
    
    // Always include the raw AI response in the output for debugging
    if (isset($aiResult['ai_raw_response'])) {
        $aiResult['description'] = $aiResult['ai_raw_response'];
    }
    
    // If AI analysis was successful, log the activity
    if ($aiResult['success']) {
        try {
            $conn = getDBConnection();
            
            // Insert activity log
            $stmt = $conn->prepare("
                INSERT INTO activity_logs 
                (user_id, activity_type, category, description, detected_at) 
                VALUES (:user_id, :activity_type, :category, :description, NOW())
            ");
            
            $stmt->execute([
                ':user_id' => $userId,
                ':activity_type' => $aiResult['activity'],
                ':category' => $aiResult['category'],
                ':description' => $aiResult['description'] ?? ''
            ]);
            
            $logId = $conn->lastInsertId();
            
            // Return success with activity data
            echo json_encode([
                'success' => true,
                'activity' => $aiResult['activity'],
                'category' => $aiResult['category'],
                'log_id' => $logId,
                'timestamp' => $aiResult['timestamp'],
                'ai_raw_response' => $aiResult['ai_raw_response'] ?? null,
                'description' => $aiResult['description'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Database error in analyze-activity.php: " . $e->getMessage());
            
            // Still return the AI result even if logging fails
            echo json_encode([
                'success' => true,
                'activity' => $aiResult['activity'],
                'category' => $aiResult['category'],
                'warning' => 'Activity detected but not logged to database',
                'timestamp' => $aiResult['timestamp'],
                'ai_raw_response' => $aiResult['ai_raw_response'] ?? null,
                'description' => $aiResult['description'] ?? null
            ]);
        }
    } else {
        // Return AI analysis error
        http_response_code(500);
        echo json_encode($aiResult);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
