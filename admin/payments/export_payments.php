<?php
// Prevent any output before headers
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Increase memory limit for large datasets
ini_set('memory_limit', '256M');

session_start();
require_once("../../config/config.php");

// Check if PHPSpreadsheet is installed
if (!file_exists('../../vendor/autoload.php')) {
    die('PHPSpreadsheet library is not installed. Please run: composer require phpoffice/phpspreadsheet');
}

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get filter parameters
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
    $schemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : '';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $promoterId = isset($_GET['promoter_id']) ? $_GET['promoter_id'] : '';

    // Build query conditions
    $conditions = [];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(c.Name LIKE :search OR c.CustomerUniqueID LIKE :search OR c.Contact LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if (!empty($status)) {
        $conditions[] = "p.Status = :status";
        $params[':status'] = $status;
    }

    if (!empty($schemeId)) {
        $conditions[] = "p.SchemeID = :schemeId";
        $params[':schemeId'] = $schemeId;
    }

    if (!empty($startDate)) {
        $conditions[] = "DATE(p.SubmittedAt) >= :startDate";
        $params[':startDate'] = $startDate;
    }

    if (!empty($endDate)) {
        $conditions[] = "DATE(p.SubmittedAt) <= :endDate";
        $params[':endDate'] = $endDate;
    }

    if (!empty($promoterId)) {
        $conditions[] = "c.PromoterID = :promoterId";
        $params[':promoterId'] = $promoterId;
    }

    $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

    // Get payments data
    $query = "
        SELECT 
            p.PaymentID,
            c.CustomerUniqueID,
            c.Name as CustomerName,
            c.Contact,
            s.SchemeName,
            p.Amount,
            p.PaymentCodeValue,
            p.ScreenshotURL,
            p.Status,
            p.SubmittedAt,
            p.VerifiedAt,
            pr.PromoterUniqueID,
            pr.Name as PromoterName,
            a.Name as VerifierName,
            p.PayerRemark,
            p.VerifierRemark
        FROM Payments p
        LEFT JOIN Customers c ON p.CustomerID = c.CustomerID
        LEFT JOIN Schemes s ON p.SchemeID = s.SchemeID
        LEFT JOIN Promoters pr ON c.PromoterID = pr.PromoterUniqueID
        LEFT JOIN Admins a ON p.AdminID = a.AdminID"
        . $whereClause .
        " ORDER BY p.SubmittedAt DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = [
        'Payment ID',
        'Customer ID',
        'Customer Name',
        'Contact',
        'Scheme',
        'Amount',
        'Payment Code',
        'Screenshot',
        'Status',
        'Submitted At',
        'Verified At',
        'Promoter ID',
        'Promoter Name',
        'Verified By',
        'Payer Remarks',
        'Verifier Remarks'
    ];

    $sheet->fromArray([$headers], NULL, 'A1');

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '000000'],
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        ],
    ];
    $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

    // Add data
    $row = 2;
    foreach ($payments as $payment) {
        $sheet->setCellValue('A' . $row, $payment['PaymentID']);
        $sheet->setCellValue('B' . $row, $payment['CustomerUniqueID']);
        $sheet->setCellValue('C' . $row, $payment['CustomerName']);
        $sheet->setCellValue('D' . $row, $payment['Contact']);
        $sheet->setCellValue('E' . $row, $payment['SchemeName']);
        $sheet->setCellValue('F' . $row, number_format($payment['Amount'], 2));
        $sheet->setCellValue('G' . $row, $payment['PaymentCodeValue']);
        $sheet->setCellValue('H' . $row, $payment['ScreenshotURL']);
        $sheet->setCellValue('I' . $row, $payment['Status']);
        $sheet->setCellValue('J' . $row, date('Y-m-d H:i:s', strtotime($payment['SubmittedAt'])));
        $sheet->setCellValue('K' . $row, $payment['VerifiedAt'] ? date('Y-m-d H:i:s', strtotime($payment['VerifiedAt'])) : '');
        $sheet->setCellValue('L' . $row, $payment['PromoterUniqueID']);
        $sheet->setCellValue('M' . $row, $payment['PromoterName']);
        $sheet->setCellValue('N' . $row, $payment['VerifierName']);
        $sheet->setCellValue('O' . $row, $payment['PayerRemark']);
        $sheet->setCellValue('P' . $row, $payment['VerifierRemark']);
        $row++;
    }

    // Auto-size columns
    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Clear any previous output
    ob_clean();

    // Set the content type and headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="payments_export_' . date('Y-m-d_H-i-s') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Create Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    // Log the error
    error_log("Excel Export Error: " . $e->getMessage());

    // Clear any output
    ob_clean();

    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate Excel file. Please try again later.']);
    exit;
}
