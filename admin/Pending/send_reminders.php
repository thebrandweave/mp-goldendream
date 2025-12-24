<?php
session_start();
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$installmentId = $data['installment_id'] ?? null;
$schemeId = $data['scheme_id'] ?? null;

if (!$installmentId || !$schemeId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    // Get installment details
    $stmt = $conn->prepare("
        SELECT i.*, s.SchemeName 
        FROM Installments i 
        JOIN Schemes s ON i.SchemeID = s.SchemeID 
        WHERE i.InstallmentID = ?
    ");
    $stmt->execute([$installmentId]);
    $installment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$installment) {
        throw new Exception('Installment not found');
    }

    // Get customers with pending payments
    $stmt = $conn->prepare("
        SELECT c.CustomerID, c.CustomerUniqueID, c.Name, c.Contact
        FROM Customers c
        JOIN Subscriptions sub ON sub.CustomerID = c.CustomerID AND sub.SchemeID = ?
        LEFT JOIN Payments p ON p.CustomerID = c.CustomerID AND p.InstallmentID = ?
        WHERE (p.PaymentID IS NULL OR p.Status = 'Rejected')
        AND c.Status = 'Active'
        AND sub.RenewalStatus = 'Active'
    ");
    $stmt->execute([$schemeId, $installmentId]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get WhatsApp API configuration
    $stmt = $conn->prepare("SELECT APIEndpoint, InstanceID, AccessToken, Status FROM WhatsAppAPIConfig WHERE Status = 'Active' ORDER BY ConfigID DESC LIMIT 1");
    $stmt->execute();
    $whatsappConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$whatsappConfig || $whatsappConfig['Status'] !== 'Active') {
        throw new Exception('WhatsApp API is not configured or inactive');
    }

    $successCount = 0;
    $failedCount = 0;
    $errorLogs = [];

    foreach ($customers as $customer) {
        try {
            // Format phone number (remove any non-numeric characters)
            $phone = preg_replace('/[^0-9]/', '', $customer['Contact']);

            // Add country code if not present
            if (strlen($phone) === 10) {
                $phone = '91' . $phone;
            }

            // Prepare message
            $message = "Dear " . $customer['Name'] . ",\n\n";
            $message .= "This is a reminder that your payment for " . $installment['SchemeName'] . " - " . $installment['InstallmentName'] . " is pending.\n\n";
            $message .= "Amount: ₹" . number_format($installment['Amount'], 2) . "\n";
            $message .= "Due Date: " . date('d M Y', strtotime($installment['DrawDate'])) . "\n\n";
            $message .= "Please submit your payment at the earliest to avoid any inconvenience.\n\n";
            $message .= "If already paid, please ignore this message.\n\n";
            $message .= "Thank you,\nGolden Dreams Team";

            // Log the request details
            error_log("Sending WhatsApp message to: " . $phone);
            error_log("API URL: " . $whatsappConfig['APIEndpoint']);
            error_log("Message: " . $message);

            // Prepare API URL with parameters
            $apiUrl = $whatsappConfig['APIEndpoint'] . 'send?number=' . $phone . '&type=text&message=' . urlencode($message) . '&instance_id=' . $whatsappConfig['InstanceID'] . '&access_token=' . $whatsappConfig['AccessToken'];

            // Send request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log the response
            error_log("WhatsApp API Response: " . $response);

            // Check if request was successful
            if ($httpCode == 200) {
                $responseData = json_decode($response, true);
                if (isset($responseData['status']) && $responseData['status'] === 'success') {
                    $successCount++;
                    error_log("Message sent successfully to: " . $phone);
                } else {
                    $failedCount++;
                    $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
                    $errorLogs[] = "Failed to send to " . $phone . ": " . $errorMessage;
                    error_log("Failed to send message to: " . $phone . " - " . $errorMessage);
                }
            } else {
                $failedCount++;
                $errorLogs[] = "HTTP Error " . $httpCode . " for " . $phone;
                error_log("HTTP Error " . $httpCode . " for " . $phone);
            }
        } catch (Exception $e) {
            $failedCount++;
            $errorLogs[] = "Error sending to " . $phone . ": " . $e->getMessage();
            error_log("Exception while sending to " . $phone . ": " . $e->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Reminders sent: $successCount successful, $failedCount failed",
        'errors' => $errorLogs
    ]);
} catch (Exception $e) {
    error_log("Main exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
