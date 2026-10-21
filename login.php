
<?php

session_start();

require_once "config/database.php";
require_once "csrf.php";

$message = "";

// Display registration success message, if available
if (isset($_SESSION["flash_success"])) {
    $message = $_SESSION["flash_success"];
    unset($_SESSION["flash_success"]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate CSRF token
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page and try again.");
    }

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $message = "Please fill in all fields.";

    } else {

        try {
            // Find the user by username
            $sql = "SELECT id, username, password
                    FROM users
                    WHERE username = :username
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":username" => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password only if the user exists
            if ($user && password_verify($password, $user["password"])) {

                // Generate a new session ID after successful login
                session_regenerate_id(true);

                $_SESSION["user_id"] = (int) $user["id"];
                $_SESSION["username"] = $user["username"];

                // Redirect to the dashboard
                header("Location: dashboard.php");
                exit;

            } else {

                $message = "Invalid username or password.";
            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $message = "Login is temporarily unavailable. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .login-container {
            max-width: 420px;
            margin: 60px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .login-container h1 {
            margin-top: 0;
            color: #1e293b;
        }

        .login-container label {
            display: block;
            margin: 18px 0 8px;
            color: #334155;
            font-weight: bold;
        }

        .login-container input {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        .login-container button {
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

        .login-container button:hover {
            background: #1d4ed8;
        }

        .message {
            margin: 18px 0;
            padding: 12px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
        }

        .login-footer {
            margin-top: 20px;
            color: #475569;
        }

        .login-footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="login-container">

        <h1>Login</h1>

        <p>Sign in to your bank account.</p>

        <?php if ($message !== ""): ?>
            <div class="message">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, "UTF-8") ?>"
            >

            <label for="username">Username</label>

            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Login</button>

        </form>

        <div class="login-footer">
            Don't have an account?
            <a href="register.php">Register here</a>
        </div>

    </div>

</body>

</html>
