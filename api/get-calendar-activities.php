<?php
/**
 * TrackMate - Get Calendar Activities API
 * Returns activities for calendar view
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

    $month = $_GET['month'] ?? date('Y-m');
    $startDate = $month . '-01 00:00:00';
    $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

    // Get daily activity summary for the month
    $stmt = $pdo->prepare("
        SELECT 
            DATE(detected_at) as date,
            category,
            COUNT(*) as count,
            GROUP_CONCAT(DISTINCT activity_type SEPARATOR ', ') as activities
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        GROUP BY DATE(detected_at), category
        ORDER BY date, category
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $dailyActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by date
    $calendar = [];
    foreach ($dailyActivities as $activity) {
        $date = $activity['date'];
        if (!isset($calendar[$date])) {
            $calendar[$date] = [
                'date' => $date,
                'categories' => [],
                'total_count' => 0
            ];
        }
        
        $calendar[$date]['categories'][] = [
            'category' => $activity['category'],
            'count' => $activity['count'],
            'activities' => $activity['activities']
        ];
        $calendar[$date]['total_count'] += $activity['count'];
    }

    // Get monthly statistics
    $stmt = $pdo->prepare("
        SELECT 
            category,
            COUNT(*) as count,
            COUNT(DISTINCT DATE(detected_at)) as active_days
        FROM activity_logs
        WHERE user_id = ? AND detected_at BETWEEN ? AND ?
        GROUP BY category
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'month' => $month,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'calendar' => array_values($calendar),
            'monthly_stats' => $monthlyStats
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
