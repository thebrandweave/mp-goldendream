<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "schemes";
$promoterUniqueID = $_SESSION['promoter_id'];

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

try {
    // Get all active schemes
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            (
                SELECT COUNT(*) 
                FROM Subscriptions sub 
                JOIN Customers c ON sub.CustomerID = c.CustomerID 
                WHERE sub.SchemeID = s.SchemeID 
                AND c.PromoterID = ? 
                AND sub.RenewalStatus = 'Active'
            ) as ActiveSubscribers
        FROM Schemes s 
        WHERE s.Status = 'Active'
        ORDER BY s.CreatedAt DESC
    ");
    $stmt->execute([$promoterUniqueID]);
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schemes | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            padding: 24px;
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            padding-top: calc(var(--topbar-height) + 24px) !important;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .page-description {
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        .schemes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .scheme-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }

        .scheme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .scheme-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .scheme-content {
            padding: 24px;
        }

        .scheme-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .scheme-description {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .scheme-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .scheme-price {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .scheme-subscribers {
            background: var(--primary-light);
            color: var(--primary-color);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .scheme-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            flex: 1;
            justify-content: center;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: rgb(11, 90, 68);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.2);
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-light);
            box-shadow: 0 4px 6px rgba(13, 106, 80, 0.1);
        }

        .search-filters {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-input {
            width: 100%;
            padding: 12px 16px;
            padding-left: 40px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 16px;
            }

            .page-header {
                padding: 20px;
            }

            .schemes-grid {
                grid-template-columns: 1fr;
            }

            .search-filters {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Available Schemes</h1>
               
            </div>

            <div class="schemes-grid">
                <?php foreach ($schemes as $scheme): ?>
                    <div class="scheme-card">
                        <img src="<?php echo !empty($scheme['SchemeImageURL']) ? '../../' . htmlspecialchars($scheme['SchemeImageURL']) : '../../uploads/schemes/default.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($scheme['SchemeName']); ?>" 
                             class="scheme-image">
                        <div class="scheme-content">
                            <h3 class="scheme-title"><?php echo htmlspecialchars($scheme['SchemeName']); ?></h3>
                            <p class="scheme-description"><?php echo htmlspecialchars($scheme['Description']); ?></p>
                            
                            <div class="scheme-details">
                                <div class="scheme-price">₹<?php echo number_format($scheme['MonthlyPayment'], 2); ?>/month</div>
                                <div class="scheme-subscribers">
                                    <i class="fas fa-users"></i>
                                    <?php echo $scheme['ActiveSubscribers']; ?> subscribers
                                </div>
                            </div>

                            <div class="scheme-actions">
                                <a href="view.php?id=<?php echo $scheme['SchemeID']; ?>" class="btn btn-outline">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                <a href="subscriptions.php?scheme_id=<?php echo $scheme['SchemeID']; ?>" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i>
                                    Add Customers
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($schemes)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 48px; background: white; border-radius: 16px; box-shadow: var(--card-shadow);">
                        <i class="fas fa-box-open" style="font-size: 48px; color: var(--text-secondary); margin-bottom: 16px;"></i>
                        <h3 style="color: var(--text-primary); margin-bottom: 8px;">No Schemes Available</h3>
                        <p style="color: var(--text-secondary);">There are no active schemes at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('schemeSearch');
            const schemeCards = document.querySelectorAll('.scheme-card');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();

                schemeCards.forEach(card => {
                    const title = card.querySelector('.scheme-title').textContent.toLowerCase();
                    const description = card.querySelector('.scheme-description').textContent.toLowerCase();

                    if (title.includes(searchTerm) || description.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            // Ensure proper topbar integration
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            adjustContent();
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html>