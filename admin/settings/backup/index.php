<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

$menuPath = "../../";
$currentPage = "backup";

// Database connection
require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Function to get database backup
function getDatabaseBackup($conn)
{
    try {
        // Get all tables
        $tables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $backup = "-- Golden Dream Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

        // Iterate through each table
        foreach ($tables as $table) {
            // Get create table statement
            $result = $conn->query("SHOW CREATE TABLE `$table`");
            $row = $result->fetch(PDO::FETCH_NUM);
            $backup .= "\n\n" . $row[1] . ";\n\n";

            // Get table data
            $result = $conn->query("SELECT * FROM `$table`");
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $values = array_map(function ($value) use ($conn) {
                    if ($value === null) return 'NULL';
                    return $conn->quote($value);
                }, $row);

                $backup .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
        }

        return $backup;
    } catch (Exception $e) {
        throw new Exception("Error creating backup: " . $e->getMessage());
    }
}

// Handle backup download
if (isset($_POST['download_backup'])) {
    try {
        $backup = getDatabaseBackup($conn);

        // Save backup to file in uploads/backups directory
        $backupDir = "../../../uploads/backups/";
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $filename = "backup_" . date('Y-m-d_H-i-s') . ".sql";
        $filepath = $backupDir . $filename;
        file_put_contents($filepath, $backup);

        // Save backup record to database with relative path
        $relativePath = "uploads/backups/" . $filename;
        $query = "INSERT INTO backups (file_url) VALUES (:file_url)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':file_url', $relativePath);
        $stmt->execute();

        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle historical backup download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    try {
        $filename = $_GET['download'];
        $filepath = "../../../" . $filename; // Use relative path from database

        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit();
        } else {
            $error = "Backup file not found.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle backup deletion
if (isset($_POST['delete_backup']) && !empty($_POST['delete_backup'])) {
    try {
        $filename = $_POST['delete_backup'];
        $filepath = "../../../" . $filename; // Use relative path from database

        if (file_exists($filepath)) {
            unlink($filepath);

            // Remove from database
            $query = "DELETE FROM backups WHERE file_url = :file_url";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':file_url', $filename);
            $stmt->execute();

            $success = "Backup deleted successfully.";
        } else {
            $error = "Backup file not found.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get recent backups
$query = "SELECT * FROM backups ORDER BY created_at DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$recentBackups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header and sidebar
include("../../components/sidebar.php");
include("../../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .backup-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .backup-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .backup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .backup-title {
            font-size: 24px;
            color: var(--secondary-color);
            margin: 0;
        }

        .btn-backup {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-backup:hover {
            background: var(--primary-dark);
        }

        .backup-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .backup-info p {
            margin: 5px 0;
            color: #666;
        }

        .backup-list {
            margin-top: 30px;
        }

        .backup-list h3 {
            color: var(--secondary-color);
            margin-bottom: 15px;
        }

        .backup-table {
            width: 100%;
            border-collapse: collapse;
        }

        .backup-table th,
        .backup-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .backup-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .backup-table tr:hover {
            background: #f8f9fa;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-download {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-download:hover {
            background: var(--primary-dark);
        }

        .btn-delete:hover {
            background: #c82333;
        }

        @media (max-width: 768px) {
            .backup-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .backup-table {
                display: block;
                overflow-x: auto;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="backup-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div class="backup-card">
                <div class="backup-header">
                    <h1 class="backup-title">Database Backup</h1>
                    <form method="POST" style="margin: 0;">
                        <button type="submit" name="download_backup" class="btn-backup">
                            <i class="fas fa-download"></i>
                            Create New Backup
                        </button>
                    </form>
                </div>

                <div class="backup-info">
                    <p><strong>Last Backup:</strong>
                        <?php
                        if (count($recentBackups) > 0) {
                            echo date('F j, Y g:i A', strtotime($recentBackups[0]['created_at']));
                        } else {
                            echo "No backups yet";
                        }
                        ?>
                    </p>
                    <p><strong>Backup Location:</strong> Backups are stored in uploads/backups directory</p>
                    <p><strong>Note:</strong> This will create a complete backup of your database including all tables and data.</p>
                </div>
            </div>

            <div class="backup-list">
                <h3>Backup History</h3>
                <div class="table-responsive">
                    <table class="backup-table">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentBackups) > 0): ?>
                                <?php foreach ($recentBackups as $backup): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($backup['file_url']); ?></td>
                                        <td><?php echo date('F j, Y g:i A', strtotime($backup['created_at'])); ?></td>
                                        <td class="action-buttons">
                                            <a href="?download=<?php echo urlencode($backup['file_url']); ?>" class="btn-download">
                                                <i class="fas fa-download"></i>
                                                Download
                                            </a>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                                <input type="hidden" name="delete_backup" value="<?php echo htmlspecialchars($backup['file_url']); ?>">
                                                <button type="submit" class="btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No backups found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>