<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if the logged-in admin has SuperAdmin privileges
if ($_SESSION['admin_role'] !== 'SuperAdmin') {
    $_SESSION['error_message'] = "You don't have permission to access WhatsApp settings.";
    header("Location: ../../dashboard/index.php");
    exit();
}

$menuPath = "../../";
$currentPage = "settings";

// Database connection
require_once("../../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get current settings
try {
    $stmt = $conn->query("SELECT * FROM WhatsAppAPIConfig ORDER BY ConfigID DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // If no settings exist, create a default record
    if (!$settings) {
        $stmt = $conn->prepare("INSERT INTO WhatsAppAPIConfig (APIProviderName, APIEndpoint, AccessToken, Token, InstanceID, Status) VALUES ('', '', '', '', '', 'Active')");
        $stmt->execute();
        $settings = [
            'ConfigID' => $conn->lastInsertId(),
            'APIProviderName' => '',
            'APIEndpoint' => '',
            'AccessToken' => '',
            'Token' => '',
            'InstanceID' => '',
            'Status' => 'Active'
        ];
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to fetch settings: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Update existing record
        $stmt = $conn->prepare("
            UPDATE WhatsAppAPIConfig SET 
                APIProviderName = :providerName,
                APIEndpoint = :endpoint,
                AccessToken = :accessToken,
                Token = :token,
                InstanceID = :instanceId,
                Status = :status
            WHERE ConfigID = :configId
        ");

        $params = [
            ':configId' => $settings['ConfigID'],
            ':providerName' => $_POST['provider_name'],
            ':endpoint' => $_POST['api_endpoint'],
            ':accessToken' => $_POST['access_token'],
            ':token' => $_POST['token'],
            ':instanceId' => $_POST['instance_id'],
            ':status' => $_POST['status']
        ];

        $stmt->execute($params);

        // Log the activity
        $action = "Updated WhatsApp API settings";
        $stmt = $conn->prepare("INSERT INTO ActivityLogs (UserID, UserType, Action, IPAddress) VALUES (?, 'Admin', ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $action, $_SERVER['REMOTE_ADDR']]);

        $conn->commit();
        $_SESSION['success_message'] = "WhatsApp API settings updated successfully.";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = "Failed to update settings: " . $e->getMessage();
    }

    header("Location: index.php");
    exit();
}

// Include header and sidebar
include("../../components/sidebar.php");
include("../../components/topbar.php");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp API Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .settings-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--ad_primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-medium);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--ad_primary-color);
            box-shadow: 0 0 0 3px rgba(58, 123, 213, 0.1);
            outline: none;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--ad_primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--ad_primary-hover);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
            transform: translateY(-2px);
        }

        .help-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
        }

        @media (max-width: 768px) {
            .settings-form {
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">WhatsApp API Settings</h1>
            <a href="../" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Settings
            </a>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <form class="settings-form" method="POST">
            <!-- API Configuration Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fab fa-whatsapp"></i>
                    WhatsApp Business API Configuration
                </h2>
                <div class="form-group">
                    <label for="provider_name">API Provider Name</label>
                    <input type="text" id="provider_name" name="provider_name" class="form-control"
                        value="<?php echo htmlspecialchars($settings['APIProviderName'] ?? ''); ?>" required>
                    <p class="help-text">Enter the name of your WhatsApp Business API provider</p>
                </div>

                <div class="form-group">
                    <label for="api_endpoint">API Endpoint</label>
                    <input type="url" id="api_endpoint" name="api_endpoint" class="form-control"
                        value="<?php echo htmlspecialchars($settings['APIEndpoint'] ?? ''); ?>" required>
                    <p class="help-text">The base URL for the WhatsApp API service</p>
                </div>
            </div>

            <!-- Authentication Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-key"></i>
                    Authentication Details
                </h2>
                <div class="form-group">
                    <label for="access_token">Access Token</label>
                    <input type="password" id="access_token" name="access_token" class="form-control"
                        value="<?php echo htmlspecialchars($settings['AccessToken'] ?? ''); ?>" required>
                    <p class="help-text">Your WhatsApp Business API access token</p>
                </div>

                <div class="form-group">
                    <label for="token">API Token</label>
                    <input type="password" id="token" name="token" class="form-control"
                        value="<?php echo htmlspecialchars($settings['Token'] ?? ''); ?>" required>
                    <p class="help-text">Your WhatsApp Business API authentication token</p>
                </div>

                <div class="form-group">
                    <label for="instance_id">Instance ID</label>
                    <input type="text" id="instance_id" name="instance_id" class="form-control"
                        value="<?php echo htmlspecialchars($settings['InstanceID'] ?? ''); ?>" required>
                    <p class="help-text">Your unique WhatsApp Business API instance identifier</p>
                </div>
            </div>

            <!-- Status Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-toggle-on"></i>
                    API Status
                </h2>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="Active" <?php echo ($settings['Status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($settings['Status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <p class="help-text">Enable or disable the WhatsApp API integration</p>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='../'">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <script>
        // Add fade-out effect for alerts
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                });
            }, 3000);
        });
    </script>
</body>

</html>