<?php

function generateCsrfToken(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function verifyCsrfToken(): bool
{
    $token = $_POST["csrf_token"] ?? "";
    $sessionToken = $_SESSION["csrf_token"] ?? "";

    return is_string($token)
        && is_string($sessionToken)
        && $sessionToken !== ""
        && hash_equals($sessionToken, $token);
}