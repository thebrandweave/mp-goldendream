<?php
require_once("./config/config.php");

function fetchPromotersOfCustomer($customerUniqueID, $conn)
{
    $promoters = [];

    try {
        // Start with the customer's promoter
        $stmt = $conn->prepare("SELECT PromoterID FROM Customers WHERE CustomerUniqueID = ?");
        $stmt->execute([$customerUniqueID]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            throw new Exception("No promoter found for the customer.");
        }

        $currentPromoterID = $customer['PromoterID'];
        error_log("Customer PromoterID: " . $currentPromoterID);

        while ($currentPromoterID) {
            // Fetch promoter details
            $stmt = $conn->prepare("SELECT PromoterID, PromoterUniqueID, ParentPromoterID, Commission, ParentCommission FROM Promoters WHERE PromoterUniqueID = ?");
            $stmt->execute([$currentPromoterID]);
            $promoter = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$promoter) {
                error_log("No promoter found for PromoterID: " . $currentPromoterID);
                break;
            }

            // Add promoter to the list
            $promoters[] = $promoter;
            error_log("Fetched Promoter: " . print_r($promoter, true));

            // Move to the parent promoter
            $currentPromoterID = $promoter['ParentPromoterID'];
        }
    } catch (Exception $e) {
        error_log("Error fetching promoters: " . $e->getMessage());
    }

    return $promoters;
}

function convertCommissionToInt($commission)
{
    // Remove any non-numeric characters and convert to integer
    return intval(preg_replace('/[^0-9]/', '', $commission));
}

function updatePromoterWallet($promoters, $conn, $paymentAmount)
{
    // First, update the direct promoter's wallet with their commission
    $directPromoter = $promoters[0];
    $commission = convertCommissionToInt($directPromoter['Commission']);
    $commissionAmount = $commission;

    // Update direct promoter's wallet
    $stmt = $conn->prepare("SELECT BalanceID FROM PromoterWallet WHERE PromoterUniqueID = ?");
    $stmt->execute([$directPromoter['PromoterUniqueID']]);
    $walletRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($walletRecord) {
        $stmt = $conn->prepare("UPDATE PromoterWallet SET BalanceAmount = BalanceAmount + ?, LastUpdated = CURRENT_TIMESTAMP WHERE PromoterUniqueID = ?");
        $stmt->execute([$commissionAmount, $directPromoter['PromoterUniqueID']]);
        echo "Updated direct promoter wallet for PromoterUniqueID: " . $directPromoter['PromoterUniqueID'] . " with amount: " . $commissionAmount . "<br>";
    } else {
        $stmt = $conn->prepare("INSERT INTO PromoterWallet (UserID, PromoterUniqueID, BalanceAmount, Message) VALUES (?, ?, ?, ?)");
        $message = "Commission from payment";
        $stmt->execute([$directPromoter['PromoterID'], $directPromoter['PromoterUniqueID'], $commissionAmount, $message]);
        echo "Created new direct promoter wallet for PromoterUniqueID: " . $directPromoter['PromoterUniqueID'] . " with amount: " . $commissionAmount . "<br>";
    }

    // Then, update each parent's wallet with their ParentCommission from their child
    for ($i = 0; $i < count($promoters) - 1; $i++) {
        $currentPromoter = $promoters[$i];
        $parentPromoter = $promoters[$i + 1];

        if (!empty($currentPromoter['ParentCommission'])) {
            $parentCommission = convertCommissionToInt($currentPromoter['ParentCommission']);
            $parentCommissionAmount = $parentCommission;

            $stmt = $conn->prepare("SELECT BalanceID FROM PromoterWallet WHERE PromoterUniqueID = ?");
            $stmt->execute([$parentPromoter['PromoterUniqueID']]);
            $parentWalletRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parentWalletRecord) {
                $stmt = $conn->prepare("UPDATE PromoterWallet SET BalanceAmount = BalanceAmount + ?, LastUpdated = CURRENT_TIMESTAMP WHERE PromoterUniqueID = ?");
                $stmt->execute([$parentCommissionAmount, $parentPromoter['PromoterUniqueID']]);
                echo "Updated parent wallet for PromoterUniqueID: " . $parentPromoter['PromoterUniqueID'] . " with amount: " . $parentCommissionAmount . "<br>";
            } else {
                $stmt = $conn->prepare("INSERT INTO PromoterWallet (UserID, PromoterUniqueID, BalanceAmount, Message) VALUES (?, ?, ?, ?)");
                $message = "Parent commission from payment";
                $stmt->execute([$parentPromoter['PromoterID'], $parentPromoter['PromoterUniqueID'], $parentCommissionAmount, $message]);
                echo "Created new parent wallet for PromoterUniqueID: " . $parentPromoter['PromoterUniqueID'] . " with amount: " . $parentCommissionAmount . "<br>";
            }
        }
    }
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        $conn->beginTransaction();

        // Get customer unique ID from POST data
        $customerUniqueID = $_POST['customer_unique_id'] ?? '';
        if (empty($customerUniqueID)) {
            throw new Exception("Customer unique ID is required");
        }

        $paymentAmount = 1000; // Example payment amount
        $promoters = fetchPromotersOfCustomer($customerUniqueID, $conn);

        echo "<h1>Promoter Hierarchy and Commissions for Customer $customerUniqueID</h1>";
        if (empty($promoters)) {
            echo "<p>No promoters found for this customer.</p>";
        } else {
            echo "<ul>";
            foreach ($promoters as $promoter) {
                echo "<li>Promoter ID: " . $promoter['PromoterID'] . ", Unique ID: " . $promoter['PromoterUniqueID'] . ", Commission: " . $promoter['Commission'] . ", Parent Commission: " . $promoter['ParentCommission'] . "</li>";
            }
            echo "</ul>";

            // Update promoter wallets
            updatePromoterWallet($promoters, $conn, $paymentAmount);
        }

        $conn->commit();
        echo "<p>Promoter fetching and wallet update completed successfully.</p>";
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error during promoter fetching or wallet update: " . $e->getMessage());
        echo "<p>Error during promoter fetching or wallet update: " . $e->getMessage() . "</p>";
    }
} else {
    // If not a POST request, show a form to submit the customer unique ID
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Promoter Commission Calculator</title>
    </head>

    <body>
        <h1>Enter Customer Unique ID</h1>
        <form method="POST">
            <input type="text" name="customer_unique_id" placeholder="Enter Customer Unique ID" required>
            <button type="submit">Calculate Commissions</button>
        </form>
    </body>

    </html>
<?php
}
?>