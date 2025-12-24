<?php
session_start();

require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['scheme_id']) || empty($_GET['scheme_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit();
}

$schemeId = $_GET['scheme_id'];

try {
    $stmt = $conn->prepare("
        SELECT InstallmentID, InstallmentName, DrawDate 
        FROM Installments 
        WHERE SchemeID = ? AND Status = 'Active'
        ORDER BY InstallmentNumber
    ");
    $stmt->execute([$schemeId]);
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($installments);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error occurred']);
}
