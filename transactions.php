
<?php

session_start();

require_once "config/database.php";

// Require login.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// Get the user's account.
$stmt = $pdo->prepare(
    "SELECT id, account_number, balance
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

// Allowed filters.
$allowedFilters = [
    "all",
    "deposit",
    "withdraw",
    "sent",
    "received"
];

$filter = $_GET["filter"] ?? "all";

if (!is_string($filter) || !in_array($filter, $allowedFilters, true)) {
    $filter = "all";
}

// Load transactions belonging to this account only.
$sql = "SELECT id, type, amount, description, created_at
        FROM transactions
        WHERE account_id = :account_id";

$params = [
    ":account_id" => $account["id"]
];

if ($filter === "deposit") {
    $sql .= " AND type = 'deposit'";
} elseif ($filter === "withdraw") {
    $sql .= " AND type = 'withdraw'";
} elseif ($filter === "sent") {
    $sql .= " AND type = 'transfer'
              AND description LIKE 'Transfer to %'";
} elseif ($filter === "received") {
    $sql .= " AND type = 'transfer'
              AND description LIKE 'Transfer from %'";
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Format transaction labels.
function transactionDetails(array $transaction): array
{
    $type = $transaction["type"];
    $description = $transaction["description"] ?? "";

    if ($type === "deposit") {
        return [
            "label" => "Deposit",
            "class" => "deposit",
            "sign" => "+"
        ];
    }

    if ($type === "withdraw") {
        return [
            "label" => "Withdrawal",
            "class" => "withdraw",
            "sign" => "-"
        ];
    }

    if ($type === "transfer" && str_starts_with($description, "Transfer to ")) {
        return [
            "label" => "Money Sent",
            "class" => "sent",
            "sign" => "-"
        ];
    }

    if ($type === "transfer" && str_starts_with($description, "Transfer from ")) {
        return [
            "label" => "Money Received",
            "class" => "received",
            "sign" => "+"
        ];
    }

    return [
        "label" => "Transfer",
        "class" => "transfer",
        "sign" => ""
    ];
}

$filterLabels = [
    "all" => "All Transactions",
    "deposit" => "Deposits",
    "withdraw" => "Withdrawals",
    "sent" => "Money Sent",
    "received" => "Money Received"
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Transaction History | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .transactions-container {
            max-width: 1150px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(15, 23, 42, .08);
        }

        .transactions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
        }

        .transactions-header h1 {
            margin: 0;
            color: #1e293b;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }

        .account-info {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 26px;
        }

        .info-card {
            flex: 1;
            min-width: 220px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .info-card p {
            margin: 0 0 8px;
            color: #64748b;
            font-size: 14px;
        }

        .info-card strong {
            color: #0f172a;
            overflow-wrap: anywhere;
        }

        .balance-value {
            font-size: 25px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .filter-link {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            color: #334155;
            text-decoration: none;
            background: #fff;
            font-size: 14px;
        }

        .filter-link:hover {
            background: #f1f5f9;
        }

        .filter-link.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .transactions-table th,
        .transactions-table td {
            padding: 15px 13px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .transactions-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .transactions-table tbody tr:hover {
            background: #f8fafc;
        }

        .transaction-type {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .type-deposit,
        .type-received {
            background: #dcfce7;
            color: #166534;
        }

        .type-withdraw,
        .type-sent {
            background: #fee2e2;
            color: #991b1b;
        }

        .type-transfer {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .amount-positive {
            color: #15803d;
            font-weight: 700;
        }

        .amount-negative {
            color: #b91c1c;
            font-weight: 700;
        }

        .description-cell {
            white-space: normal !important;
            min-width: 180px;
            overflow-wrap: anywhere;
        }

        .empty-state {
            padding: 45px 20px;
            text-align: center;
            background: #f8fafc;
            border-radius: 12px;
            color: #64748b;
        }

        .empty-state h3 {
            color: #334155;
        }

        .back-button {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 9px;
        }

        .back-button:hover {
            background: #1d4ed8;
        }

        .results-count {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .transactions-container {
                margin: 15px;
                padding: 18px;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 11px;
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

<main class="transactions-container">

    <div class="transactions-header">
        <div>
            <h1>Transaction History</h1>
            <p class="subtitle">Review your account activity.</p>
        </div>

        <a href="dashboard.php" class="back-button">
            ← Back to Dashboard
        </a>
    </div>

    <div class="account-info">

        <div class="info-card">
            <p>Account Number</p>
            <strong>
                <?= htmlspecialchars(
                    $account["account_number"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>
        </div>

        <div class="info-card">
            <p>Current Balance</p>
            <strong class="balance-value">
                €<?= number_format((float) $account["balance"], 2) ?>
            </strong>
        </div>

    </div>

    <h2><?= htmlspecialchars($filterLabels[$filter], ENT_QUOTES, "UTF-8") ?></h2>

    <nav class="filters" aria-label="Transaction filters">

        <?php foreach ($filterLabels as $key => $label): ?>

            <a
                class="filter-link <?= $filter === $key ? "active" : "" ?>"
                href="transactions.php?filter=<?= urlencode($key) ?>"
                <?= $filter === $key ? 'aria-current="page"' : "" ?>
            >
                <?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?>
            </a>

        <?php endforeach; ?>

    </nav>

    <p class="results-count">
        <?= count($transactions) ?>
        transaction(s) found
    </p>

    <?php if (empty($transactions)): ?>

        <div class="empty-state">
            <h3>No transactions found</h3>
            <p>Try another filter or make your first transaction.</p>
        </div>

    <?php else: ?>

        <div class="table-wrapper">

            <table class="transactions-table">

                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($transactions as $transaction): ?>

                        <?php
                        $details = transactionDetails($transaction);
                        $amount = (float) $transaction["amount"];
                        ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $transaction["created_at"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>
                                <span class="transaction-type type-<?= htmlspecialchars(
                                    $details["class"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>">
                                    <?= htmlspecialchars(
                                        $details["label"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </span>
                            </td>

                            <td class="<?= $details["sign"] === "+"
                                ? "amount-positive"
                                : ($details["sign"] === "-"
                                    ? "amount-negative"
                                    : "") ?>">

                                <?= htmlspecialchars($details["sign"], ENT_QUOTES, "UTF-8") ?>€<?= number_format($amount, 2) ?>

                            </td>

                            <td class="description-cell">
                                <?= htmlspecialchars(
                                    $transaction["description"] ?? "—",
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</main>

</body>
</html>