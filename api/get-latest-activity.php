<?php
/**
 * TrackMate - Get Latest Activity API
 * Returns the most recent detected activity
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit();
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Get the latest activity
    $stmt = $pdo->prepare("
        SELECT 
            id,
            activity_type,
            category,
            description,
            detected_at,
            TIMESTAMPDIFF(SECOND, detected_at, NOW()) as seconds_ago
        FROM activity_logs
        WHERE user_id = ?
        ORDER BY detected_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $latestActivity = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latestActivity) {
        // Format time ago
        $secondsAgo = $latestActivity['seconds_ago'];
        if ($secondsAgo < 60) {
            $timeAgo = 'Just now';
        } elseif ($secondsAgo < 3600) {
            $minutes = floor($secondsAgo / 60);
            $timeAgo = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } else {
            $hours = floor($secondsAgo / 3600);
            $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => $latestActivity['id'],
                'activity_type' => $latestActivity['activity_type'],
                'category' => $latestActivity['category'],
                'description' => $latestActivity['description'],
                'detected_at' => $latestActivity['detected_at'],
                'time_ago' => $timeAgo
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => null,
            'message' => 'No activities detected yet'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
