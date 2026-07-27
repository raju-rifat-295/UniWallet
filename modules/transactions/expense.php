<?php
require_once '../../includes/auth_check.php'; // Middleware protection
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Handle New Expense Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id    = $_POST['category_id'];
    $amount         = trim($_POST['amount']);
    $expense_date   = $_POST['expense_date'];
    $payment_method = $_POST['payment_method'];
    $note           = trim($_POST['note']);

    if (empty($category_id) || empty($amount) || empty($expense_date) || empty($payment_method)) {
        $error = "Please fill in all required fields (Category, Amount, Date, and Payment Method).";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid positive amount.";
    } else {
        try {
            $sql = "INSERT INTO Expense (user_id, category_id, amount, expense_date, payment_method, note) 
                    VALUES (:user_id, :category_id, :amount, :expense_date, :payment_method, :note)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id'        => $user_id,
                'category_id'    => $category_id,
                'amount'         => $amount,
                'expense_date'   => $expense_date,
                'payment_method' => $payment_method,
                'note'           => $note
            ]);
            $success = "Expense recorded successfully!";
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

// 3. Fetch User's Expense History using a relational JOIN
try {
    $history_sql = "SELECT e.*, c.category_name 
                    FROM Expense e 
                    JOIN Category c ON e.category_id = c.category_id 
                    WHERE e.user_id = :user_id 
                    ORDER BY e.expense_date DESC, e.expense_id DESC";
    $history_stmt = $pdo->prepare($history_sql);
    $history_stmt->execute(['user_id' => $user_id]);
    $expenses = $history_stmt->fetchAll();

    // Calculate total expenses for display badge
    $total_expense = 0;
    foreach ($expenses as $exp) {
        $total_expense += $exp['amount'];
    }
} catch (PDOException $e) {
    $error = "Failed to load expense history: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">💸 Expense Tracker</h2>
            <p class="text-muted mb-0">Monitor your daily campus spending, food bills, tuition, and transport costs.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-danger fs-5 py-2 px-3 shadow-sm">
                Total Spent: ৳ <?php echo number_format($total_expense, 2); ?>
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
    <!-- Left Column: Add Expense Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="mb-0 fw-bold">➖ Record New Expense</h5>
            </div>
            <div class="card-body p-4">
                <form action="expense.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Expense Type</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="e.g., 150.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="bKash">bKash</option>
                            <option value="Nagad">Nagad</option>
                            <option value="Rocket">Rocket</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Debit/Credit Card">Debit/Credit Card</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date Spent <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Note / Remarks (Optional)</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="e.g., Lunch with friends at canteen..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger fw-bold py-2">Save Expense Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Expense History Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">📋 Recent Spending History</h5>
                <small class="text-muted">Showing all records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Category</th>
                                <th>Payment / Note</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expenses)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <div class="fs-1 mb-2">🛒</div>
                                        No expense records found yet. Use the form on the left to track your spending!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($expenses as $exp): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap">
                                            <?php echo date('d M, Y', strtotime($exp['expense_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <?php echo htmlspecialchars($exp['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                💳 <?php echo htmlspecialchars($exp['payment_method']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo !empty($exp['note']) ? htmlspecialchars($exp['note']) : ''; ?>
                                            </small>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-danger text-nowrap">
                                            - ৳ <?php echo number_format($exp['amount'], 2); ?>
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