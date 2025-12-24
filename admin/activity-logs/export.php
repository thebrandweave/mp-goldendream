<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Search and filtering (taking from URL parameters)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$userTypeFilter = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : '';
$ipAddress = isset($_GET['ip_address']) ? $_GET['ip_address'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "Action LIKE ?";
    $params[] = "%$search%";
}

if (!empty($userTypeFilter)) {
    $conditions[] = "UserType = ?";
    $params[] = $userTypeFilter;
}

if (!empty($ipAddress)) {
    $conditions[] = "IPAddress LIKE ?";
    $params[] = "%$ipAddress%";
}

if (!empty($dateRange)) {
    $dates = explode(' - ', $dateRange);
    if (count($dates) == 2) {
        $startDate = date('Y-m-d', strtotime($dates[0]));
        $endDate = date('Y-m-d', strtotime($dates[1] . ' +1 day'));
        $conditions[] = "CreatedAt BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    }
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get all activity logs based on filters
$query = "
    SELECT al.LogID, al.UserType, al.UserID, al.Action, al.IPAddress, al.CreatedAt,
           CASE 
               WHEN al.UserType = 'Admin' THEN a.Name 
               WHEN al.UserType = 'Promoter' THEN p.Name 
           END as UserName,
           CASE 
               WHEN al.UserType = 'Admin' THEN a.Email 
               WHEN al.UserType = 'Promoter' THEN p.Email
           END as UserEmail
    FROM ActivityLogs al
    LEFT JOIN Admins a ON al.UserType = 'Admin' AND al.UserID = a.AdminID
    LEFT JOIN Promoters p ON al.UserType = 'Promoter' AND al.UserID = p.PromoterID"
    . $whereClause .
    " ORDER BY al.CreatedAt DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$activityLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Log ID',
    'User Type',
    'User ID',
    'User Name',
    'User Email',
    'Action',
    'IP Address',
    'Timestamp'
]);

// Add data rows
foreach ($activityLogs as $log) {
    fputcsv($output, [
        $log['LogID'],
        $log['UserType'],
        $log['UserID'],
        $log['UserName'] ?? 'Unknown',
        $log['UserEmail'] ?? 'N/A',
        $log['Action'],
        $log['IPAddress'],
        $log['CreatedAt']
    ]);
}

// Close output stream
fclose($output);
exit;
