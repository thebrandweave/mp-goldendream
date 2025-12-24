<?php
session_start();

// Check if promoter is logged in
if (!isset($_SESSION['promoter_id'])) {
    header("Location: ../login.php");
    exit();
}

$menuPath = "../";
$currentPage = "profile";

// Database connection
require_once("../../config/config.php");
$database = new Database();
$conn = $database->getConnection();

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching promoter data";
    $messageType = "error";
}

// Define image paths
$uploadDir = '../../uploads/profile/';
$defaultImagePath = '../../uploads/profile/image.png';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --border-color: #2a3942;
            --text-primary: #ecf0f1;
            --text-secondary: #bdc3c7;
            --bg-dark: #1a1f24;
            --bg-darker: #141a1f;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Poppins', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .content-wrapper {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            padding-top: calc(var(--topbar-height) + 20px) !important;
        }

        .id-card-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .download-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 106, 80, 0.2);
            border: none;
            cursor: pointer;
        }

        .download-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 106, 80, 0.3);
        }

        .id-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .id-card-inner {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--bg-dark) 0%, var(--bg-darker) 100%);
            border-radius: 15px;
            padding: 30px;
            border: 2px solid var(--border-color);
            position: relative;
            color: var(--text-primary);
        }

        .id-card-header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
        }

        .company-logo {
            max-width: 150px;
            margin-bottom: 15px;
        }

        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .card-title {
            font-size: 14px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .id-card-body {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 30px;
            align-items: start;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            object-fit: cover;
            border: 4px solid var(--border-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .promoter-details {
            display: grid;
            gap: 15px;
        }

        .detail-group {
            display: grid;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 15px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .id-card-footer {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .validity {
            text-align: right;
        }

        .valid-from {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .valid-till {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 150px;
            color: rgba(255, 255, 255, 0.03);
            pointer-events: none;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .id-card {
                padding: 20px;
            }

            .id-card-inner {
                padding: 20px;
            }

            .id-card-body {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .profile-photo {
                margin: 0 auto;
            }

            .promoter-details {
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .profile-photo {
                width: 120px;
                height: 120px;
            }

            .company-name {
                font-size: 20px;
            }
        }

        @media print {
            @page {
                size: auto;
                margin: 0mm;
            }

            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            body * {
                visibility: hidden;
            }

            .id-card, .id-card * {
                visibility: visible;
            }

            .id-card {
                position: fixed;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                margin: 0;
                padding: 0;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page-header, .download-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <?php include('../components/sidebar.php'); ?>
    <?php include('../components/topbar.php'); ?>

    <div class="content-wrapper">
        <div class="id-card-container">
            <div class="page-header">
                <h1 class="page-title">Promoter ID Card</h1>
                <button class="download-btn" onclick="downloadIDCard()">
                    <i class="fas fa-download"></i>
                    Download ID Card
                </button>
            </div>

            <div class="id-card">
                <div class="watermark">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="id-card-inner">
                    <div class="id-card-header">
                        <img src="../assets/images/logo.png" alt="Golden Dreams Logo" class="company-logo">
                        <div class="company-name">Golden Dreams</div>
                        <div class="card-title">Authorized Promoter</div>
                    </div>

                    <div class="id-card-body">
                        <img src="<?php 
                            if ($promoter['ProfileImageURL'] && file_exists($uploadDir . $promoter['ProfileImageURL'])) {
                                echo $uploadDir . $promoter['ProfileImageURL'];
                            } else {
                                echo $defaultImagePath;
                            }
                        ?>" alt="Profile Photo" class="profile-photo">

                        <div class="promoter-details">
                            <div class="detail-group">
                                <div class="detail-label">Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($promoter['Name']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label">Promoter ID</div>
                                <div class="detail-value"><?php echo htmlspecialchars($promoter['PromoterUniqueID']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label">Contact</div>
                                <div class="detail-value"><?php echo htmlspecialchars($promoter['Contact']); ?></div>
                            </div>

                            <div class="detail-group">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?php echo htmlspecialchars($promoter['Email']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="id-card-footer">
                        <div class="validity">
                            <div class="valid-from">Valid From: <?php 
                                $createdDate = new DateTime($promoter['CreatedAt']);
                                echo $createdDate->format('d M Y'); 
                            ?></div>
                            <div class="valid-till">Valid Till: <?php 
                                $validTill = clone $createdDate;
                                $validTill->modify('+1 year');
                                echo $validTill->format('d M Y'); 
                            ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadIDCard() {
            // Create a filename with promoter ID and date
            const filename = 'GoldenDreams_IDCard_<?php echo $promoter['PromoterUniqueID']; ?>_' + 
                           new Date().toISOString().slice(0,10) + '.pdf';

            // Add temporary print styles
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    @page {
                        size: auto;
                        margin: 0mm;
                    }
                    body {
                        background: white !important;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .id-card {
                        padding: 0;
                        box-shadow: none !important;
                    }
                }
            `;
            document.head.appendChild(style);

            // Print the ID card
            window.print();

            // Remove temporary styles after printing
            setTimeout(() => {
                document.head.removeChild(style);
            }, 100);
        }

        // Ensure proper topbar integration
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const content = document.querySelector('.content-wrapper');

            function adjustContent() {
                if (sidebar.classList.contains('collapsed')) {
                    content.style.marginLeft = 'var(--sidebar-collapsed-width)';
                } else {
                    content.style.marginLeft = 'var(--sidebar-width)';
                }
            }

            // Initial adjustment
            adjustContent();

            // Watch for sidebar changes
            const observer = new MutationObserver(adjustContent);
            observer.observe(sidebar, { attributes: true });
        });
    </script>
</body>
</html> 