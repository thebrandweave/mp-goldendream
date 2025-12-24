<?php
session_start();

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get parameters
$teamName = isset($_GET['team']) ? $_GET['team'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (empty($teamName)) {
    die("Team name is required");
}

// Function to get team members (customers only)
function getTeamMembers($conn, $teamName, $date)
{
    $query = "SELECT 
        c.CustomerUniqueID as unique_id,
        c.Name,
        c.Contact,
        c.Email,
        c.PromoterID as ParentPromoterID,
        p.Name as ParentName,
        COUNT(DISTINCT CASE WHEN DATE(pay.SubmittedAt) = :today THEN pay.PaymentID END) as total_payments,
        SUM(CASE WHEN DATE(pay.SubmittedAt) = :today AND pay.Status = 'Verified' THEN pay.Amount ELSE 0 END) as total_amount
        FROM Customers c
        LEFT JOIN Promoters p ON c.PromoterID = p.PromoterUniqueID
        LEFT JOIN Payments pay ON pay.CustomerID = c.CustomerID
        WHERE c.TeamName = :teamName
        AND (DATE(c.CreatedAt) = :today OR DATE(pay.SubmittedAt) = :today)
        GROUP BY c.CustomerID, c.CustomerUniqueID, c.Name, c.Contact, c.Email, c.PromoterID, p.Name";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':teamName', $teamName);
    $stmt->bindParam(':today', $date);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get team members data
$teamMembers = getTeamMembers($conn, $teamName, $date);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $teamName . '_sales_' . $date . '.xls"');
header('Cache-Control: max-age=0');

// Output Excel content
echo "Team Sales Report - " . $teamName . "\n";
echo "Date: " . date('F d, Y', strtotime($date)) . "\n\n";

// Headers
echo "ID\tName\tContact\tEmail\tParent\tToday's Payments\tToday's Amount\n";

// Data rows
foreach ($teamMembers as $member) {
    echo implode("\t", [
        $member['unique_id'],
        $member['Name'],
        $member['Contact'],
        $member['Email'],
        $member['ParentName'] ?? 'None',
        $member['total_payments'],
        '₹' . number_format($member['total_amount'])
    ]) . "\n";
}
