
<?php

session_start();

require_once "config/database.php";
require_once "csrf.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate CSRF token
    if (!verifyCsrfToken()) {
        http_response_code(403);
        exit("Invalid security token. Please reload the page and try again.");
    }

    // Read form fields
    $username = trim($_POST["username"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Validate input
    if ($username === "" || $email === "" || $password === "") {

        $error = "All fields are required.";

    } elseif (strlen($username) > 50) {

        $error = "Username must not exceed 50 characters.";

    } elseif (strlen($email) > 255) {

        $error = "Email address is too long.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters long.";

    } elseif (strlen($password) > 4096) {

        $error = "Password is too long.";

    } else {

        try {
            // Hash the password before storing it
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert the new user using a prepared statement
            $sql = "INSERT INTO users (username, email, password)
                    VALUES (:username, :email, :password)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ":username" => $username,
                ":email" => $email,
                ":password" => $hashedPassword
            ]);

            // Redirect after successful registration
            $_SESSION["flash_success"] =
                "Registration successful! You can now log in.";

            header("Location: login.php");
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());

            if ($e->getCode() === "23000") {
                $error = "This username or email may already be registered.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | PHP Bank Manager</title>

    <link rel="stylesheet" href="css/style.css">

    <style>
        .register-container {
            max-width: 440px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .register-container h1 {
            margin-top: 0;
            color: #1e293b;
        }

        .register-container label {
            display: block;
            margin: 16px 0 8px;
            font-weight: bold;
            color: #334155;
        }

        .register-container input {
            width: 100%;
            padding: 12px;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        .register-container button {
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

        .register-container button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            margin: 16px 0;
            border-radius: 8px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .register-footer {
            margin-top: 20px;
            color: #475569;
        }

        .register-footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="register-container">

        <h1>Create an Account</h1>

        <p>Register for PHP Bank Manager.</p>

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

        <form method="POST" action="register.php">

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
                maxlength="50"
                autocomplete="username"
                required
            >

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                maxlength="255"
                autocomplete="email"
                required
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                maxlength="4096"
                autocomplete="new-password"
                required
            >

            <button type="submit">Create Account</button>

        </form>

        <div class="register-footer">
            Already have an account?
            <a href="login.php">Log in</a>
        </div>

    </div>

</body>
</html>
