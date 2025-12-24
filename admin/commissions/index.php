<?php
session_start();
$menuPath = "../";
$currentPage = "commissions";
require_once($menuPath . "../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// --- Handle commission update POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_commission'])) {
    $childId = (int)$_POST['child_id'];
    $childCommission = (int)$_POST['child_commission'];
    $parentUniqueId = $_POST['parent_unique_id'];
    $parentActualCommission = (int)$_POST['parent_actual_commission'];

    // Validation
    if ($childCommission < 0 || $childCommission > $parentActualCommission) {
        $_SESSION['error_message'] = "Child commission must be between 0 and parent commission ($parentActualCommission).";
        header("Location: index.php");
        exit();
    }
    $parentCommissionInChildRow = $parentActualCommission - $childCommission;

    try {
        $conn->beginTransaction();
        // Update child commission and parent commission in child row
        $stmt = $conn->prepare("UPDATE Promoters SET Commission = ?, ParentCommission = ? WHERE PromoterID = ?");
        $stmt->execute([$childCommission, $parentCommissionInChildRow, $childId]);
        $conn->commit();
        $_SESSION['success_message'] = "Commission updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update commission: " . $e->getMessage();
    }
    header("Location: index.php");
    exit();
}

// --- Pagination settings ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// --- Filters ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$parentId = isset($_GET['parent_id']) ? trim($_GET['parent_id']) : '';

// --- Build query conditions ---
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(child.Name LIKE :search OR child.PromoterUniqueID LIKE :search OR parent.Name LIKE :search OR parent.PromoterUniqueID LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($parentId)) {
    $conditions[] = "child.ParentPromoterID = :parent_id";
    $params[':parent_id'] = $parentId;
}
$whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// --- Get total records for pagination ---
$countQuery = "SELECT COUNT(*) as total FROM Promoters child LEFT JOIN Promoters parent ON child.ParentPromoterID = parent.PromoterUniqueID $whereClause";
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// --- Get parent promoters for filter dropdown ---
$parentQuery = "SELECT PromoterUniqueID, Name FROM Promoters WHERE Status = 'Active' ORDER BY Name";
$parentStmt = $conn->prepare($parentQuery);
$parentStmt->execute();
$parentPromoters = $parentStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch paginated commission data ---
$query = "SELECT 
    child.PromoterID AS ChildID,
    child.PromoterUniqueID AS ChildUniqueID,
    child.Name AS ChildName,
    child.Commission AS ChildCommission,
    child.ParentCommission AS ChildParentCommission,
    child.ParentPromoterID AS ParentUniqueID,
    parent.PromoterID AS ParentID,
    parent.PromoterUniqueID AS ParentUniqueID,
    parent.Name AS ParentName,
    parent.Commission AS ParentCommission
FROM Promoters child
LEFT JOIN Promoters parent ON child.ParentPromoterID = parent.PromoterUniqueID
$whereClause
ORDER BY child.PromoterID ASC
LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

