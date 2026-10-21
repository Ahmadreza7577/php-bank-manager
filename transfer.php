
<?php

session_start();

require_once "config/database.php";
require_once "csrf.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$error = "";

if (isset($_SESSION["flash_success"])) {
    $success = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
} else {
    $success = "";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page.");
    }

    $destinationNumber = trim($_POST["account_number"] ?? "");
    $amountInput = trim($_POST["amount"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($destinationNumber === "" || $amountInput === "") {
        $error = "Please fill in all required fields.";
    } elseif (!preg_match('/^\d{10,20}$/', $destinationNumber)) {
        $error = "Please enter a valid account number.";
    } elseif (!is_numeric($amountInput) || !preg_match('/^\d+(\.\d{1,2})?$/', $amountInput)) {
        $error = "Please enter a valid amount with up to 2 decimal places.";
    } elseif ((float) $amountInput <= 0 || (float) $amountInput > 1000000000) {
        $error = "The transfer amount is outside the allowed range.";
    } elseif (strlen($description) > 255) {
        $error = "Description must not exceed 255 characters.";
    } else {

        $amount = number_format((float) $amountInput, 2, '.', '');

        try {
            $pdo->beginTransaction();

            // Lock and retrieve the sender's account.
            $stmt = $pdo->prepare(
                "SELECT id, account_number, balance
                 FROM accounts
                 WHERE user_id = :user_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([":user_id" => $userId]);
            $sender = $stmt->fetch();

            if (!$sender) {
                throw new RuntimeException("Your bank account was not found.");
            }

            // Find the destination account.
            $stmt = $pdo->prepare(
                "SELECT id, user_id, account_number
                 FROM accounts
                 WHERE account_number = :account_number
                 LIMIT 2"
            );
            $stmt->execute([
                ":account_number" => $destinationNumber
            ]);
            $destinations = $stmt->fetchAll();

            if (count($destinations) !== 1) {
                throw new RuntimeException(
                    "Destination account was not found or is not unique."
                );
            }

            $destination = $destinations[0];

            if ((int) $destination["id"] === (int) $sender["id"]) {
                throw new RuntimeException(
                    "You cannot transfer money to your own account."
                );
            }

            // Lock the destination account before changing its balance.
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM accounts
                 WHERE id = :id
                 FOR UPDATE"
            );
            $stmt->execute([":id" => $destination["id"]]);

            if (!$stmt->fetch()) {
                throw new RuntimeException("Destination account is unavailable.");
            }

            // Subtract only if the sender has sufficient funds.
            $stmt = $pdo->prepare(
                "UPDATE accounts
                 SET balance = balance - :amount
                 WHERE id = :id AND balance >= :minimum"
            );
            $stmt->execute([
                ":amount" => $amount,
                ":id" => $sender["id"],
                ":minimum" => $amount
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException("Insufficient balance.");
            }

            // Add the money to the destination account.
            $stmt = $pdo->prepare(
                "UPDATE accounts
                 SET balance = balance + :amount
                 WHERE id = :id"
            );
            $stmt->execute([
                ":amount" => $amount,
                ":id" => $destination["id"]
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException("Could not credit destination account.");
            }

            // Record both sides of the transfer.
            $senderDescription = "Transfer to " . $destinationNumber;
            if ($description !== "") {
                $senderDescription .= " - " . $description;
            }

            $receiverDescription = "Transfer from " . $sender["account_number"];
            if ($description !== "") {
                $receiverDescription .= " - " . $description;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO transactions
                 (account_id, type, amount, description)
                 VALUES (:account_id, 'transfer', :amount, :description)"
            );

            $stmt->execute([
                ":account_id" => $sender["id"],
                ":amount" => $amount,
                ":description" => $senderDescription
            ]);

            $stmt->execute([
                ":account_id" => $destination["id"],
                ":amount" => $amount,
                ":description" => $receiverDescription
            ]);

            $pdo->commit();

            $_SESSION["flash_success"] = "Transfer completed successfully!";
            header("Location: transfer.php");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof PDOException) {
                error_log($e->getMessage());
                $error = "Transfer failed due to a database error.";
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

// Load the current account details.
$stmt = $pdo->prepare(
    "SELECT account_number, balance
     FROM accounts
     WHERE user_id = :user_id
     LIMIT 1"
);
$stmt->execute([":user_id" => $userId]);
$account = $stmt->fetch();

if (!$account) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Money | PHP Bank Manager</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .transfer-container {
            max-width: 520px;
            margin: 40px auto;
            padding: 28px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .transfer-container h1 { color: #1e293b; margin-top: 0; }
        .account-info {
            background: #eff6ff;
            padding: 14px;
            border-radius: 8px;
            margin: 18px 0;
            color: #1e40af;
        }
        .transfer-container label {
            display: block;
            margin: 16px 0 8px;
            font-weight: bold;
            color: #334155;
        }
        .transfer-container input,
        .transfer-container textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }
        .transfer-container button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin: 16px 0;
        }
        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }
        .back-link {
            display: inline-block;
            margin-top: 18px;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="transfer-container">
        <h1>Transfer Money</h1>
        <p>Send money to another bank account.</p>

        <div class="account-info">
            <strong>Your account:</strong>
            <?= htmlspecialchars($account["account_number"], ENT_QUOTES, "UTF-8") ?>
            <br>
            <strong>Available balance:</strong>
            <?= number_format((float) $account["balance"], 2) ?>
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="message success">
                <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="transfer.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="account_number">Destination Account Number</label>
            <input
                type="text"
                id="account_number"
                name="account_number"
                maxlength="20"
                inputmode="numeric"
                pattern="[0-9]{10,20}"
                required
            >

            <label for="amount">Amount</label>
            <input
                type="number"
                id="amount"
                name="amount"
                min="0.01"
                max="1000000000"
                step="0.01"
                required
            >

            <label for="description">Description (optional)</label>
            <textarea
                id="description"
                name="description"
                maxlength="255"
                rows="3"
            ></textarea>

            <button type="submit">Transfer Money</button>
        </form>

        <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
    </main>
</body>
</html>
