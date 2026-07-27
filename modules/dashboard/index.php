<?php
require_once '../../includes/auth_check.php'; // Protects this page from unauthenticated guests!
require_once '../../config/db.php';
require_once '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white shadow-sm border-0 rounded-3">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</h2>
                    <p class="mb-0 text-light">Student ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?> | Here is your financial overview.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <span class="badge bg-light text-primary fs-6 py-2 px-3 shadow-sm">
                        ⭐ Financial Health: Evaluating...
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">Total Income</h5>
                <h3 class="fw-bold text-success mt-2">৳ 0.00</h3>
                <a href="/uniwallet/modules/transactions/income.php" class="btn btn-outline-success btn-sm mt-3">Add Income</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">Total Expenses</h5>
                <h3 class="fw-bold text-danger mt-2">৳ 0.00</h3>
                <a href="/uniwallet/modules/transactions/expense.php" class="btn btn-outline-danger btn-sm mt-3">Add Expense</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">Current Balance</h5>
                <h3 class="fw-bold text-primary mt-2">৳ 0.00</h3>
                <a href="/uniwallet/modules/budget/index.php" class="btn btn-outline-primary btn-sm mt-3">View Budgets</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="alert alert-info shadow-sm border-0 d-flex align-items-center" role="alert">
            <span class="fs-4 me-3">🚀</span>
            <div>
                <strong>Phase 2 Complete!</strong> Your authentication system is working perfectly. In the next phase, we will build the actual database queries to replace these zeros with live financial tracking!
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>