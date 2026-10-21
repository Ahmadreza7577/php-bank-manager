
<?php

session_start();

require_once "config/database.php";
require_once "csrf.php";

// Only logged-in users can access this page.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$message = "";
$messageType = "";

// Handle password change.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page.");
    }

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        !is_string($currentPassword) ||
        !is_string($newPassword) ||
        !is_string($confirmPassword)
    ) {
        $message = "Invalid form data.";
        $messageType = "error";

    } elseif (
        $currentPassword === "" ||
        $newPassword === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please fill in all password fields.";
        $messageType = "error";

    } elseif (strlen($newPassword) < 8) {
        $message = "Your new password must contain at least 8 characters.";
        $messageType = "error";

    } elseif (strlen($newPassword) > 4096) {
        $message = "Your new password is too long.";
        $messageType = "error";

    } elseif ($newPassword !== $confirmPassword) {
        $message = "The new passwords do not match.";
        $messageType = "error";

    } elseif ($currentPassword === $newPassword) {
        $message = "Your new password must be different from the current password.";
        $messageType = "error";

    } else {
        try {
            // Get the current password hash.
            $stmt = $pdo->prepare(
                "SELECT password
                 FROM users
                 WHERE id = :id
                 LIMIT 1"
            );

            $stmt->execute([":id" => $userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user["password"])) {
                $message = "Your current password is incorrect.";
                $messageType = "error";

            } else {
                // Hash the new password before saving it.
                $newPasswordHash = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    "UPDATE users
                     SET password = :password
                     WHERE id = :id"
                );

                $stmt->execute([
                    ":password" => $newPasswordHash,
                    ":id" => $userId
                ]);

                // Rotate the session ID after a sensitive operation.
                session_regenerate_id(true);

                $_SESSION["flash_success"] =
                    "Your password has been changed successfully.";

                header("Location: profile.php");
                exit;
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = "Could not change your password. Please try again.";
            $messageType = "error";
        }
    }
}

// Load profile details.
$stmt = $pdo->prepare(
    "SELECT username, email, created_at
     FROM users
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute([":id" => $userId]);
$profile = $stmt->fetch();

if (!$profile) {
    $_SESSION = [];
    session_destroy();

    header("Location: login.php");
    exit;
}

// Display a one-time success message.
if (isset($_SESSION["flash_success"])) {
    $message = $_SESSION["flash_success"];
    $messageType = "success";
    unset($_SESSION["flash_success"]);
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .profile-container {
            max-width: 850px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(15, 23, 42, .08);
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 25px;
        }

        .profile-header h1 {
            margin: 0;
            color: #1e293b;
        }

        .profile-card {
            padding: 22px;
            margin-bottom: 25px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .profile-card h2 {
            margin-top: 0;
            color: #334155;
        }

        .profile-field {
            margin: 16px 0;
            overflow-wrap: anywhere;
        }

        .profile-field span {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 14px;
        }

        .profile-field strong {
            color: #0f172a;
        }

        .profile-form label {
            display: block;
            margin: 17px 0 8px;
            color: #334155;
            font-weight: 600;
        }

        .profile-form input {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        .profile-form button,
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 18px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
        }

        .profile-form button:hover,
        .back-button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .help-text {
            color: #64748b;
            font-size: 13px;
            margin-top: 8px;
        }

        @media (max-width: 600px) {
            .profile-container {
                margin: 15px;
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<main class="profile-container">

    <div class="profile-header">
        <h1>My Profile</h1>

        <a href="dashboard.php" class="back-button">
            ← Dashboard
        </a>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                "UTF-8"
            ) ?>
        </div>
    <?php endif; ?>

    <section class="profile-card">
        <h2>Account Information</h2>

        <div class="profile-field">
            <span>Username</span>
            <strong>
                <?= htmlspecialchars(
                    $profile["username"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>
        </div>

        <div class="profile-field">
            <span>Email Address</span>
            <strong>
                <?= htmlspecialchars(
                    $profile["email"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>
        </div>

        <div class="profile-field">
            <span>Member Since</span>
            <strong>
                <?= htmlspecialchars(
                    $profile["created_at"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>
        </div>
    </section>

    <section class="profile-card">
        <h2>Change Password</h2>

        <p>Enter your current password and choose a new password.</p>

        <form
            class="profile-form"
            method="POST"
            action="profile.php"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    generateCsrfToken(),
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label for="current_password">
                Current Password
            </label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                autocomplete="current-password"
                required
            >

            <label for="new_password">
                New Password
            </label>

            <input
                type="password"
                id="new_password"
                name="new_password"
                minlength="8"
                maxlength="4096"
                autocomplete="new-password"
                required
            >

            <label for="confirm_password">
                Confirm New Password
            </label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                maxlength="4096"
                autocomplete="new-password"
                required
            >

            <p class="help-text">
                Use at least 8 characters. Never share your password.
            </p>

            <button type="submit">
                Update Password
            </button>

        </form>
    </section>

</main>

</body>
</html>
