<?php
session_start();
require_once("../../config/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$customerId = $_GET['customer_id'] ?? null;
$schemeId = $_GET['scheme_id'] ?? null;
$installmentId = $_GET['installment_id'] ?? null;

if (!$customerId || !$schemeId || !$installmentId) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT p.*, s.SchemeName, i.InstallmentNumber 
        FROM Payments p
        JOIN Schemes s ON p.SchemeID = s.SchemeID
        JOIN Installments i ON p.InstallmentID = i.InstallmentID
        WHERE p.CustomerID = ? AND p.SchemeID = ? AND p.InstallmentID = ?
    ");
    $stmt->execute([$customerId, $schemeId, $installmentId]);
    $existingPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPayment) {
        echo json_encode([
            'exists' => true,
            'message' => "A payment already exists for this scheme's installment. Status: " . $existingPayment['Status'] .
                " (Scheme: " . $existingPayment['SchemeName'] .
                ", Installment: " . $existingPayment['InstallmentNumber'] . ")"
        ]);
    } else {
        echo json_encode([
            'exists' => false
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
