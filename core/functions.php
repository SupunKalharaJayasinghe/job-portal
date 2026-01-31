<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLoggedIn(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function sanitizeInput(string $value): string
{
    return trim(strip_tags($value));
}
?>
