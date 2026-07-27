<?php
// Start a PHP session if one doesn't exist already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniWallet - Student Expense Management</title>
    <!-- Bootstrap 5 CDN for clean, responsive UI -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/uniwallet/assets/css/style.css">
</head>
<body class="bg-light">
    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/uniwallet/index.php">🎓 UniWallet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Links shown ONLY to logged-in students -->
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/dashboard/index.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/transactions/income.php">Income</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/transactions/expense.php">Expenses</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/budget/index.php">Budgets</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/savings/index.php">Savings</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/loans/index.php">Loans</a></li>
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/calendar/index.php">📅 Calendar</a></li>
                        <li class="nav-item ms-lg-3">
                            <span class="navbar-text text-light me-2">Hi, <strong><?php echo htmlspecialchars($_SESSION['first_name']); ?></strong></span>
                        </li>
                        <li class="nav-item"><a class="btn btn-danger btn-sm px-3" href="/uniwallet/modules/auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <!-- Links shown to guests -->
                        <li class="nav-item"><a class="nav-link" href="/uniwallet/modules/auth/login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-light text-primary ms-2 px-3 fw-bold" href="/uniwallet/modules/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Start Main Content Container -->
    <div class="container pb-5">