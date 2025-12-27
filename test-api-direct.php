<?php
session_start();
// Simulate logged-in user
$_SESSION['user_id'] = 1;

echo "<h1>TrackMate API Test</h1>";
echo "<p>Testing with user_id: " . $_SESSION['user_id'] . "</p>";

// Test 1: Calendar Activities API
echo "<h2>1. Calendar Activities API (December 2025)</h2>";
$_GET['month'] = '2025-12';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include 'api/get-calendar-activities.php';
$calendarOutput = ob_get_clean();

echo "<pre>Response:\n";
$calendarData = json_decode($calendarOutput, true);
echo json_encode($calendarData, JSON_PRETTY_PRINT);
echo "</pre>";

// Test 2: Activity Stats API
echo "<h2>2. Activity Stats API (2025-12-24)</h2>";
$_GET['period'] = 'today';
$_GET['date'] = '2025-12-24';

ob_start();
include 'api/get-activity-stats.php';
$statsOutput = ob_get_clean();

echo "<pre>Response:\n";
$statsData = json_decode($statsOutput, true);
echo json_encode($statsData, JSON_PRETTY_PRINT);
echo "</pre>";

// Test 3: Direct Database Query
echo "<h2>3. Direct Database Query</h2>";
require 'config/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE(detected_at) as date,
            category,
            activity_type,
            COUNT(*) as count
        FROM activity_logs
        WHERE user_id = 1 
            AND detected_at >= '2025-12-01' 
            AND detected_at <= '2025-12-31'
        GROUP BY DATE(detected_at), category, activity_type
        ORDER BY date DESC, category
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    echo "Total records: " . count($results) . "\n\n";
    print_r($results);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}
