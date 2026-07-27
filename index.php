<?php
session_start();

// If logged in, send to dashboard; otherwise, send to login
if (isset($_SESSION['user_id'])) {
    header("Location: /uniwallet/modules/dashboard/index.php");
} else {
    header("Location: /uniwallet/modules/auth/login.php");
}
exit;
?>