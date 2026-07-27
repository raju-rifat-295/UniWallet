<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user_id is not in the session, redirect immediately to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: /uniwallet/modules/auth/login.php");
    exit;
}
?>