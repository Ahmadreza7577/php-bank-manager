
<?php

session_start();

require_once "config/database.php";
require_once "csrf.php";

// Only logged-in users can access the dashboard.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$username = $_SESSION["username"] ?? "User";

$message = "";
$messageType = "";

// Read a one-time success message.
if (isset($_SESSION["flash_success"])) {
    $message = $_SESSION["flash_success"];
    $messageType = "success";
    unset($_SESSION["flash_success"]);
}

// Load the user's bank account.
$sql = "SELECT id, account_number, balance
        FROM accounts
        WHERE user_id = :user_id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([":user_id" => $userId]);
$account = $stmt->fetch();

// Create a bank account if the user does not have one.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page.");
    }

    if (!$account) {
        try {
            $accountNumber = "10" . random_int(10000000, 99999999);

            $sql = "INSERT INTO accounts
                    (user_id, account_number, balance)
                    VALUES
                    (:user_id, :account_number, 0.00)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ":user_id" => $userId,
                ":account_number" => $accountNumber
            ]);

            $_SESSION["flash_success"] =
                "Your bank account was created successfully!";

            header("Location: dashboard.php");
            exit;

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not create your bank account. Please try again.";
            $messageType = "error";
        }
    }
}

// Refresh the account data after processing the request.
$stmt = $pdo->prepare(
    "SELECT id, account_number, balance
     FROM accounts
     WHERE user_id = :user_id
     LIMIT 1"
);
$stmt->execute([":user_id" => $userId]);
$account = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .sidebar a.active {
            background: #2563eb;
            color: #fff;
        }

        .dashboard-message {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
        }

        .dashboard-message.success {
            background: #dcfce7;
            color: #166534;
        }

        .dashboard-message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .account-card {
            max-width: 760px;
        }

        .account-number {
            margin-top: 18px;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .actions .button {
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }

        .button.transfer {
            background: #0f766e;
            color: #fff;
        }

        .button.transfer:hover {
            background: #115e59;
        }

        .button.transactions {
            background: #475569;
            color: #fff;
        }

        .welcome-text {
            overflow-wrap: anywhere;
        }
    </style>
</head>

<body>

<div class="dashboard">

    <!-- Sidebar navigation -->
    
<!-- Sidebar navigation -->
<aside class="sidebar">

    <h2>🏦 Bank Manager</h2>

    <a href="dashboard.php" class="active">
        🏠 Dashboard
    </a>

    <a href="profile.php">
        👤 My Profile
    </a>

    <a href="deposit.php">
        ➕ Deposit
    </a>

    <a href="withdraw.php">
        ➖ Withdraw
    </a>

    <a href="transfer.php">
        💸 Transfer Money
    </a>

    <a href="transactions.php">
        📋 Transactions
    </a>

    <a href="logout.php">
        🚪 Logout
    </a>

</aside>



    <!-- Main content -->
    <main class="content">

        <div class="header">

            <h1>Dashboard</h1>

            <span class="welcome-text">
                Welcome,
                <strong>
                    <?= htmlspecialchars(
                        $username,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </strong>
            </span>

        </div>


        <?php if ($message !== ""): ?>

            <div class="dashboard-message <?= $messageType ?>">
                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <?php if (!$account): ?>

            <!-- Create account card -->
            <div class="balance-card account-card">

                <h2>Create Your Bank Account</h2>

                <p>
                    You don't have a bank account yet.
                    Create one to start using the application.
                </p>

                <form method="POST" action="dashboard.php">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            generateCsrfToken(),
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <button class="button" type="submit">
                        Create Bank Account
                    </button>

                </form>

            </div>

        <?php else: ?>

            <!-- Account balance card -->
            <div class="balance-card account-card">

                <p>Current Balance</p>

                <div class="balance">
                    €<?= number_format(
                        (float) $account["balance"],
                        2,
                        ".",
                        ","
                    ) ?>
                </div>

                <p class="account-number">
                    <strong>Account Number:</strong>
                    <?= htmlspecialchars(
                        $account["account_number"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <!-- Quick actions -->
                <div class="actions">

                    <a class="button" href="deposit.php">
                        + Deposit
                    </a>

                    <a class="button withdraw" href="withdraw.php">
                        − Withdraw
                    </a>

                    <a class="button transfer" href="transfer.php">
                        💸 Transfer Money
                    </a>

                    <a class="button transactions" href="transactions.php">
                        View Transactions
                    </a>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>
