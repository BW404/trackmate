<?php
/**
 * TrackMate - Get Activity Statistics API
 * Returns activity statistics for dashboard, reports, and calendar
 */

require_once '../config/config.php';

header('Content-Type: application/json');

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

    $period = $_GET['period'] ?? 'today'; // today, week, month, year
    $date = $_GET['date'] ?? date('Y-m-d');

    // Determine date range based on period
    switch ($period) {
        case 'today':
            $startDate = $date . ' 00:00:00';
            $endDate = $date . ' 23:59:59';
            break;
        case 'week':
            $startDate = date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($date)));
            $endDate = date('Y-m-d 23:59:59', strtotime('sunday this week', strtotime($date)));
            break;
        case 'month':
            $startDate = date('Y-m-01 00:00:00', strtotime($date));
            $endDate = date('Y-m-t 23:59:59', strtotime($date));
            break;
        case 'year':
            $startDate = date('Y-01-01 00:00:00', strtotime($date));
            $endDate = date('Y-12-31 23:59:59', strtotime($date));
            break;
        default:
            $startDate = $date . ' 00:00:00';
            $endDate = $date . ' 23:59:59';
    }

    // Get activity summary by category
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count,
            MIN(detected_at) as first_detection,
            MAX(detected_at) as last_detection
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        GROUP BY category
        ORDER BY category
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $activitySummary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate time spent (approximate based on 3-second intervals)
    $categoryNames = [
        1 => 'Phone Usage',
        2 => 'Working',
        3 => 'Phone + Work',
        4 => 'Sleeping',
        5 => 'Eating',
        6 => 'Drinking',
        7 => 'Other'
    ];

    $stats = [];
    $totalDetections = 0;
    
    foreach ($activitySummary as $activity) {
        $category = $activity['category'];
        $count = $activity['count'];
        $totalDetections += $count;
        
        // Each detection is approximately 3 seconds
        $timeInSeconds = $count * 3;
        $hours = floor($timeInSeconds / 3600);
        $minutes = floor(($timeInSeconds % 3600) / 60);
        
        $stats[] = [
            'category' => $category,
            'name' => $categoryNames[$category] ?? 'Unknown',
            'count' => $count,
            'time_seconds' => $timeInSeconds,
            'time_formatted' => sprintf('%dh %dm', $hours, $minutes),
            'hours' => round($timeInSeconds / 3600, 1),
            'first_detection' => $activity['first_detection'],
            'last_detection' => $activity['last_detection']
        ];
    }

    // Get recent activities (last 20)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            activity_type,
            category,
            description,
            detected_at
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        ORDER BY detected_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get hourly breakdown for charts
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(detected_at) as hour,
            category,
            COUNT(*) as count
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        GROUP BY HOUR(detected_at), category
        ORDER BY hour, category
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $hourlyBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get daily breakdown for week/month view
    $stmt = $pdo->prepare("
        SELECT 
            DATE(detected_at) as date,
            category,
            COUNT(*) as count
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        GROUP BY DATE(detected_at), category
        ORDER BY date, category
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $dailyBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate productivity score (working vs phone usage)
    $workingCount = 0;
    $phoneCount = 0;
    foreach ($activitySummary as $activity) {
        if ($activity['category'] == 2) { // Working
            $workingCount = $activity['count'];
        } elseif ($activity['category'] == 1) { // Phone
            $phoneCount = $activity['count'];
        }
    }
    
    $productivityScore = 0;
    if ($totalDetections > 0) {
        $productivityScore = round(($workingCount / $totalDetections) * 100);
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_detections' => $totalDetections,
            'productivity_score' => $productivityScore,
            'summary' => $stats,
            'recent_activities' => $recentActivities,
            'hourly_breakdown' => $hourlyBreakdown,
            'daily_breakdown' => $dailyBreakdown,
            'category_names' => $categoryNames
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
