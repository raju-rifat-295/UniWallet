<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$current_month = (int)date('m');
$current_year  = (int)date('Y');

try {
    // 1. Fetch Total Income for this month
    $inc_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM Income WHERE user_id = :uid AND MONTH(income_date) = :m AND YEAR(income_date) = :y");
    $inc_stmt->execute(['uid' => $user_id, 'm' => $current_month, 'y' => $current_year]);
    $month_income = $inc_stmt->fetch()['total'];

    // 2. Fetch Total Expense for this month
    $exp_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM Expense WHERE user_id = :uid AND MONTH(expense_date) = :m AND YEAR(expense_date) = :y");
    $exp_stmt->execute(['uid' => $user_id, 'm' => $current_month, 'y' => $current_year]);
    $month_expense = $exp_stmt->fetch()['total'];

    // 3. Fetch Latest Financial Health Score
    // 3. Fetch This Month's Financial Health Score
    $score_stmt = $pdo->prepare("SELECT total_score, rating FROM Financial_Score_History WHERE user_id = :uid AND month = :m AND year = :y");
    $score_stmt->execute(['uid' => $user_id, 'm' => $current_month, 'y' => $current_year]);
    $latest_score = $score_stmt->fetch();

    // 4. Fetch 5 most recent transactions (combining Income & Expense via UNION)
    $recent_sql = "
        SELECT 'Income' AS type, c.category_name, i.amount, i.income_date AS date, i.source AS note 
        FROM Income i JOIN Category c ON i.category_id = c.category_id WHERE i.user_id = :u1
        UNION ALL
        SELECT 'Expense' AS type, c.category_name, e.amount, e.expense_date AS date, e.note 
        FROM Expense e JOIN Category c ON e.category_id = c.category_id WHERE e.user_id = :u2
        ORDER BY date DESC LIMIT 6
    ";
    $recent_stmt = $pdo->prepare($recent_sql);
    $recent_stmt->execute(['u1' => $user_id, 'u2' => $user_id]);
    $recent_trans = $recent_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

$balance = $month_income - $month_expense;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white shadow-sm border-0 rounded-3">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="fw-bold mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</h2>
                    <p class="mb-0 text-light">Student ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?> | Here is your financial summary for <strong><?php echo date('F Y'); ?></strong>.</p>
                </div>
                <div class="mt-3 mt-md-0">
                    <a href="/uniwallet/modules/reports/index.php" class="text-decoration-none">
                        <?php if ($latest_score): ?>
                            <span class="badge bg-light text-dark fs-6 py-2 px-3 shadow-sm border">
                                ⭐ Health Score: <strong class="text-primary"><?php echo $latest_score['total_score']; ?>/100</strong> (<?php echo $latest_score['rating']; ?>)
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark fs-6 py-2 px-3 shadow-sm">
                                ⭐ Health Score: Click to Evaluate!
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3 border-start border-success border-4">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">This Month's Income</h5>
                <h3 class="fw-bold text-success mt-2">৳ <?php echo number_format($month_income, 2); ?></h3>
                <a href="/uniwallet/modules/transactions/income.php" class="btn btn-outline-success btn-sm mt-3">Add Income</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3 border-start border-danger border-4">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">This Month's Expenses</h5>
                <h3 class="fw-bold text-danger mt-2">৳ <?php echo number_format($month_expense, 2); ?></h3>
                <a href="/uniwallet/modules/transactions/expense.php" class="btn btn-outline-danger btn-sm mt-3">Add Expense</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3 border-start border-primary border-4">
            <div class="card-body text-center p-4">
                <h5 class="text-muted fw-semibold">Net Cash Balance</h5>
                <h3 class="fw-bold <?php echo ($balance >= 0) ? 'text-primary' : 'text-danger'; ?> mt-2">
                    ৳ <?php echo number_format($balance, 2); ?>
                </h3>
                <a href="/uniwallet/modules/reports/index.php" class="btn btn-outline-primary btn-sm mt-3">View Full Report</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Recent Activity Table -->
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">⚡ Quick Navigation</h5>
            </div>
            <div class="card-body p-3 d-grid gap-2">
                <a href="/uniwallet/modules/budget/index.php" class="btn btn-light text-start border py-2">🎯 Check Monthly Budgets</a>
                <a href="/uniwallet/modules/savings/index.php" class="btn btn-light text-start border py-2">🌱 Manage Savings Targets</a>
                <a href="/uniwallet/modules/loans/index.php" class="btn btn-light text-start border py-2">🤝 Peer Debt Tracker</a>
                <a href="/uniwallet/modules/calendar/index.php" class="btn btn-light text-start border py-2">📅 Smart Financial Calendar</a>
                <a href="/uniwallet/modules/reports/index.php" class="btn btn-primary text-center fw-bold py-2 mt-2">🏆 AI Health Evaluation</a>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">📋 Recent Financial Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Type</th>
                                <th>Category / Note</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_trans)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No transactions recorded yet. Start tracking above!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_trans as $t): ?>
                                    <?php $is_inc = $t['type'] === 'Income'; ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap small"><?php echo date('d M, Y', strtotime($t['date'])); ?></td>
                                        <td><span class="badge <?php echo $is_inc ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>"><?php echo $t['type']; ?></span></td>
                                        <td><strong class="text-dark"><?php echo htmlspecialchars($t['category_name']); ?></strong><small class="text-muted d-block"><?php echo !empty($t['note']) ? htmlspecialchars($t['note']) : ''; ?></small></td>
                                        <td class="text-end pe-4 fw-bold <?php echo $is_inc ? 'text-success' : 'text-danger'; ?>"><?php echo $is_inc ? '+' : '-'; ?> ৳ <?php echo number_format($t['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>