<?php
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');

if (!isset($_GET['scheme_id'])) {
    echo json_encode([]);
    exit;
}

$schemeId = $_GET['scheme_id'];

try {
    $stmt = $conn->prepare("
        SELECT DISTINCT i.InstallmentNumber 
        FROM Payments p 
        JOIN Installments i ON p.InstallmentID = i.InstallmentID 
        WHERE p.SchemeID = ? 
        ORDER BY i.InstallmentNumber
    ");
    $stmt->execute([$schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($installments);
} catch (PDOException $e) {
    error_log("Error fetching installments: " . $e->getMessage());
    echo json_encode([]);
}
