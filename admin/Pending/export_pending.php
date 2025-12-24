<?php
session_start();
require_once("../../config/config.php");
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Get parameters
$selectedSchemeId = isset($_GET['scheme_id']) ? $_GET['scheme_id'] : null;
$installmentId = isset($_GET['installment_id']) ? $_GET['installment_id'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.Name LIKE :search OR c.CustomerUniqueID LIKE :search OR c.Contact LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($selectedSchemeId)) {
    $conditions[] = "s.SchemeID = :scheme_id";
    $params[':scheme_id'] = $selectedSchemeId;
}

if (!empty($installmentId)) {
    $conditions[] = "i.InstallmentID = :installment_id";
    $params[':installment_id'] = $installmentId;
}

$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Get pending payments
$query = "
    SELECT 
        c.CustomerUniqueID,
        c.Name as CustomerName,
        c.Contact,
        s.SchemeName,
        i.InstallmentName,
        i.InstallmentNumber,
        i.Amount as InstallmentAmount,
        i.DrawDate,
        CASE 
            WHEN p.Status IS NULL THEN 'Not Submitted'
            ELSE p.Status 
        END as PaymentStatus,
        p.SubmittedAt as PaymentSubmittedAt
    FROM Customers c
    JOIN Schemes s ON 1=1
    JOIN Installments i ON i.SchemeID = s.SchemeID
    JOIN Subscriptions sub ON sub.CustomerID = c.CustomerID AND sub.SchemeID = s.SchemeID
    LEFT JOIN Payments p ON p.CustomerID = c.CustomerID AND p.InstallmentID = i.InstallmentID
    $whereClause
    AND (p.PaymentID IS NULL OR p.Status != 'Verified')
    AND c.Status = 'Active'
    AND sub.RenewalStatus = 'Active'
    ORDER BY i.InstallmentNumber ASC, c.Name ASC";

$stmt = $conn->prepare($query);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

$pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set column headers
$headers = [
    'Customer ID',
    'Customer Name',
    'Contact',
    'Scheme',
    'Installment',
    'Amount',
    'Draw Date',
    'Payment Status',
    'Submitted At'
];

// Style the header row
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '217346'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
];

// Set headers and apply styles
foreach ($headers as $colIndex => $header) {
    $col = chr(65 + $colIndex); // Convert number to letter (A, B, C, etc.)
    $sheet->setCellValue($col . '1', $header);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}
$sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

// Add data rows
$row = 2;
foreach ($pendingPayments as $payment) {
    $sheet->setCellValue('A' . $row, $payment['CustomerUniqueID']);
    $sheet->setCellValue('B' . $row, $payment['CustomerName']);
    $sheet->setCellValue('C' . $row, $payment['Contact']);
    $sheet->setCellValue('D' . $row, $payment['SchemeName']);
    $sheet->setCellValue('E' . $row, $payment['InstallmentName'] . ' (#' . $payment['InstallmentNumber'] . ')');
    $sheet->setCellValue('F' . $row, $payment['InstallmentAmount']);
    $sheet->setCellValue('G' . $row, date('Y-m-d', strtotime($payment['DrawDate'])));
    $sheet->setCellValue('H' . $row, $payment['PaymentStatus']);
    $sheet->setCellValue('I' . $row, $payment['PaymentSubmittedAt'] ? date('Y-m-d', strtotime($payment['PaymentSubmittedAt'])) : '-');

    // Apply cell styles
    $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ]);

    // Format amount column
    $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

    // Format date columns
    $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode('yyyy-mm-dd');

    $row++;
}

//Create the Excel file

ob_clean();

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="pending_payments_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Save to PHP output
$writer = new Xlsx($spreadsheet);
ob_end_clean();
$writer->save('php://output');
exit;
