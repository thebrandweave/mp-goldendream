<?php
session_start();
require_once("../../config/config.php");

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate input
if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid customer ID']);
    exit;
}

$customerId = (int)$_GET['customer_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get schemes for the customer
    $stmt = $conn->prepare("
        SELECT DISTINCT s.SchemeID, s.SchemeName
        FROM Schemes s
        LEFT JOIN Subscriptions sub ON s.SchemeID = sub.SchemeID
        WHERE s.Status = 'Active'
        AND (sub.CustomerID = ? OR sub.CustomerID IS NULL)
        ORDER BY s.SchemeName ASC
    ");
    $stmt->execute([$customerId]);
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($schemes);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
