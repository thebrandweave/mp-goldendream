<?php
require_once("../../config/config.php");
header('Content-Type: application/json');

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['promoter_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Promoter ID is required']);
    exit;
}

$promoterId = $_GET['promoter_id'];

try {
    // Get wallet logs for the promoter
    $stmt = $conn->prepare("
        SELECT Amount, Message, CreatedAt, TransactionType 
        FROM WalletLogs 
        WHERE PromoterUniqueID = ? 
        ORDER BY CreatedAt DESC 
        LIMIT 50
    ");

    $stmt->execute([$promoterId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($logs);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch wallet logs']);
    error_log($e->getMessage());
}
