<?php
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

header("Content-Type: application/json");

$chart = $_GET["chart"] ?? "";
$period = $_GET["period"] ?? "week";

if ($chart == "revenue-chart") {
    // Get revenue data
    $data = getRevenueChartData($conn, $period);
    echo json_encode($data);
} elseif ($chart == "customers-chart") {
    // Get customer growth data
    $data = getCustomerGrowthData($conn, $period);
    echo json_encode($data);
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid chart type"]);
}

// Get revenue data for chart
function getRevenueChartData($conn, $period = "week") {
    // Same function as in dashboard.php
    $data = ["labels" => [], "values" => []];
    
    // Function implementation here...
    // Copy same implementation from dashboard.php
    
    return $data;
}

// Get customer growth data for chart
function getCustomerGrowthData($conn, $period = "week") {
    // Same function as in dashboard.php
    $data = [
        "labels" => [],
        "new" => [],
        "returning" => []
    ];
    
    // Function implementation here...
    // Copy same implementation from dashboard.php
    
    return $data;
}
