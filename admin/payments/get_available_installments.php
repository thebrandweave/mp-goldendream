<?php
session_start();
require_once("../../config/config.php");

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate input
if (!isset($_GET['scheme_id']) || !is_numeric($_GET['scheme_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid scheme ID']);
    exit;
}

$scheme_id = intval($_GET['scheme_id']);

try {
    $database = new Database();
    $conn = $database->getConnection();

    // First, check if the scheme exists and is active
    $checkScheme = $conn->prepare("
        SELECT SchemeID, SchemeName, Status 
        FROM Schemes 
        WHERE SchemeID = :scheme_id
    ");
    $checkScheme->bindParam(':scheme_id', $scheme_id, PDO::PARAM_INT);
    $checkScheme->execute();
    $scheme = $checkScheme->fetch(PDO::FETCH_ASSOC);

    if (!$scheme) {
        echo json_encode(['error' => 'Scheme not found']);
        exit;
    }

    if ($scheme['Status'] !== 'Active') {
        echo json_encode(['error' => 'Scheme is not active']);
        exit;
    }

    // Get available installments for the selected scheme
    $stmt = $conn->prepare("
        SELECT i.InstallmentID, i.InstallmentNumber, i.Amount, i.DrawDate
        FROM Installments i
        WHERE i.SchemeID = :scheme_id 
        AND i.Status = 'Active'
        ORDER BY i.InstallmentNumber ASC
    ");

    $stmt->bindParam(':scheme_id', $scheme_id, PDO::PARAM_INT);
    $stmt->execute();
    $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add debug information
    $response = [
        'debug' => [
            'scheme_id' => $scheme_id,
            'scheme_name' => $scheme['SchemeName'],
            'installment_count' => count($installments)
        ],
        'installments' => $installments
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Error in get_available_installments.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error occurred', 'details' => $e->getMessage()]);
}
