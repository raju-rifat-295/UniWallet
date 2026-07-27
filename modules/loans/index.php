<?php
require_once '../../includes/auth_check.php';
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ACTION A: Record a New Loan (Borrow or Lend)
    if ($action === 'create_loan') {
        $person_name = trim($_POST['person_name']);
        $loan_type   = $_POST['loan_type'];
        $amount      = trim($_POST['amount']);
        $borrow_date = $_POST['borrow_date'];
        $due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $note        = trim($_POST['note']);

        if (empty($person_name) || empty($loan_type) || empty($amount) || empty($borrow_date)) {
            $error = "Please fill in all required fields.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error = "Please enter a valid positive amount.";
        } else {
            try {
                $sql = "INSERT INTO Loan (user_id, person_name, loan_type, status, borrow_date, due_date, amount, note) 
                        VALUES (:user_id, :person, :type, 'Pending', :b_date, :d_date, :amount, :note)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'user_id' => $user_id,
                    'person'  => $person_name,
                    'type'    => $loan_type,
                    'b_date'  => $borrow_date,
                    'd_date'  => $due_date,
                    'amount'  => $amount,
                    'note'    => $note
                ]);
                $type_label = ($loan_type === 'Borrow') ? 'borrowed from' : 'lent to';
                $success = "Successfully recorded ৳ " . number_format($amount, 2) . " $type_label $person_name!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    // ACTION B: Add an Installment / Repayment
    if ($action === 'add_payment') {
        $loan_id        = $_POST['loan_id'];
        $amount         = trim($_POST['amount']);
        $payment_date   = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $note           = trim($_POST['note']);

        if (empty($loan_id) || empty($amount) || empty($payment_date)) {
            $error = "Please select a loan, amount, and date.";
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $error = "Please enter a valid positive amount.";
        } else {
            try {
                // Step 1: Insert the payment installment
                $pay_sql = "INSERT INTO Loan_Payment (loan_id, payment_date, amount, payment_method, note) 
                            VALUES (:loan_id, :p_date, :amount, :method, :note)";
                $pay_stmt = $pdo->prepare($pay_sql);
                $pay_stmt->execute([
                    'loan_id' => $loan_id,
                    'p_date'  => $payment_date,
                    'amount'  => $amount,
                    'method'  => $payment_method,
                    'note'    => $note
                ]);

                // Step 2: Calculate total paid so far for this loan
                $sum_stmt = $pdo->prepare("SELECT SUM(amount) AS total_paid FROM Loan_Payment WHERE loan_id = :loan_id");
                $sum_stmt->execute(['loan_id' => $loan_id]);
                $total_paid = $sum_stmt->fetch()['total_paid'] ?? 0;

                // Step 3: Check original loan amount
                $loan_stmt = $pdo->prepare("SELECT amount FROM Loan WHERE loan_id = :loan_id");
                $loan_stmt->execute(['loan_id' => $loan_id]);
                $original_amount = $loan_stmt->fetch()['amount'] ?? 0;

                // Step 4: Dynamically update Loan status!
                $new_status = ($total_paid >= $original_amount) ? 'Completed' : 'Partially Paid';
                $update_stmt = $pdo->prepare("UPDATE Loan SET status = :status WHERE loan_id = :loan_id");
                $update_stmt->execute(['status' => $new_status, 'loan_id' => $loan_id]);

                $success = "Repayment of ৳ " . number_format($amount, 2) . " recorded! Status automatically updated to '$new_status'.";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// 2. Fetch All Loans AND Calculate Total Repaid Amount via SQL Subquery
try {
    $loans_sql = "SELECT l.*, 
                         IFNULL((SELECT SUM(p.amount) FROM Loan_Payment p WHERE p.loan_id = l.loan_id), 0.00) AS total_paid
                  FROM Loan l
                  WHERE l.user_id = :user_id
                  ORDER BY l.status ASC, l.borrow_date DESC";
    $loans_stmt = $pdo->prepare($loans_sql);
    $loans_stmt->execute(['user_id' => $user_id]);
    $loans = $loans_stmt->fetchAll();

    // Calculate totals for summary badges
    $unpaid_borrowed = 0;
    $unpaid_lent = 0;
    foreach ($loans as $l) {
        $remaining = max(0, $l['amount'] - $l['total_paid']);
        if ($l['loan_type'] === 'Borrow') {
            $unpaid_borrowed += $remaining;
        } else {
            $unpaid_lent += $remaining;
        }
    }
} catch (PDOException $e) {
    $error = "Failed to load loans: " . $e->getMessage();
}

// 3. Fetch Recent Repayment History
try {
    $history_sql = "SELECT p.*, l.person_name, l.loan_type 
                    FROM Loan_Payment p
                    JOIN Loan l ON p.loan_id = l.loan_id
                    WHERE l.user_id = :user_id
                    ORDER BY p.payment_date DESC LIMIT 8";
    $history_stmt = $pdo->prepare($history_sql);
    $history_stmt->execute(['user_id' => $user_id]);
    $payments = $history_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load payment history: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">🤝 Informal Debt & Loan Tracker</h2>
            <p class="text-muted mb-0">Keep a reliable ledger of money borrowed from or lent to peers and roommates.</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex gap-2">
            <span class="badge bg-danger fs-6 py-2 px-3 shadow-sm">
                You Owe: ৳ <?php echo number_format($unpaid_borrowed, 2); ?>
            </span>
            <span class="badge bg-success fs-6 py-2 px-3 shadow-sm">
                Owed to You: ৳ <?php echo number_format($unpaid_lent, 2); ?>
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
    <!-- Left Column: Action Forms -->
    <div class="col-lg-4">
        <!-- Form 1: Record New Borrow/Lend -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="mb-0 fw-bold">➕ Record New Debt / Loan</h5>
            </div>
            <div class="card-body p-4">
                <form action="index.php" method="POST">
                    <input type="hidden" name="action" value="create_loan">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Person's Name <span class="text-danger">*</span></label>
                        <input type="text" name="person_name" class="form-control" placeholder="e.g., Rakib" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="loan_type" class="form-select" required>
                                <option value="Borrow" selected>I Borrowed (-)</option>
                                <option value="Lend">I Lent (+)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Amount (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="500.00" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="borrow_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Due Date (Optional)</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Note / Reason</label>
                        <input type="text" name="note" class="form-control" placeholder="e.g., Canteen bill, bus fare">
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Save Record</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form 2: Add Installment / Repayment -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold">💸 Record Repayment</h5>
            </div>
            <div class="card-body p-4">
                <?php 
                    $active_loans = array_filter($loans, function($l) { return $l['status'] !== 'Completed'; });
                ?>
                <?php if (empty($active_loans)): ?>
                    <div class="text-center py-3 text-muted small">
                        No pending loans or debts to repay right now!
                    </div>
                <?php else: ?>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="add_payment">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Loan <span class="text-danger">*</span></label>
                            <select name="loan_id" class="form-select" required>
                                <option value="">Choose Record...</option>
                                <?php foreach ($active_loans as $l): ?>
                                    <?php 
                                        $type_tag = ($l['loan_type'] === 'Borrow') ? 'I Owe' : 'Owes Me';
                                        $remaining = $l['amount'] - $l['total_paid'];
                                    ?>
                                    <option value="<?php echo $l['loan_id']; ?>">
                                        <?php echo htmlspecialchars($l['person_name']); ?> [<?php echo $type_tag; ?>: ৳ <?php echo number_format($remaining, 0); ?> left]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Amount Paid <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="200.00" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="Cash" selected>Cash</option>
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Date Paid <span class="text-danger">*</span></label>
                            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Note (Optional)</label>
                            <input type="text" name="note" class="form-control" placeholder="e.g., Paid via bKash send money">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success fw-bold py-2">Record Installment</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Active Loans & Repayment History -->
    <div class="col-lg-8">
        <h5 class="fw-bold mb-3">📋 Active Debts & Loans</h5>
        
        <?php if (empty($loans)): ?>
            <div class="card shadow-sm border-0 rounded-3 mb-5">
                <div class="card-body text-center py-5 text-muted">
                    <div class="fs-1 mb-2">⚖️</div>
                    <p class="mb-0">No borrowing or lending records found.</p>
                    <small>Use the form on the left to track peer-to-peer debts!</small>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3 mb-5">
                <?php foreach ($loans as $l): ?>
                    <?php 
                        $is_borrow = $l['loan_type'] === 'Borrow';
                        $card_border = $is_borrow ? 'border-danger-subtle bg-danger-subtle' : 'border-success-subtle bg-success-subtle';
                        $badge_bg = $is_borrow ? 'bg-danger' : 'bg-success';
                        $type_text = $is_borrow ? 'I Borrowed from' : 'I Lent to';
                        
                        $percentage = ($l['amount'] > 0) ? ($l['total_paid'] / $l['amount']) * 100 : 0;
                        $remaining = max(0, $l['amount'] - $l['total_paid']);
                    ?>
                    <div class="col-md-6">
                        <div class="card shadow-sm border border-2 rounded-3 h-100 <?php echo ($l['status'] === 'Completed') ? 'border-secondary bg-light' : ''; ?>">
                            <div class="card-body p-4 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge <?php echo $badge_bg; ?>"><?php echo $type_text; ?></span>
                                        <span class="badge <?php echo ($l['status'] === 'Completed') ? 'bg-secondary' : 'bg-dark'; ?>">
                                            <?php echo htmlspecialchars($l['status']); ?>
                                        </span>
                                    </div>
                                    <h4 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($l['person_name']); ?></h4>
                                    <p class="small text-muted mb-3"><?php echo !empty($l['note']) ? htmlspecialchars($l['note']) : 'No details added.'; ?></p>

                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Paid: <strong class="text-success">৳ <?php echo number_format($l['total_paid'], 2); ?></strong></span>
                                        <span>Total: <strong class="text-dark">৳ <?php echo number_format($l['amount'], 2); ?></strong></span>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="progress mb-3" style="height: 8px;">
                                        <div class="progress-bar <?php echo ($l['status'] === 'Completed') ? 'bg-secondary' : 'bg-primary'; ?>" 
                                             role="progressbar" style="width: <?php echo min($percentage, 100); ?>%;"></div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                                    <span class="text-muted">
                                        📅 Due: <?php echo !empty($l['due_date']) ? date('d M, Y', strtotime($l['due_date'])) : 'No date set'; ?>
                                    </span>
                                    <span class="fw-bold <?php echo ($remaining == 0) ? 'text-success' : 'text-danger'; ?>">
                                        ৳ <?php echo number_format($remaining, 2); ?> Left
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Repayment Installments Table -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">📜 Recent Installment Ledger</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Person & Type</th>
                                <th>Method / Note</th>
                                <th class="text-end pe-4">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No repayment installments recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap small"><?php echo date('d M, Y', strtotime($p['payment_date'])); ?></td>
                                        <td>
                                            <strong class="text-dark"><?php echo htmlspecialchars($p['person_name']); ?></strong>
                                            <span class="badge bg-light text-dark border ms-1"><?php echo htmlspecialchars($p['loan_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info-subtle text-info-emphasis me-1"><?php echo htmlspecialchars($p['payment_method']); ?></span>
                                            <small class="text-muted"><?php echo !empty($p['note']) ? htmlspecialchars($p['note']) : ''; ?></small>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-success text-nowrap">
                                            ৳ <?php echo number_format($p['amount'], 2); ?>
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