include($menuPath . "components/sidebar.php");
include($menuPath . "components/topbar.php");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Data</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .commission-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .commission-table th, .commission-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e0e0e0; font-size: 14px; }
        .commission-table th { background: #f4f8fb; color: #34495e; font-weight: 600; }
        .commission-table td .badge { display: inline-block; padding: 4px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #fff; }
        .badge-blue { background: #3a7bd5; }
        .badge-cyan { background: #00d2ff; }
        .badge-green { background: #2ecc71; }
        .edit-btn { background: #ffc107; color: #222; border: none; border-radius: 8px; padding: 7px 18px; font-weight: 500; cursor: pointer; transition: background 0.2s; }
        .edit-btn:hover { background: #e0a800; }
        .commission-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .commission-header h2 { color: #2360a5; font-size: 22px; font-weight: 700; }
        .records-count { background: #2360a5; color: #fff; border-radius: 20px; padding: 6px 18px; font-size: 14px; font-weight: 500; }
        .filter-container { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; padding: 20px; background-color: #f4f8fb; border-radius: 8px; }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-group label { font-weight: 500; font-size: 14px; color: #34495e; }
        .filter-select { padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 14px; min-width: 170px; background-color: #fff; transition: all 0.2s; }
        .filter-select:focus { border-color: #3a7bd5; box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1); outline: none; }
        .filter-btn { padding: 10px 15px; background: #3a7bd5; color: white; border: none; border-radius: 6px; font-size: 14px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .filter-btn:hover { background: #2c60a9; transform: translateY(-1px); }
        .reset-btn { padding: 10px 15px; background: #fff; border: 1px solid #e74c3c; border-radius: 6px; font-size: 14px; color: #e74c3c; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 6px; text-decoration: none; }
        .reset-btn:hover { background: rgba(231, 76, 60, 0.05); color: #c0392b; }
        .pagination { display: flex; list-style: none; padding: 0; margin: 25px 0; justify-content: center; gap: 6px; }
        .pagination li { margin: 0; }
        .pagination a, .pagination span { display: flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 8px; border-radius: 6px; text-decoration: none; color: #34495e; background: white; border: 1px solid #e0e0e0; transition: all 0.2s; font-size: 14px; }
        .pagination a:hover { background: #f4f8fb; border-color: #3a7bd5; color: #3a7bd5; }
        .pagination .active a { background: #3a7bd5; color: white; border-color: #3a7bd5; box-shadow: 0 2px 5px rgba(58, 123, 213, 0.3); }
        .no-data { padding: 50px 20px; text-align: center; color: #7f8c8d; }
        .no-data p { margin-bottom: 20px; font-size: 16px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; transition: all 0.2s; font-weight: 500; }
        .btn-primary { background: #3a7bd5; color: white; }
        .btn-primary:hover { background: #2c60a9; transform: translateY(-2px); }
        /* Modal styles */
        .modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background: rgba(0,0,0,0.3); }
        .modal-content { background: #fff; margin: 8% auto; padding: 30px 30px 20px 30px; border-radius: 10px; width: 100%; max-width: 420px; box-shadow: 0 4px 16px rgba(0,0,0,0.15); position: relative; }
        .modal-header { font-size: 20px; font-weight: 600; margin-bottom: 18px; color: #2360a5; }
        .modal-close { position: absolute; right: 18px; top: 18px; font-size: 22px; color: #888; cursor: pointer; }
        .modal-close:hover { color: #e74c3c; }
        .modal-form-group { margin-bottom: 18px; }
        .modal-form-group label { font-weight: 500; color: #34495e; margin-bottom: 6px; display: block; }
        .modal-form-group input[type='number'] { width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 15px; }
        .modal-form-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .modal-form-actions button { padding: 10px 18px; border-radius: 6px; border: none; font-weight: 500; cursor: pointer; }
        .modal-form-actions .btn-cancel { background: #eee; color: #333; }
        .modal-form-actions .btn-save { background: #3a7bd5; color: #fff; }
        .modal-form-actions .btn-save:hover { background: #2360a5; }
    </style>
</head>
<body>
<div class="content-wrapper">
    <div class="commission-header">
        <h2><i class="fas fa-coins"></i> Commission Data</h2>
        <span class="records-count"><?php echo $totalRecords; ?> Records Found</span>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <form action="" method="GET" class="filter-container">
        <div class="filter-group">
            <label for="search">Search:</label>
            <input type="text" name="search" id="search" class="filter-select" placeholder="Child/Parent Name or ID..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label for="parent_id">Parent Promoter:</label>
            <select name="parent_id" id="parent_id" class="filter-select">
                <option value="">All Parent Promoters</option>
                <?php foreach ($parentPromoters as $parent): ?>
                    <option value="<?php echo htmlspecialchars($parent['PromoterUniqueID']); ?>" <?php echo ($parentId == $parent['PromoterUniqueID']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($parent['PromoterUniqueID'] . ' - ' . $parent['Name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
        <?php if (!empty($search) || !empty($parentId)): ?>
            <a href="index.php" class="reset-btn"><i class="fas fa-times"></i> Reset Filters</a>
        <?php endif; ?>
    </form>

    <?php if (count($rows) > 0): ?>
        <div class="responsive-table">
        <table class="commission-table">
            <thead>
                <tr>
                    <th>Child ID</th>
                    <th>Child Unique ID</th>
                    <th>Child Name</th>
                    <th>Child Commission</th>
                    <th>Parent Commission</th>
                    <th>Parent ID</th>
                    <th>Parent Unique ID</th>
                    <th>Parent Name</th>
                    <th>Parent Commission</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['ChildID']); ?></td>
                    <td><?php echo htmlspecialchars($row['ChildUniqueID']); ?></td>
                    <td><?php echo htmlspecialchars($row['ChildName']); ?></td>
                    <td><span class="badge badge-blue"><?php echo htmlspecialchars($row['ChildCommission'] ?? 0); ?></span></td>
                    <td><span class="badge badge-cyan"><?php echo htmlspecialchars($row['ChildParentCommission'] ?? 0); ?></span></td>
                    <td><?php echo htmlspecialchars($row['ParentID'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['ParentUniqueID'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['ParentName'] ?? '-'); ?></td>
                    <td><span class="badge badge-green"><?php echo htmlspecialchars($row['ParentCommission'] ?? 0); ?></span></td>
                    <td><button class="edit-btn" 
                        data-child-id="<?php echo htmlspecialchars($row['ChildID']); ?>"
                        data-child-name="<?php echo htmlspecialchars($row['ChildName']); ?>"
                        data-child-commission="<?php echo htmlspecialchars($row['ChildCommission'] ?? 0); ?>"
                        data-parent-unique-id="<?php echo htmlspecialchars($row['ParentUniqueID'] ?? ''); ?>"
                        data-parent-name="<?php echo htmlspecialchars($row['ParentName'] ?? ''); ?>"
                        data-parent-actual-commission="<?php echo htmlspecialchars($row['ParentCommission'] ?? 0); ?>"
                        >
                        <i class="fas fa-edit"></i> Edit</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <!-- Modal for editing commission -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="modal-close" id="modalClose">&times;</span>
                <div class="modal-header">Edit Commission</div>
                <form method="POST" id="editCommissionForm">
                    <input type="hidden" name="edit_commission" value="1">
                    <input type="hidden" name="child_id" id="modalChildId">
                    <input type="hidden" name="parent_unique_id" id="modalParentUniqueId">
                    <input type="hidden" name="parent_actual_commission" id="modalParentActualCommission">
                    <div class="modal-form-group">
                        <label>Child Name</label>
                        <input type="text" id="modalChildName" class="filter-select" readonly>
                    </div>
                    <div class="modal-form-group">
                        <label>Parent Name</label>
                        <input type="text" id="modalParentName" class="filter-select" readonly>
                    </div>
                    <div class="modal-form-group">
                        <label>Parent's Actual Commission</label>
                        <input type="number" id="modalParentCommissionDisplay" class="filter-select" readonly>
                    </div>
                    <div class="modal-form-group">
                        <label>Child Commission <span id="modalCommissionRange"></span></label>
                        <input type="number" name="child_commission" id="modalChildCommission" min="0" required>
                    </div>
                    <div class="modal-form-actions">
                        <button type="button" class="btn-cancel" id="modalCancel">Cancel</button>
                        <button type="submit" class="btn-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        // Modal logic
        const modal = document.getElementById('editModal');
        const modalClose = document.getElementById('modalClose');
        const modalCancel = document.getElementById('modalCancel');
        const editBtns = document.querySelectorAll('.edit-btn');
        const modalChildId = document.getElementById('modalChildId');
        const modalChildName = document.getElementById('modalChildName');
        const modalChildCommission = document.getElementById('modalChildCommission');
        const modalParentUniqueId = document.getElementById('modalParentUniqueId');
        const modalParentName = document.getElementById('modalParentName');
        const modalParentActualCommission = document.getElementById('modalParentActualCommission');
        const modalParentCommissionDisplay = document.getElementById('modalParentCommissionDisplay');
        const modalCommissionRange = document.getElementById('modalCommissionRange');

        editBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const childId = this.getAttribute('data-child-id');
                const childName = this.getAttribute('data-child-name');
                const childCommission = this.getAttribute('data-child-commission');
                const parentUniqueId = this.getAttribute('data-parent-unique-id');
                const parentName = this.getAttribute('data-parent-name');
                const parentActualCommission = this.getAttribute('data-parent-actual-commission');

                modalChildId.value = childId;
                modalChildName.value = childName;
                modalChildCommission.value = childCommission;
                modalChildCommission.max = parentActualCommission;
                modalChildCommission.min = 0;
                modalParentUniqueId.value = parentUniqueId;
                modalParentName.value = parentName;
                modalParentActualCommission.value = parentActualCommission;
                modalParentCommissionDisplay.value = parentActualCommission;
                modalCommissionRange.textContent = `(0 - ${parentActualCommission})`;
                modal.style.display = 'block';
            });
        });
        modalClose.onclick = modalCancel.onclick = function() {
            modal.style.display = 'none';
        };
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };
        </script>
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li><a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-double-left"></i></a></li>
                    <li><a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-left"></i></a></li>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                if ($startPage > 1) {
                    echo '<li><a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">1</a></li>';
                    if ($startPage > 2) {
                        echo '<li><span>...</span></li>';
                    }
                }
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<li class="' . ($page == $i ? 'active' : '') . '"><a href="?page=' . $i .
                        (!empty($search) ? '&search=' . urlencode($search) : '') .
                        (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">' . $i . '</a></li>';
                }
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<li><span>...</span></li>';
                    }
                    echo '<li><a href="?page=' . $totalPages .
                        (!empty($search) ? '&search=' . urlencode($search) : '') .
                        (!empty($parentId) ? '&parent_id=' . urlencode($parentId) : '') . '">' . $totalPages . '</a></li>';
                }
                ?>
                <?php if ($page < $totalPages): ?>
                    <li><a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-right"></i></a></li>
                    <li><a href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; echo !empty($parentId) ? '&parent_id=' . urlencode($parentId) : ''; ?>"><i class="fas fa-angle-double-right"></i></a></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-data">
            <?php if (!empty($search) || !empty($parentId)): ?>
                <p>No commissions found matching your criteria.</p>
                <a href="index.php" class="btn btn-primary">Clear Filters</a>
            <?php else: ?>
                <p>No commission records found in the system.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html> 