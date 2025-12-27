<?php
/**
 * Test Calendar API
 */

session_start();
$_SESSION['user_id'] = 1; // Set a test user ID

// Test get-calendar-activities.php
echo "=== Testing get-calendar-activities.php ===\n";
$month = '2025-12';
$_GET['month'] = $month;
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
include 'api/get-calendar-activities.php';
$output = ob_get_clean();

echo "Response for month $month:\n";
echo $output;
echo "\n\n";

// Test get-activity-stats.php for today
echo "=== Testing get-activity-stats.php for today ===\n";
$_GET['period'] = 'today';
$_GET['date'] = '2025-12-24';

ob_start();
include 'api/get-activity-stats.php';
$output = ob_get_clean();

echo "Response for 2025-12-24:\n";
echo $output;
echo "\n";
