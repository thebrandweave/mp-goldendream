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

$message = '';
$messageType = '';
$showNotification = false;

// Get promoter details
try {
    $stmt = $conn->prepare("SELECT * FROM Promoters WHERE PromoterID = ?");
    $stmt->execute([$_SESSION['promoter_id']]);
    $promoter = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching promoter data";
    $messageType = "error";
}

// Handle welcome letter generation
if (isset($_POST['generate_letter'])) {
    try {
        // Generate unique filename
        $timestamp = time();
        $uniqueId = uniqid($timestamp . '_');
        $filename = 'welcome_letter_' . $uniqueId . '.html';
        
        // Create HTML content for the letter
        $html = '
        <div class="letter-container">
            <div class="letter-header">
                <img src="../../assets/images/logo.png" class="letter-logo">
                <h1>Welcome to Golden Dreams</h1>
            </div>
            <div class="letter-content">
                <p>Dear ' . htmlspecialchars($promoter['Name']) . ',</p>
                <p>We are pleased to welcome you to Golden Dreams as a Promoter. Your unique ID is: ' . htmlspecialchars($promoter['PromoterUniqueID']) . '</p>
                <p>As a valued member of our team, you are now part of a community dedicated to helping people achieve their financial dreams.</p>
                <p>Your journey with us begins today, and we are committed to supporting your success every step of the way.</p>
                <div class="letter-signature">
                    <p>Best regards,<br>Golden Dreams Team</p>
                </div>
            </div>
            <div class="letter-footer">
                <p>This is a computer-generated document. No signature is required.</p>
                <p>Generated on: ' . date('d M Y') . '</p>
            </div>
        </div>';

        // Save the HTML to a file
        file_put_contents('../../uploads/welcome-letters/' . $filename, $html);

       

        $message = "Welcome letter generated successfully!";
        $messageType = "success";
        $showNotification = true;

    } catch (Exception $e) {
        $message = "Error generating welcome letter: " . $e->getMessage();
        $messageType = "error";
        $showNotification = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome Letter | Golden Dreams</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(13, 106, 80);
            --primary-light: rgba(13, 106, 80, 0.1);
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --warning-color: #f1c40f;
            --border-color: #e0e0e0;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --bg-light: #f8f9fa;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
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

        .welcome-letter-container {
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

        .welcome-letter {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .letter-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #eee;
            position: relative;
            padding: 40px;
        }

        .letter-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }

        .letter-logo {
            max-width: 200px;
            margin-bottom: 20px;
        }

        .letter-content {
            margin: 20px 0;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .letter-signature {
            margin-top: 50px;
            font-style: italic;
        }

        .letter-footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 150px;
            color: rgba(13, 106, 80, 0.03);
            pointer-events: none;
            z-index: 1;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
        }

        .alert-error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
        }

        .info-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-top: 30px;
        }

        .info-section h3 {
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .info-section ul {
            margin-left: 20px;
            margin-bottom: 20px;
        }

        .info-section li {
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }

            .welcome-letter {
                padding: 20px;
            }

            .letter-container {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
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

            .welcome-letter, .welcome-letter * {
                visibility: visible;
            }

            .welcome-letter {
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
        <div class="welcome-letter-container">
            <div class="page-header">
                <h1 class="page-title">Welcome Letter</h1>
               
            </div>

            <?php if ($showNotification && $message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

           
                <div class="info-section">
                    <h3>About Your Welcome Letter</h3>
                    <p>Your welcome letter is an official document that confirms your association with Golden Dreams.</p>
                    
                    <h3>What's Included in Your Welcome Letter?</h3>
                    <ul>
                        <li>Your unique Promoter ID</li>
                        <li>Official welcome message from Golden Dreams</li>
                        <li>Date of generation</li>
                        <li>Digital signature</li>
                    </ul>

                    <h3>How to Use Your Welcome Letter</h3>
                    <ul>
                        <li>Keep it for your records</li>
                        <li>Use it as proof of your association with Golden Dreams</li>
                        <li>Share it with potential customers when needed</li>
                    </ul>
                </div>
          
        </div>
    </div>

    <script>
        function downloadWelcomeLetter() {
            // Create a filename with promoter ID and date
            const filename = 'GoldenDreams_WelcomeLetter_<?php echo $promoter['PromoterUniqueID']; ?>_' + 
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
                    .welcome-letter {
                        padding: 0;
                        box-shadow: none !important;
                    }
                }
            `;
            document.head.appendChild(style);

            // Print the welcome letter
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