<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Handle Manual Evaluation Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluate_score'])) {
    $month = (int)$_POST['month'];
    $year  = (int)$_POST['year'];

    try {
        // 1. Fetch Total Income for the month
        $inc_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM Income WHERE user_id = :uid AND MONTH(income_date) = :m AND YEAR(income_date) = :y");
        $inc_stmt->execute(['uid' => $user_id, 'm' => $month, 'y' => $year]);
        $total_income = $inc_stmt->fetch()['total'];

        // 2. Fetch Total Expense for the month
        $exp_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) AS total FROM Expense WHERE user_id = :uid AND MONTH(expense_date) = :m AND YEAR(expense_date) = :y");
        $exp_stmt->execute(['uid' => $user_id, 'm' => $month, 'y' => $year]);
        $total_expense = $exp_stmt->fetch()['total'];

        // 3. Fetch Budget Adherence
        $bud_stmt = $pdo->prepare("SELECT IFNULL(SUM(budget_amount), 0) AS total_planned FROM Budget WHERE user_id = :uid AND month = :m AND year = :y");
        $bud_stmt->execute(['uid' => $user_id, 'm' => $month, 'y' => $year]);
        $total_budget = $bud_stmt->fetch()['total_planned'];

        // 4. Fetch Savings Net Growth for the month
        $sav_stmt = $pdo->prepare("SELECT IFNULL(SUM(CASE WHEN transaction_type = 'Deposit' THEN amount ELSE -amount END), 0) AS net_saved 
                                   FROM Savings_Transaction t JOIN Savings_goal g ON t.goal_id = g.goal_id 
                                   WHERE g.user_id = :uid AND MONTH(t.transaction_date) = :m AND YEAR(t.transaction_date) = :y");
        $sav_stmt->execute(['uid' => $user_id, 'm' => $month, 'y' => $year]);
        $total_savings = max(0, $sav_stmt->fetch()['net_saved']);

        // 5. Fetch Unpaid Borrowed Debt
        $debt_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount - IFNULL((SELECT SUM(amount) FROM Loan_Payment WHERE loan_id = l.loan_id), 0)), 0) AS unpaid 
                                    FROM Loan l WHERE user_id = :uid AND loan_type = 'Borrow' AND status != 'Completed'");
        $debt_stmt->execute(['uid' => $user_id]);
        $unpaid_debt = max(0, $debt_stmt->fetch()['unpaid']);

        // --- THE DBMS FINANCIAL HEALTH SCORING ALGORITHM (100 Points Total) ---
        
        // Dimension 1: Budget Score (25 max) - Are expenses under planned limits?
        if ($total_budget == 0) {
            $budget_score = 15; // Neutral default if no budget set
        } elseif ($total_expense <= $total_budget) {
            $budget_score = 25; // Perfect budget discipline!
        } else {
            $overspend_ratio = ($total_expense - $total_budget) / $total_budget;
            $budget_score = max(0, 25 - ($overspend_ratio * 25));
        }

        // Dimension 2: Cash Flow & Payment Score (25 max) - Is income >= expenses?
        if ($total_income == 0 && $total_expense == 0) {
            $payment_score = 15;
        } elseif ($total_income >= $total_expense) {
            $payment_score = 25;
        } else {
            $deficit_ratio = ($total_expense - $total_income) / max(1, $total_income);
            $payment_score = max(0, 25 - ($deficit_ratio * 25));
        }

        // Dimension 3: Savings Discipline Score (25 max) - Are they actively saving?
        if ($total_savings > 0) {
            $savings_score = min(25, ($total_savings / max(1, $total_income)) * 100);
        } else {
            $savings_score = 5; // Minimal points if nothing saved this month
        }

        // Dimension 4: Debt Management Score (25 max) - Keeping informal debts low
        if ($unpaid_debt == 0) {
            $debt_score = 25; // No outstanding borrowed debt!
        } else {
            $debt_ratio = $unpaid_debt / max(1, $total_income);
            $debt_score = max(0, 25 - ($debt_ratio * 20));
        }

        // Total Score & Descriptive Rating
        $total_score = round($budget_score + $payment_score + $savings_score + $debt_score, 2);
        if ($total_score >= 80)      $rating = "Excellent";
        elseif ($total_score >= 65)  $rating = "Good";
        elseif ($total_score >= 50)  $rating = "Fair";
        else                         $rating = "Needs Improvement";

        // Save into Financial_Score_History (Upsert)
        $score_sql = "INSERT INTO Financial_Score_History (user_id, emergency_score, payment_score, savings_score, budget_score, debt_score, total_score, rating, month, year) 
                      VALUES (:uid, 0, :ps, :ss, :bs, :ds, :tot, :rating, :m, :y)
                      ON DUPLICATE KEY UPDATE payment_score=:ps2, savings_score=:ss2, budget_score=:bs2, debt_score=:ds2, total_score=:tot2, rating=:rating2, calculated_at=CURRENT_TIMESTAMP";
        $score_stmt = $pdo->prepare($score_sql);
        $score_stmt->execute([
            'uid' => $user_id, 'ps' => $payment_score, 'ss' => $savings_score, 'bs' => $budget_score, 'ds' => $debt_score, 'tot' => $total_score, 'rating' => $rating, 'm' => $month, 'y' => $year,
            'ps2' => $payment_score, 'ss2' => $savings_score, 'bs2' => $budget_score, 'ds2' => $debt_score, 'tot2' => $total_score, 'rating2' => $rating
        ]);

        // Save into Monthly_Report (Upsert)
        $rem_balance = $total_income - $total_expense;
        $rep_sql = "INSERT INTO Monthly_Report (user_id, month, year, total_income, total_expense, total_savings, remaining_balance, financial_score) 
                    VALUES (:uid, :m, :y, :inc, :exp, :sav, :rem, :score)
                    ON DUPLICATE KEY UPDATE total_income=:inc2, total_expense=:exp2, total_savings=:sav2, remaining_balance=:rem2, financial_score=:score2, generated_at=CURRENT_TIMESTAMP";
        $rep_stmt = $pdo->prepare($rep_sql);
        $rep_stmt->execute([
            'uid' => $user_id, 'm' => $month, 'y' => $year, 'inc' => $total_income, 'exp' => $total_expense, 'sav' => $total_savings, 'rem' => $rem_balance, 'score' => $total_score,
            'inc2' => $total_income, 'exp2' => $total_expense, 'sav2' => $total_savings, 'rem2' => $rem_balance, 'score2' => $total_score
        ]);

        $success = "Financial Health evaluated successfully! Your score for " . date("F", mktime(0, 0, 0, $month, 10)) . " $year is $total_score ($rating).";
        $current_month = $month;
        $current_year  = $year;
    } catch (PDOException $e) {
        $error = "Evaluation Error: " . $e->getMessage();
    }
}

