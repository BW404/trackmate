<?php
/**
 * Get Activity Reports API
 * Returns daily, weekly, monthly, and yearly activity reports
 */

require_once __DIR__ . '/../config/config.php';

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

$userId = $_SESSION['user_id'];
$period = $_GET['period'] ?? 'daily';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

/**
 * Get activity data based on period
 */
function getActivityReport($conn, $userId, $period, $startDate = null, $endDate = null) {
    $dateCondition = buildDateCondition($period, $startDate, $endDate);
    
    // Get activity summary by category (simplified without window functions)
    $stmt = $conn->prepare("
        SELECT 
            activity_type,
            activity_name,
            COUNT(*) as count
        FROM activity_logs
        WHERE user_id = ? AND $dateCondition
        GROUP BY activity_type, activity_name
        ORDER BY count DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $summary = [];
    $totalActivities = 0;
    
    while ($row = $result->fetch_assoc()) {
        $count = (int)$row['count'];
        $totalActivities += $count;
        
        // Estimate hours based on activity count (assume 2-second intervals)
        $estimatedSeconds = $count * 2;
        
        $summary[] = [
            'category' => (int)$row['activity_type'],
            'name' => $row['activity_name'],
            'count' => $count,
            'seconds' => $estimatedSeconds,
            'hours' => round($estimatedSeconds / 3600, 2),
            'percentage' => 0 // Will calculate after we know total
        ];
    }
    
    // Calculate total seconds and percentages
    $totalSeconds = array_sum(array_column($summary, 'seconds'));
    
    foreach ($summary as &$item) {
        $item['percentage'] = $totalSeconds > 0 ? round(($item['seconds'] / $totalSeconds) * 100, 1) : 0;
    }
    
    $stmt->close();
    
    // Get time series data for charts
    $timeSeriesData = getTimeSeriesData($conn, $userId, $period, $dateCondition);
    
    // Calculate total seconds for productivity
    $totalSeconds = array_sum(array_column($summary, 'seconds'));
    
    // Get productivity metrics
    $productivity = calculateProductivity($summary, $totalSeconds);
    
    return [
        'period' => $period,
        'start_date' => getStartDate($period, $startDate),
        'end_date' => $endDate ?? date('Y-m-d'),
        'summary' => $summary,
        'total_hours' => round($totalSeconds / 3600, 2),
        'total_activities' => $totalActivities,
        'time_series' => $timeSeriesData,
        'productivity' => $productivity
    ];
}

/**
 * Build SQL date condition based on period
 */
function buildDateCondition($period, $startDate, $endDate) {
    if ($startDate && $endDate) {
        return "created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";
    }
    
    switch ($period) {
        case 'daily':
        case 'today':
            return "DATE(created_at) = CURDATE()";
            
        case 'weekly':
        case 'week':
            return "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            
        case 'monthly':
        case 'month':
            return "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            
        case 'yearly':
        case 'year':
            return "YEAR(created_at) = YEAR(CURDATE())";
            
        case 'last7days':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            
        case 'last30days':
            return "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            
        default:
            return "DATE(created_at) = CURDATE()";
    }
}

/**
 * Get start date based on period
 */
function getStartDate($period, $customStart = null) {
    if ($customStart) return $customStart;
    
    switch ($period) {
        case 'daily':
        case 'today':
            return date('Y-m-d');
            
        case 'weekly':
        case 'week':
            return date('Y-m-d', strtotime('monday this week'));
            
        case 'monthly':
        case 'month':
            return date('Y-m-01');
            
        case 'yearly':
        case 'year':
            return date('Y-01-01');
            
        case 'last7days':
            return date('Y-m-d', strtotime('-7 days'));
            
        case 'last30days':
            return date('Y-m-d', strtotime('-30 days'));
            
        default:
            return date('Y-m-d');
    }
}

/**
 * Get time series data for charts
 */
function getTimeSeriesData($conn, $userId, $period, $dateCondition) {
    $groupBy = getTimeSeriesGroupBy($period);
    
    $stmt = $conn->prepare("
        SELECT 
            $groupBy as time_label,
            activity_type,
            activity_name,
            COUNT(*) as count
        FROM activity_logs
        WHERE user_id = ? AND $dateCondition
        GROUP BY time_label, activity_type, activity_name
        ORDER BY time_label, activity_type
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timeSeries = [];
    while ($row = $result->fetch_assoc()) {
        $label = $row['time_label'];
        if (!isset($timeSeries[$label])) {
            $timeSeries[$label] = [];
        }
        
        // Estimate hours based on count (2 seconds per detection)
        $estimatedSeconds = (int)$row['count'] * 2;
        
        $timeSeries[$label][] = [
            'category' => (int)$row['activity_type'],
            'name' => $row['activity_name'],
            'count' => (int)$row['count'],
            'hours' => round($estimatedSeconds / 3600, 2)
        ];
    }
    
    $stmt->close();
    return $timeSeries;
}

/**
 * Get GROUP BY clause for time series based on period
 */
function getTimeSeriesGroupBy($period) {
    switch ($period) {
        case 'daily':
        case 'today':
            return "DATE_FORMAT(created_at, '%H:00')"; // Hourly
            
        case 'weekly':
        case 'week':
            return "DATE_FORMAT(created_at, '%W')"; // Day of week
            
        case 'monthly':
        case 'month':
            return "DATE_FORMAT(created_at, '%Y-%m-%d')"; // Daily
            
        case 'yearly':
        case 'year':
            return "DATE_FORMAT(created_at, '%Y-%m')"; // Monthly
            
        case 'last7days':
        case 'last30days':
            return "DATE_FORMAT(created_at, '%Y-%m-%d')"; // Daily
            
        default:
            return "DATE_FORMAT(created_at, '%H:00')";
    }
}

/**
 * Calculate productivity metrics
 */
function calculateProductivity($summary, $totalSeconds) {
    // Define productive activities (Working, Studying, etc.)
    $productiveCategories = [2]; // Working
    $unproductiveCategories = [1, 3]; // Phone, Phone + Work
    
    $productiveSeconds = 0;
    $unproductiveSeconds = 0;
    
    foreach ($summary as $item) {
        if (in_array($item['category'], $productiveCategories)) {
            $productiveSeconds += $item['seconds'];
        } elseif (in_array($item['category'], $unproductiveCategories)) {
            $unproductiveSeconds += $item['seconds'];
        }
    }
    
    $productivityScore = $totalSeconds > 0 
        ? round(($productiveSeconds / $totalSeconds) * 100, 1) 
        : 0;
    
    return [
        'score' => $productivityScore,
        'productive_hours' => round($productiveSeconds / 3600, 2),
        'unproductive_hours' => round($unproductiveSeconds / 3600, 2),
        'neutral_hours' => round(($totalSeconds - $productiveSeconds - $unproductiveSeconds) / 3600, 2)
    ];
}

/**
 * Get comparison data (vs previous period)
 */
function getComparisonData($conn, $userId, $period) {
    $currentReport = getActivityReport($conn, $userId, $period);
    
    // Get previous period dates
    $prevDates = getPreviousPeriodDates($period);
    $previousReport = getActivityReport($conn, $userId, $period, $prevDates['start'], $prevDates['end']);
    
    // Calculate changes
    $hoursChange = $currentReport['total_hours'] - $previousReport['total_hours'];
    $productivityChange = $currentReport['productivity']['score'] - $previousReport['productivity']['score'];
    
    return [
        'current' => $currentReport,
        'previous' => $previousReport,
        'changes' => [
            'hours' => round($hoursChange, 2),
            'hours_percent' => $previousReport['total_hours'] > 0 
                ? round(($hoursChange / $previousReport['total_hours']) * 100, 1) 
                : 0,
            'productivity' => round($productivityChange, 1)
        ]
    ];
}

/**
 * Get previous period dates
 */
function getPreviousPeriodDates($period) {
    switch ($period) {
        case 'daily':
        case 'today':
            return [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d', strtotime('-1 day'))
            ];
            
        case 'weekly':
        case 'week':
            return [
                'start' => date('Y-m-d', strtotime('monday last week')),
                'end' => date('Y-m-d', strtotime('sunday last week'))
            ];
            
        case 'monthly':
        case 'month':
            return [
                'start' => date('Y-m-01', strtotime('first day of last month')),
                'end' => date('Y-m-t', strtotime('last day of last month'))
            ];
            
        case 'yearly':
        case 'year':
            return [
                'start' => date('Y-01-01', strtotime('-1 year')),
                'end' => date('Y-12-31', strtotime('-1 year'))
            ];
            
        default:
            return [
                'start' => date('Y-m-d', strtotime('-1 day')),
                'end' => date('Y-m-d', strtotime('-1 day'))
            ];
    }
}

// Main execution
try {
    $includeComparison = isset($_GET['compare']) && $_GET['compare'] === 'true';
    
    if ($includeComparison) {
        $data = getComparisonData($conn, $userId, $period);
    } else {
        $data = getActivityReport($conn, $userId, $period, $startDate, $endDate);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate report',
        'message' => $e->getMessage()
    ]);
}
?>
