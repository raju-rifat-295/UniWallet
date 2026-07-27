<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Default to current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$current_year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// 1. Handle Budget Form Submission (Set or Update Budget)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id   = $_POST['category_id'];
    $budget_amount = trim($_POST['budget_amount']);
    $month         = (int)$_POST['month'];
    $year          = (int)$_POST['year'];

    if (empty($category_id) || empty($budget_amount) || empty($month) || empty($year)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($budget_amount) || $budget_amount <= 0) {
        $error = "Please enter a valid positive budget amount.";
    } else {
        try {
            // Advanced DBMS Feature: ON DUPLICATE KEY UPDATE (Upsert)
            // If budget exists for this Category+Month+Year, update it! Otherwise, insert a new one.
            $sql = "INSERT INTO Budget (user_id, category_id, budget_amount, month, year) 
                    VALUES (:user_id, :category_id, :amount, :month, :year)
                    ON DUPLICATE KEY UPDATE budget_amount = :amount_update";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id'       => $user_id,
                'category_id'   => $category_id,
                'amount'        => $budget_amount,
                'month'         => $month,
                'year'          => $year,
                'amount_update' => $budget_amount
            ]);
            $success = "Budget limit saved successfully for " . date("F", mktime(0, 0, 0, $month, 10)) . " $year!";
            
            // Stay on the selected month/year view
            $current_month = $month;
            $current_year  = $year;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// 2. Fetch Expense Categories for the Dropdown
try {
    $cat_stmt = $pdo->query("SELECT category_id, category_name FROM Category WHERE category_type = 'Expense' ORDER BY category_name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load categories: " . $e->getMessage();
}

// 3. Fetch User's Budgets AND Calculate Actual Spending using an SQL Subquery
try {
    $budget_sql = "SELECT b.*, c.category_name,
                          IFNULL((SELECT SUM(e.amount) 
                                  FROM Expense e 
                                  WHERE e.user_id = b.user_id 
                                    AND e.category_id = b.category_id 
                                    AND MONTH(e.expense_date) = b.month 
                                    AND YEAR(e.expense_date) = b.year), 0.00) AS total_spent
                   FROM Budget b
                   JOIN Category c ON b.category_id = c.category_id
                   WHERE b.user_id = :user_id AND b.month = :month AND b.year = :year
                   ORDER BY c.category_name ASC";
                   
    $budget_stmt = $pdo->prepare($budget_sql);
    $budget_stmt->execute([
        'user_id' => $user_id,
        'month'   => $current_month,
        'year'    => $current_year
    ]);
    $budgets = $budget_stmt->fetchAll();

    // Calculate totals for summary badges
    $total_planned = 0;
    $total_actual_spent = 0;
    foreach ($budgets as $b) {
        $total_planned += $b['budget_amount'];
        $total_actual_spent += $b['total_spent'];
    }
} catch (PDOException $e) {
    $error = "Failed to load budgets: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">🎯 Monthly Spending Budgets</h2>
            <p class="text-muted mb-0">Set monthly expense ceilings and track your spending against your limits.</p>
        </div>
        
        <!-- Month/Year Filter Form -->
        <form action="index.php" method="GET" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($m === $current_month) ? 'selected' : ''; ?>>
                        <?php echo date("F", mktime(0, 0, 0, $m, 10)); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($y === $current_year) ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Set/Update Budget Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">⚙️ Set Category Limit</h5>
            </div>
            <div class="card-body p-4">
                <form action="index.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Expense Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Monthly Budget Ceiling (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="budget_amount" class="form-control" placeholder="e.g., 4000.00" required>
                    </div>

                    <div class="row mb-4">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Month</label>
                            <select name="month" class="form-select" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($m === $current_month) ? 'selected' : ''; ?>>
                                        <?php echo date("M", mktime(0, 0, 0, $m, 10)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Year</label>
                            <select name="year" class="form-select" required>
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y === $current_year) ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Save Budget Ceiling</button>
                    </div>
                </form>
                
                <div class="alert alert-light border mt-4 mb-0 py-2 px-3 small text-muted">
                    💡 <strong>Tip:</strong> If you set a budget for a category that already exists for this month, the system will update your old ceiling automatically!
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Budget Progress Cards -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3 mb-4 bg-primary-subtle border-primary-subtle">
            <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <span class="text-primary-emphasis fw-bold">Total Planned: ৳ <?php echo number_format($total_planned, 2); ?></span>
                </div>
                <div>
                    <span class="text-danger-emphasis fw-bold">Actual Spent: ৳ <?php echo number_format($total_actual_spent, 2); ?></span>
                </div>
                <div>
                    <?php $remaining = $total_planned - $total_actual_spent; ?>
                    <span class="<?php echo ($remaining >= 0) ? 'text-success-emphasis' : 'text-danger fw-bold'; ?>">
                        Remaining: ৳ <?php echo number_format($remaining, 2); ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (empty($budgets)): ?>
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body text-center py-5 text-muted">
                    <div class="fs-1 mb-2">📊</div>
                    <p class="mb-0">No budget ceilings defined for <strong><?php echo date("F", mktime(0, 0, 0, $current_month, 10)) . " " . $current_year; ?></strong>.</p>
                    <small>Use the form on the left to set spending limits for food, transport, or bills!</small>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($budgets as $b): ?>
                    <?php 
                        $percentage = ($b['budget_amount'] > 0) ? ($b['total_spent'] / $b['budget_amount']) * 100 : 0;
                        
                        // Determine progress bar colors based on spending percentage
                        if ($percentage >= 100) {
                            $bar_color = 'bg-danger'; // Over budget!
                            $status_text = 'Over Budget!';
                            $status_class = 'text-danger fw-bold';
                        } elseif ($percentage >= 75) {
                            $bar_color = 'bg-warning'; // Warning threshold
                            $status_text = 'Nearing Limit';
                            $status_class = 'text-warning-emphasis fw-semibold';
                        } else {
                            $bar_color = 'bg-success'; // Safe zone
                            $status_text = 'On Track';
                            $status_class = 'text-success fw-semibold';
                        }
                    ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 rounded-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($b['category_name']); ?></h6>
                                    <span class="small <?php echo $status_class; ?>"><?php echo $status_text; ?> (<?php echo round($percentage, 1); ?>%)</span>
                                </div>
                                
                                <div class="d-flex justify-content-between small text-muted mb-2">
                                    <span>Spent: <strong class="text-dark">৳ <?php echo number_format($b['total_spent'], 2); ?></strong></span>
                                    <span>Limit: <strong class="text-primary">৳ <?php echo number_format($b['budget_amount'], 2); ?></strong></span>
                                </div>

                                <!-- Bootstrap Progress Bar -->
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar <?php echo $bar_color; ?>" role="progressbar" 
                                         style="width: <?php echo min($percentage, 100); ?>%;" 
                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>