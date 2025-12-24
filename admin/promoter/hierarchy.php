<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "promoters";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Function to get promoter's children (both sub-promoters and customers)
function getChildren($conn, $parentPromoterID)
{
    if (empty($parentPromoterID)) {
        return ['promoters' => [], 'customers' => []];
    }

    // Get sub-promoters
    $promoterStmt = $conn->prepare("
        SELECT 
            p.*,
            (SELECT COUNT(*) FROM Customers WHERE PromoterID = p.PromoterUniqueID) as customer_count,
            (SELECT COUNT(*) FROM Promoters WHERE ParentPromoterID = p.PromoterUniqueID) as sub_promoter_count
        FROM Promoters p
        WHERE p.ParentPromoterID = :parentId
        ORDER BY p.CreatedAt DESC
    ");
    $promoterStmt->bindParam(':parentId', $parentPromoterID);
    $promoterStmt->execute();
    $promoters = $promoterStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get direct customers
    $customerStmt = $conn->prepare("
        SELECT 
            c.*,
            'customer' as node_type
        FROM Customers c
        WHERE c.PromoterID = :promoterId
        ORDER BY c.CreatedAt DESC
    ");
    $customerStmt->bindParam(':promoterId', $parentPromoterID);
    $customerStmt->execute();
    $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'promoters' => $promoters,
        'customers' => $customers
    ];
}

// Get top-level promoters (those with no parent)
try {
    $rootStmt = $conn->prepare("
        SELECT 
            p.*,
            (SELECT COUNT(*) FROM Customers WHERE PromoterID = p.PromoterUniqueID) as customer_count,
            (SELECT COUNT(*) FROM Promoters WHERE ParentPromoterID = p.PromoterUniqueID) as sub_promoter_count
        FROM Promoters p
        WHERE (p.ParentPromoterID IS NULL OR p.ParentPromoterID = '' OR p.ParentPromoterID = 'NULL')
        AND p.Name IS NOT NULL 
        AND p.Name != ''
        ORDER BY p.CreatedAt DESC
    ");
    $rootStmt->execute();
    $rootPromoters = $rootStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving promoter hierarchy: " . $e->getMessage();
    $rootPromoters = [];
}

// Include header and sidebar
include("../components/sidebar.php");
include("../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promoter Hierarchy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .hierarchy-container {
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            margin: 20px;
            overflow-x: auto;
            min-height: calc(100vh - 200px);
        }

        .tree-wrapper {
            min-width: 100%;
            width: max-content;
            padding: 20px;
        }

        .tree {
            display: flex;
            justify-content: center;
            min-width: 100%;
            width: max-content;
        }

        .tree ul {
            padding-top: 20px;
            position: relative;
            transition: all 0.5s;
            padding-left: 0;
            display: flex;
            flex-wrap: nowrap;
        }

        .tree li {
            float: left;
            text-align: center;
            list-style-type: none;
            position: relative;
            padding: 20px 5px 0 5px;
            transition: all 0.5s;
            flex-shrink: 0;
        }

        .tree li::before,
        .tree li::after {
            content: '';
            position: absolute;
            top: 0;
            right: 50%;
            border-top: 2px solid #ccc;
            width: 50%;
            height: 20px;
        }

        .tree li::after {
            right: auto;
            left: 50%;
            border-left: 2px solid #ccc;
        }

        .tree li:only-child::after,
        .tree li:only-child::before {
            display: none;
        }

        .tree li:first-child::before,
        .tree li:last-child::after {
            border: 0 none;
        }

        .tree li:last-child::before {
            border-right: 2px solid #ccc;
            border-radius: 0 5px 0 0;
        }

        .tree li:first-child::after {
            border-radius: 5px 0 0 0;
        }

        .tree ul ul::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            border-left: 2px solid #ccc;
            width: 0;
            height: 20px;
        }

        .tree-node {
            padding: 15px;
            border-radius: 8px;
            display: inline-block;
            min-width: 300px;
            position: relative;
            margin: 10px;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .promoter-node {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border: 2px solid #3a7bd5;
        }

        .customer-node {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border: 2px solid #2ecc71;
        }

        .node-header {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .node-id-inline {
            font-size: 12px;
            color: var(--text-light);
            font-weight: normal;
        }

        .node-details {
            margin: 10px 0;
            padding: 5px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .node-contact {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
        }

        .node-email {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
        }

        .node-stats {
            display: flex;
            justify-content: center;
            gap: 15px;
            font-size: 12px;
            margin-top: 8px;
        }

        .node-stat {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-active {
            background-color: #2ecc71;
        }

        .status-inactive {
            background-color: #e74c3c;
        }

        .hierarchy-expand-collapse {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: var(--ad_primary-color);
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            position: sticky;
            left: 0;
            background: white;
            padding: 10px 0;
            z-index: 1;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            max-width: 300px;
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--ad_primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-secondary {
            background: var(--ad_secondary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }

        .btn-secondary:hover {
            background: var(--ad_secondary-hover);
            color: white;
        }

        .hierarchy-collapsed>ul {
            display: none;
        }

        .hierarchy-collapsed .hierarchy-expand-collapse i {
            transform: rotate(-90deg);
        }

        .hierarchy-expand-collapse i {
            transition: transform 0.3s ease;
        }

        /* Collapsible tree styles */
        .tree-node {
            position: relative;
        }

        .tree-node ul {
            display: none;
            /* Hide all child lists by default */
        }

        .tree-node.expanded ul {
            display: flex;
            /* Show child lists when expanded */
        }

        .tree-toggle {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: var(--ad_primary-color);
            z-index: 10;
        }

        .tree-toggle i {
            transition: transform 0.3s ease;
        }

        .tree-node.expanded .tree-toggle i {
            transform: rotate(180deg);
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Promoter Hierarchy</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Promoters
            </a>
        </div>

        <div class="hierarchy-container">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by name or ID...">
                <button onclick="searchHierarchy()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <div class="tree-wrapper">
                <div class="tree">
                    <?php if (!empty($rootPromoters)): ?>
                        <ul>
                            <?php foreach ($rootPromoters as $promoter): ?>
                                <?php if (!empty($promoter['Name'])): ?>
                                    <li>
                                        <?php displayPromoterNode($conn, $promoter); ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="no-data" style="text-align: center; padding: 20px;">
                            <p>No promoters found in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to toggle the expanded state of a node
        function toggleNode(element) {
            // Find the closest tree-node parent
            const node = element.closest('.tree-node');
            if (node) {
                // Toggle only this specific node
                node.classList.toggle('expanded');

                // Ensure child nodes remain collapsed
                const childNodes = node.querySelectorAll('.tree-node');
                childNodes.forEach(childNode => {
                    childNode.classList.remove('expanded');
                });
            }
        }

        // Function to search in the hierarchy
        function searchHierarchy() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const nodes = document.querySelectorAll('.tree-node');

            nodes.forEach(node => {
                const text = node.textContent.toLowerCase();

                if (text.includes(searchTerm)) {
                    node.style.backgroundColor = 'rgba(58, 123, 213, 0.2)';

                    // Expand all parent nodes
                    let parent = node.parentElement;
                    while (parent) {
                        if (parent.classList.contains('tree-node')) {
                            parent.classList.add('expanded');
                        }
                        parent = parent.parentElement;
                    }
                } else {
                    node.style.backgroundColor = '';
                }
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchHierarchy();
            }
        });

        // Add click event listeners to all toggle buttons after the page loads
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButtons = document.querySelectorAll('.tree-toggle');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event bubbling
                    toggleNode(this);
                });
            });
        });
    </script>
</body>

</html>

<?php
function displayPromoterNode($conn, $promoter)
{
    $children = getChildren($conn, $promoter['PromoterUniqueID']);
?>
    <div class="tree-node promoter-node">
        <div class="node-header">
            <span class="status-indicator status-<?php echo strtolower($promoter['Status']); ?>"></span>
            <i class="fas fa-user-tie"></i>
            <?php echo htmlspecialchars($promoter['Name']); ?>
            <span class="node-id-inline">(ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID'] ?: 'N/A'); ?>)</span>
        </div>
        <div class="node-details">
                    <?php if (!empty($promoter['Contact'])): ?>
                <div class="node-contact">
                 <strong>   <i class="fas fa-person"></i> <?php echo htmlspecialchars($promoter['Name']); ?></strong>
                </div>
            <?php endif; ?>
            <?php if (!empty($promoter['Contact'])): ?>
                <div class="node-contact">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($promoter['Contact']); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($promoter['Email'])): ?>
                <div class="node-email">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($promoter['Email']); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="node-stats">
            <span class="node-stat" title="Direct Customers">
                <i class="fas fa-users"></i>
                <?php echo $promoter['customer_count']; ?>
            </span>
            <span class="node-stat" title="Sub Promoters">
                <i class="fas fa-user-friends"></i>
                <?php echo $promoter['sub_promoter_count']; ?>
            </span>
            <span class="node-stat" title="Payment Codes">
                <i class="fas fa-ticket"></i>
                <?php echo $promoter['PaymentCodeCounter']; ?>
            </span>
        </div>
        <?php if (!empty($children['promoters']) || !empty($children['customers'])): ?>
            <div class="tree-toggle">
                <i class="fas fa-chevron-down"></i>
            </div>
            <ul>
                <?php foreach ($children['promoters'] as $subPromoter): ?>
                    <?php if (!empty($subPromoter['Name'])): ?>
                        <li class="promoter-branch">
                            <?php displayPromoterNode($conn, $subPromoter); ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php foreach ($children['customers'] as $customer): ?>
                    <?php if (!empty($customer['Name'])): ?>
                        <li class="customer-branch">
                            <div class="tree-node customer-node">
                                <div class="node-header">
                                    <span class="status-indicator status-<?php echo strtolower($customer['Status']); ?>"></span>
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($customer['Name']); ?>
                                </div>
                                <div class="node-id">
                                    ID: <?php echo htmlspecialchars($customer['CustomerUniqueID']); ?>
                                </div>
                                <?php if (!empty($customer['Contact'])): ?>
                                    <div class="node-contact">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['Contact']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($customer['Email'])): ?>
                                    <div class="node-email">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['Email']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php
}
?>