// Fetch Stored Evaluation & Report for Selected Month
try {
    $fetch_stmt = $pdo->prepare("SELECT * FROM Financial_Score_History WHERE user_id = :uid AND month = :m AND year = :y");
    $fetch_stmt->execute(['uid' => $user_id, 'm' => $current_month, 'y' => $current_year]);
    $score_data = $fetch_stmt->fetch();

    $rep_stmt = $pdo->prepare("SELECT * FROM Monthly_Report WHERE user_id = :uid AND month = :m AND year = :y");
    $rep_stmt->execute(['uid' => $user_id, 'm' => $current_month, 'y' => $current_year]);
    $report_data = $rep_stmt->fetch();
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">🏆 Financial Health & Monthly Reports</h2>
            <p class="text-muted mb-0">AI-style algorithmic evaluation of your campus money management habits.</p>
        </div>
        
        <form action="index.php" method="GET" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m === $current_month) ? 'selected' : ''; ?>><?php echo date("F", mktime(0, 0, 0, $m, 10)); ?></option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y === $current_year) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error:</strong> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success!</strong> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Trigger Evaluation Button & Monthly Overview -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3 mb-4 bg-primary text-white text-center p-4">
            <h4 class="fw-bold mb-2">⚡ Generate Evaluation</h4>
            <p class="small text-light mb-4">Click below to aggregate your income, expenses, budgets, and debts for <strong><?php echo date("F", mktime(0, 0, 0, $current_month, 10)) . " " . $current_year; ?></strong>.</p>
            <form action="index.php" method="POST">
                <input type="hidden" name="evaluate_score" value="1">
                <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                <button type="submit" class="btn btn-light text-primary fw-bold w-100 py-2 shadow-sm">🚀 Calculate Health Score</button>
            </form>
        </div>

        <?php if ($report_data): ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 border-bottom"><h5 class="mb-0 fw-bold">📊 Monthly Financial Summary</h5></div>
                <div class="card-body p-3">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span>Total Income:</span><strong class="text-success">৳ <?php echo number_format($report_data['total_income'], 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Total Expense:</span><strong class="text-danger">৳ <?php echo number_format($report_data['total_expense'], 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Net Savings:</span><strong class="text-primary">৳ <?php echo number_format($report_data['total_savings'], 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Remaining Balance:</span><strong class="text-dark">৳ <?php echo number_format($report_data['remaining_balance'], 2); ?></strong></li>
                    </ul>
                    <div class="text-muted small text-center mt-3">Generated: <?php echo date('d M Y, h:i A', strtotime($report_data['generated_at'])); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Visual Health Score & Dimension Breakdown -->
    <div class="col-lg-8">
        <?php if (!$score_data): ?>
            <div class="card shadow-sm border-0 rounded-3 text-center py-5 text-muted">
                <div class="fs-1 mb-2">⭐</div>
                <h5 class="fw-bold text-dark">No Evaluation Found for this Month</h5>
                <p class="mb-0">Click the **"Calculate Health Score"** button on the left to run the DBMS evaluation algorithm!</p>
            </div>
        <?php else: ?>
            <?php 
                $badge_class = 'bg-success';
                if ($score_data['rating'] === 'Good') $badge_class = 'bg-primary';
                elseif ($score_data['rating'] === 'Fair') $badge_class = 'bg-warning text-dark';
                elseif ($score_data['rating'] === 'Needs Improvement') $badge_class = 'bg-danger';
            ?>
            <div class="card shadow-sm border-0 rounded-3 mb-4 p-4 text-center bg-light border">
                <h6 class="text-uppercase text-muted fw-bold mb-1">Overall Financial Health Rating</h6>
                <div class="display-3 fw-bold text-dark mb-2"><?php echo $score_data['total_score']; ?> <span class="fs-4 text-muted">/ 100</span></div>
                <div><span class="badge <?php echo $badge_class; ?> fs-5 py-2 px-4 shadow-sm"><?php echo $score_data['rating']; ?></span></div>
            </div>

            <h5 class="fw-bold mb-3">📈 Score Dimension Breakdown (25 Pts Each)</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3 p-3">
                        <div class="d-flex justify-content-between fw-bold mb-1"><span>🎯 Budget Discipline</span><span class="text-primary"><?php echo $score_data['budget_score']; ?> / 25</span></div>
                        <div class="progress" style="height: 10px;"><div class="progress-bar bg-primary" style="width: <?php echo ($score_data['budget_score']/25)*100; ?>%;"></div></div>
                        <small class="text-muted mt-2 d-block">Measures how well you stay under your monthly category ceilings.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3 p-3">
                        <div class="d-flex justify-content-between fw-bold mb-1"><span>💵 Cash Flow & Payments</span><span class="text-success"><?php echo $score_data['payment_score']; ?> / 25</span></div>
                        <div class="progress" style="height: 10px;"><div class="progress-bar bg-success" style="width: <?php echo ($score_data['payment_score']/25)*100; ?>%;"></div></div>
                        <small class="text-muted mt-2 d-block">Evaluates positive cash flow (ensuring income meets or exceeds spending).</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3 p-3">
                        <div class="d-flex justify-content-between fw-bold mb-1"><span>🌱 Savings Consistency</span><span class="text-info"><?php echo $score_data['savings_score']; ?> / 25</span></div>
                        <div class="progress" style="height: 10px;"><div class="progress-bar bg-info" style="width: <?php echo ($score_data['savings_score']/25)*100; ?>%;"></div></div>
                        <small class="text-muted mt-2 d-block">Rewards consistent deposit transactions into active savings goals.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-0 rounded-3 p-3">
                        <div class="d-flex justify-content-between fw-bold mb-1"><span>🤝 Debt Burden Control</span><span class="text-warning"><?php echo $score_data['debt_score']; ?> / 25</span></div>
                        <div class="progress" style="height: 10px;"><div class="progress-bar bg-warning" style="width: <?php echo ($score_data['debt_score']/25)*100; ?>%;"></div></div>
                        <small class="text-muted mt-2 d-block">Monitors informal borrowing to ensure unpaid debts remain manageable.</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>