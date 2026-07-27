<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Handle Form Submissions (Create Goal OR Add Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ACTION A: Create a New Savings Goal
    if ($action === 'create_goal') {
        $goal_name     = trim($_POST['goal_name']);
        $target_amount = trim($_POST['target_amount']);
        $deadline      = $_POST['deadline'];

        if (empty($goal_name) || empty($target_amount) || empty($deadline)) {
            $error = "Please fill in all fields to create a goal.";
        } elseif (!is_numeric($target_amount) || $target_amount <= 0) {
            $error = "Please enter a valid target amount.";
        } else {
            try {
                $sql = "INSERT INTO Savings_goal (user_id, goal_name, target_amount, deadline) 
                        VALUES (:user_id, :goal_name, :target_amount, :deadline)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'user_id'       => $user_id,
                    'goal_name'     => $goal_name,
                    'target_amount' => $target_amount,
                    'deadline'      => $deadline
                ]);
                $success = "Savings goal '$goal_name' created successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    // ACTION B: Add Deposit or Withdrawal Transaction
    if ($action === 'add_transaction') {
        $goal_id          = $_POST['goal_id'];
        $amount           = trim($_POST['amount']);
        $transaction_type = $_POST['transaction_type'];
        $note             = trim($_POST['note']);

        if (empty($goal_id) || empty($amount) || empty($transaction_type)) {
            $error = "Please select a goal, amount, and transaction type.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error = "Please enter a valid positive amount.";
        } else {
            try {
                $sql = "INSERT INTO Savings_Transaction (goal_id, amount, transaction_type, note) 
                        VALUES (:goal_id, :amount, :type, :note)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'goal_id' => $goal_id,
                    'amount'  => $amount,
                    'type'    => $transaction_type,
                    'note'    => $note
                ]);
                $success = "$transaction_type of ৳ " . number_format($amount, 2) . " recorded successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// 2. Fetch All Active Savings Goals AND Dynamically Calculate Saved Amount (3NF Proof!)
try {
    $goals_sql = "SELECT g.*, 
                         IFNULL((SELECT SUM(CASE 
                                                WHEN t.transaction_type = 'Deposit' THEN t.amount 
                                                ELSE -t.amount 
                                            END) 
                                 FROM Savings_Transaction t 
                                 WHERE t.goal_id = g.goal_id), 0.00) AS total_saved
                  FROM Savings_goal g
                  WHERE g.user_id = :user_id AND g.status = 'In Progress'
                  ORDER BY g.deadline ASC";
    $goals_stmt = $pdo->prepare($goals_sql);
    $goals_stmt->execute(['user_id' => $user_id]);
    $goals = $goals_stmt->fetchAll();

    // Calculate total savings across all goals for display badge
    $total_all_savings = 0;
    foreach ($goals as $g) {
        $total_all_savings += max(0, $g['total_saved']);
    }
} catch (PDOException $e) {
    $error = "Failed to load savings goals: " . $e->getMessage();
}

// 3. Fetch Recent Savings Transactions History
try {
    $trans_sql = "SELECT t.*, g.goal_name 
                  FROM Savings_Transaction t
                  JOIN Savings_goal g ON t.goal_id = g.goal_id
                  WHERE g.user_id = :user_id
                  ORDER BY t.transaction_date DESC LIMIT 8";
    $trans_stmt = $pdo->prepare($trans_sql);
    $trans_stmt->execute(['user_id' => $user_id]);
    $transactions = $trans_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load transaction history: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">🌱 Savings Goal Management</h2>
            <p class="text-muted mb-0">Define specific targets (laptop, semester fees, emergency funds) and track deposit progress.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-primary fs-5 py-2 px-3 shadow-sm">
                Total Saved: ৳ <?php echo number_format($total_all_savings, 2); ?>
            </span>
        </div>
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
    <!-- Left Column: Two Action Forms (Create Goal & Add Money) -->
    <div class="col-lg-4">
        <!-- Form 1: Create Goal -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">🎯 Create New Target</h5>
            </div>
            <div class="card-body p-4">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="create_goal">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Goal Name <span class="text-danger">*</span></label>
                        <input type="text" name="goal_name" class="form-control" placeholder="e.g., New Laptop, Semester Fee" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="target_amount" class="form-control" placeholder="e.g., 50000.00" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Target Deadline <span class="text-danger">*</span></label>
                        <input type="date" name="deadline" class="form-control" required>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Create Goal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form 2: Deposit / Withdraw Money -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold">💰 Deposit / Withdraw</h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($goals)): ?>
                    <div class="text-center py-3 text-muted small">
                        Please create a savings goal above before adding transactions!
                    </div>
                <?php else: ?>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="add_transaction">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Goal <span class="text-danger">*</span></label>
                            <select name="goal_id" class="form-select" required>
                                <option value="">Choose Target...</option>
                                <?php foreach ($goals as $g): ?>
                                    <option value="<?php echo $g['goal_id']; ?>">
                                        <?php echo htmlspecialchars($g['goal_name']); ?> (Saved: ৳ <?php echo number_format($g['total_saved'], 0); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                <select name="transaction_type" class="form-select" required>
                                    <option value="Deposit" selected>Deposit (+)</option>
                                    <option value="Withdrawal">Withdraw (-)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Amount (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="500.00" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Note / Source (Optional)</label>
                            <input type="text" name="note" class="form-control" placeholder="e.g., Saved from freelance gig">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success fw-bold py-2">Record Transaction</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Active Goals Progress & Recent History -->
    <div class="col-lg-8">
        <h5 class="fw-bold mb-3">🏆 Active Savings Targets</h5>
        
        <?php if (empty($goals)): ?>
            <div class="card shadow-sm border-0 rounded-3 mb-5">
                <div class="card-body text-center py-5 text-muted">
                    <div class="fs-1 mb-2">🐷</div>
                    <p class="mb-0">You don't have any active savings goals yet.</p>
                    <small>Use the form on the left to set up your first financial target!</small>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-5">
                <?php foreach ($goals as $g): ?>
                    <?php 
                        $percentage = ($g['target_amount'] > 0) ? ($g['total_saved'] / $g['target_amount']) * 100 : 0;
                        $is_completed = $percentage >= 100;
                        
                        // Calculate days remaining
                        $days_remaining = (strtotime($g['deadline']) - time()) / (60 * 60 * 24);
                        $deadline_class = ($days_remaining < 7 && !$is_completed) ? 'text-danger fw-bold' : 'text-muted';
                    ?>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 rounded-3 h-100 <?php echo $is_completed ? 'border-success border-2' : ''; ?>">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($g['goal_name']); ?></h5>
                                        <?php if ($is_completed): ?>
                                            <span class="badge bg-success">🎉 Goal Reached!</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">In Progress</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Saved: <strong class="text-success fs-6">৳ <?php echo number_format($g['total_saved'], 2); ?></strong></span>
                                        <span>Target: <strong class="text-dark fs-6">৳ <?php echo number_format($g['target_amount'], 2); ?></strong></span>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="progress mb-3" style="height: 12px;">
                                        <div class="progress-bar <?php echo $is_completed ? 'bg-success' : 'bg-primary'; ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo min($percentage, 100); ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                                    <span class="<?php echo $deadline_class; ?>">
                                        📅 Due: <?php echo date('d M, Y', strtotime($g['deadline'])); ?>
                                    </span>
                                    <span class="fw-bold <?php echo $is_completed ? 'text-success' : 'text-primary'; ?>">
                                        <?php echo round($percentage, 1); ?>% Complete
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Recent Savings Transactions Table -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">📜 Recent Deposit & Withdrawal History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Goal</th>
                                <th>Type / Note</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        No savings transactions recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <?php $is_deposit = $t['transaction_type'] === 'Deposit'; ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap small">
                                            <?php echo date('d M, Y', strtotime($t['transaction_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($t['goal_name']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $is_deposit ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'; ?> me-1">
                                                <?php echo htmlspecialchars($t['transaction_type']); ?>
                                            </span>
                                            <small class="text-muted"><?php echo !empty($t['note']) ? htmlspecialchars($t['note']) : ''; ?></small>
                                        </td>
                                        <td class="text-end pe-4 fw-bold <?php echo $is_deposit ? 'text-success' : 'text-danger'; ?> text-nowrap">
                                            <?php echo $is_deposit ? '+' : '-'; ?> ৳ <?php echo number_format($t['amount'], 2); ?>
                                        </td>
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