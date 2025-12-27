<?php
/**
 * Hybrid Activity Detection API
 * Uses MediaPipe + YOLO for fast detection, Ollama as fallback
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/ollama-config.php';
require_once __DIR__ . '/../config/hybrid-config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get image data from request
$input = json_decode(file_get_contents('php://input'), true);

// Handle health check request
if (isset($input['check_health']) && $input['check_health']) {
    $isOnline = isHybridServerAvailable();
    echo json_encode([
        'success' => true,
        'hybrid_available' => $isOnline,
        'hybrid_url' => HYBRID_SERVER_URL,
        'status' => $isOnline ? 'online' : 'offline'
    ]);
    exit;
}

$imageData = $input['image'] ?? '';

if (empty($imageData)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No image data provided'
    ]);
    exit;
}

/**
 * Analyze image using hybrid detection server
 */
function analyzeWithHybrid($imageData) {
    $ch = curl_init(HYBRID_SERVER_URL);
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['image' => $imageData]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10, // Much faster than Ollama!
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * Fallback to original Ollama detection
 */
function analyzeWithOllama($imageData) {
    // Use the existing Ollama detection from analyze-activity.php
    require_once __DIR__ . '/analyze-activity.php';
    
    // This is a simplified version - in production, extract the Ollama function
    return [
        'success' => false,
        'error' => 'Hybrid server unavailable, Ollama fallback not implemented yet'
    ];
}

/**
 * Save activity to database
 */
function saveActivity($userId, $category, $activityName, $confidence, $method, $reasoning) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, activity_type, activity_name, notes, confidence, detection_method)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $notes = "Confidence: " . round($confidence * 100, 1) . "% | " . $reasoning;
    
    $stmt->bind_param(
        "iissds",
        $userId,
        $category,
        $activityName,
        $notes,
        $confidence,
        $method
    );
    
    $result = $stmt->execute();
    $activityId = $stmt->insert_id;
    $stmt->close();
    
    return $result ? $activityId : false;
}

// Main detection logic
try {
    $result = null;
    
    // Try hybrid detection first (if enabled)
    if (USE_HYBRID_DETECTION) {
        $result = analyzeWithHybrid($imageData);
    }
    
    // If hybrid failed or disabled, use Ollama
    if (!$result || !$result['success']) {
        error_log("Hybrid detection unavailable, using Ollama fallback");
        $result = analyzeWithOllama($imageData);
    }
    
    if ($result && $result['success']) {
        $category = $result['category'];
        $activityName = $result['activity'];
        $confidence = $result['confidence'] ?? 0.5;
        $method = $result['method'] ?? 'Unknown';
        $reasoning = $result['reasoning'] ?? '';
        
        // Save to database
        $activityId = saveActivity(
            $_SESSION['user_id'],
            $category,
            $activityName,
            $confidence,
            $method,
            $reasoning
        );
        
        if ($activityId) {
            echo json_encode([
                'success' => true,
                'activity_id' => $activityId,
                'category' => $category,
                'activity_name' => $activityName,
                'confidence' => $confidence,
                'method' => $method,
                'processing_time' => $result['processing_time'] ?? 0,
                'details' => $result['details'] ?? []
            ]);
        } else {
            throw new Exception('Failed to save activity to database');
        }
    } else {
        throw new Exception('All detection methods failed');
    }
    
} catch (Exception $e) {
    error_log("Hybrid detection error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'category' => 7,
        'activity_name' => 'Other'
    ]);
}
?>
