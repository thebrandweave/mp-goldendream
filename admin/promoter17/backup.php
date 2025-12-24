<?php
session_start();
// Check if user is logged in


$menuPath = "../";
$currentPage = "mp_promoters";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Function to get promoter's children (both sub-promoters and customers)
function getChildren($conn, $parentPromoterID)
{
    // Get sub-promoters - exact match on ParentPromoterID
    $promoterStmt = $conn->prepare("
        SELECT 
            p.*,
            (SELECT COUNT(*) FROM mp_customers WHERE PromoterID = p.PromoterUniqueID) as customer_count,
            (SELECT COUNT(*) FROM mp_promoters WHERE ParentPromoterID = p.PromoterUniqueID) as sub_promoter_count
        FROM mp_promoters p
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
        FROM mp_customers c
        WHERE c.PromoterID = :promoterId
        ORDER BY c.CreatedAt DESC
    ");
    $customerStmt->bindParam(':promoterId', $parentPromoterID);
    $customerStmt->execute();
    $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'mp_promoters' => $promoters,
        'mp_customers' => $customers
    ];
}

// Get top-level promoters (those with no parent)
try {
    $rootStmt = $conn->prepare("
        SELECT 
            p.*,
            (SELECT COUNT(*) FROM mp_customers WHERE PromoterID = p.PromoterUniqueID) as customer_count,
            (SELECT COUNT(*) FROM mp_promoters WHERE ParentPromoterID = p.PromoterUniqueID) as sub_promoter_count
        FROM mp_promoters p
        WHERE p.ParentPromoterID IS NULL 
           OR p.ParentPromoterID = ''
           OR p.ParentPromoterID = 'NULL'
           OR p.ParentPromoterID = 'No Parent'
        ORDER BY p.CreatedAt DESC
    ");
    $rootStmt->execute();
    $rootPromoters = $rootStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rootPromoters)) {
        // Debug output
        echo "<!-- Debug: No root promoters found -->";

        // Check total promoters and their ParentPromoterID values
        $debugStmt = $conn->query("SELECT PromoterUniqueID, Name, ParentPromoterID FROM mp_promoters");
        $debugData = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<!-- Debug: All promoters data: " . json_encode($debugData) . " -->";

        // Count total promoters
        $countStmt = $conn->query("SELECT COUNT(*) as total FROM mp_promoters");
        $totalPromoters = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<!-- Debug: Total promoters in system: $totalPromoters -->";
    }
} catch (PDOException $e) {
    echo "<!-- Debug: " . $e->getMessage() . " -->";
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
            overflow-x: auto;
            min-height: calc(100vh - 200px);
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .tree {
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .tree ul {
            padding-top: 20px;
            position: relative;
            transition: all 0.5s;
            padding-left: 0;
        }

        .tree li {
            float: left;
            text-align: center;
            list-style-type: none;
            position: relative;
            padding: 20px 5px 0 5px;
            transition: all 0.5s;
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
            padding: 10px 15px;
            border-radius: 8px;
            display: inline-block;
            min-width: 250px;
            position: relative;
        }

        .promoter-node {
            background: linear-gradient(135deg, rgba(58, 123, 213, 0.1), rgba(0, 210, 255, 0.1));
            border: 2px solid var(--ad_primary-color);
        }

        .customer-node {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(0, 210, 255, 0.1));
            border: 2px solid var(--ad_success-color);
        }

        .node-header {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .node-id {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 5px;
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
            background-color: var(--ad_success-color);
        }

        .status-inactive {
            background-color: var(--danger-color);
        }

        .expand-collapse {
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 24px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1;
            transition: all 0.3s;
        }

        .expand-collapse:hover {
            background: var(--ad_primary-color);
            color: white;
            border-color: var(--ad_primary-color);
        }

        .tree li.collapsed>ul {
            display: none;
        }

        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box button {
            padding: 10px 20px;
            background: var(--ad_primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
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

            <div class="tree">
                <?php if (!empty($rootPromoters)): ?>
                    <ul>
                        <?php foreach ($rootPromoters as $promoter): ?>
                            <li>
                                <?php displayPromoterNode($conn, $promoter); ?>
                            </li>
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

    <script>
        function toggleChildren(element) {
            const li = element.closest('li');
            li.classList.toggle('collapsed');
            const icon = element.querySelector('i');
            icon.classList.toggle('fa-plus');
            icon.classList.toggle('fa-minus');
        }

        function searchHierarchy() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const nodes = document.querySelectorAll('.tree-node');

            nodes.forEach(node => {
                const text = node.textContent.toLowerCase();
                const li = node.closest('li');

                if (text.includes(searchTerm)) {
                    node.style.backgroundColor = 'rgba(58, 123, 213, 0.2)';
                    // Expand all parent nodes
                    let parent = li.parentElement;
                    while (parent) {
                        if (parent.tagName === 'LI') {
                            parent.classList.remove('collapsed');
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
        </div>
        <div class="node-id">
            ID: <?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?>
            <?php if (!empty($promoter['ParentPromoterID'])): ?>
                <br>
                <small>Parent: <?php echo htmlspecialchars($promoter['ParentPromoterID']); ?></small>
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
            <div class="expand-collapse" onclick="toggleChildren(this.parentElement)">
                <i class="fas fa-minus"></i>
            </div>
            <ul>
                <?php foreach ($children['promoters'] as $subPromoter): ?>
                    <li class="promoter-branch">
                        <?php displayPromoterNode($conn, $subPromoter); ?>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($children['customers'] as $customer): ?>
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
                            <div class="node-stats">
                                <span class="node-stat">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($customer['Contact']); ?>
                                </span>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php
}
?>