<?php

session_start();

require_once "csrf.php";

require_once "config/database.php";

// Check whether the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$error = "";
$success = "";

// Get the user's bank account
$sql = "SELECT id, balance
        FROM accounts
        WHERE user_id = :user_id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":user_id" => $userId
]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("Bank account not found.");
}

// Process withdrawal
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page and try again.");
    }

    $amountInput = trim($_POST["amount"] ?? "");;

    // Validate the amount
    if (
        $amountInput === "" ||
        !is_numeric($amountInput) ||
        !is_finite((float) $amountInput) ||
        (float) $amountInput <= 0
    ) {
        $error = "Please enter a valid amount greater than zero.";
    } else {

        $amount = round((float) $amountInput, 2);

        if ($amount <= 0) {
            $error = "The amount must be greater than zero.";
        } else {

            try {
                $pdo->beginTransaction();

                // Lock the account row while checking and updating balance
                $sql = "SELECT balance
                        FROM accounts
                        WHERE id = :account_id
                          AND user_id = :user_id
                        FOR UPDATE";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ":account_id" => $account["id"],
                    ":user_id" => $userId
                ]);

                $lockedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lockedAccount) {
                    throw new RuntimeException("Bank account not found.");
                }

                $currentBalance = (float) $lockedAccount["balance"];

                if ($amount > $currentBalance) {

                    $pdo->rollBack();

                    $error = "Insufficient balance. Your current balance is €"
                           . number_format($currentBalance, 2) . ".";

                } else {

                    // Update balance
                    $sql = "UPDATE accounts
                            SET balance = balance - :amount
                            WHERE id = :account_id
                              AND user_id = :user_id";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ":amount" => number_format($amount, 2, ".", ""),
                        ":account_id" => $account["id"],
                        ":user_id" => $userId
                    ]);

                    // Record the transaction
                    $sql = "INSERT INTO transactions
                            (account_id, type, amount, description)
                            VALUES
                            (:account_id, 'withdraw', :amount, :description)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ":account_id" => $account["id"],
                        ":amount" => number_format($amount, 2, ".", ""),
                        ":description" => "Cash withdrawal"
                    ]);

                    $pdo->commit();

                    $_SESSION["flash_success"] =
                        "Withdrawal completed successfully. €"
                        . number_format($amount, 2)
                        . " withdrawn.";

                    header("Location: withdraw.php");
                    exit;
                }
            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                $error = "The withdrawal could not be completed. Please try again.";
            }
        }
    }
}

// Get the latest balance
$stmt = $pdo->prepare(
    "SELECT balance FROM accounts WHERE id = :account_id AND user_id = :user_id"
);

$stmt->execute([
    ":account_id" => $account["id"],
    ":user_id" => $userId
]);

$latestAccount = $stmt->fetch(PDO::FETCH_ASSOC);

$balance = (float) $latestAccount["balance"];

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Withdraw Money | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .form-container h1 {
            margin-top: 0;
            color: #1e293b;
        }

        .current-balance {
            margin: 20px 0;
            padding: 16px;
            background: #f1f5f9;
            border-radius: 10px;
            color: #334155;
        }

        .current-balance strong {
            color: #0f172a;
            font-size: 22px;
        }

        .form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .form-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 16px;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .form-container button:hover {
            background: #b91c1c;
        }

        .message {
            padding: 12px;
            margin-bottom: 18px;
            border-radius: 8px;
        }

        .error {
            color: #991b1b;
            background: #fee2e2;
        }

        .success {
            color: #166534;
            background: #dcfce7;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="form-container">

        <h1>Withdraw Money</h1>

        <div class="current-balance">
            Current Balance:
            <br>
            <strong>€<?= number_format($balance, 2) ?></strong>
        </div>

        <?php if ($error !== ""): ?>
            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php
        $success = $_SESSION["flash_success"] ?? "";
        unset($_SESSION["flash_success"]);
        ?>

        <?php if ($success !== ""): ?>
            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="withdraw.php">
            
            //<input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="amount">Amount (€)</label>

            <input
                type="number"
                id="amount"
                name="amount"
                min="0.01"
                step="0.01"
                placeholder="Enter amount"
                required
            >

            <button type="submit">Withdraw Money</button>

        </form>

        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

    </div>

</body>
</html>