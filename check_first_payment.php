<?php
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

header('Content-Type: application/json');
//hh
try {
    // Get the input data
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentId = $input['payment_id'] ?? null;

    if (!$paymentId) {
        echo json_encode(['message' => 'Payment ID is required.']);
        exit;
    }

    // Get payment details
    $stmt = $conn->prepare("
        SELECT CustomerID, SchemeID 
        FROM Payments 
        WHERE PaymentID = ?
    ");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo json_encode(['message' => 'Payment not found.']);
        exit;
    }

    // Check if this is the first verified payment for this customer for this scheme
    $stmt = $conn->prepare("
        SELECT COUNT(*) as payment_count 
        FROM Payments 
        WHERE CustomerID = ? AND SchemeID = ? AND Status = 'Verified'
    ");
    $stmt->execute([$payment['CustomerID'], $payment['SchemeID']]);
    $paymentCount = $stmt->fetch(PDO::FETCH_ASSOC)['payment_count'];

    if ($paymentCount == 0) {
        echo json_encode(['message' => 'This is the first verified payment for the scheme.']);
    } else {
        echo json_encode(['message' => 'This is not the first verified payment for the scheme.']);
    }
} catch (Exception $e) {
    echo json_encode(['message' => 'Error checking payment: ' . $e->getMessage()]);
}
