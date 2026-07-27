<?php
require_once '../../includes/auth_check.php'; // Protects page from unauthorized access
require_once '../../config/db.php';
require_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Handle New Income Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'];
    $amount      = trim($_POST['amount']);
    $income_date = $_POST['income_date'];
    $source      = trim($_POST['source']);
    $note        = trim($_POST['note']);

    if (empty($category_id) || empty($amount) || empty($income_date)) {
        $error = "Please fill in all required fields (Category, Amount, and Date).";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid positive amount.";
    } else {
        try {
            $sql = "INSERT INTO Income (user_id, category_id, amount, income_date, source, note) 
                    VALUES (:user_id, :category_id, :amount, :income_date, :source, :note)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id'     => $user_id,
                'category_id' => $category_id,
                'amount'      => $amount,
                'income_date' => $income_date,
                'source'      => $source,
                'note'        => $note
            ]);
            $success = "Income record added successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// 2. Fetch Income Categories for the Dropdown
try {
    $cat_stmt = $pdo->query("SELECT category_id, category_name FROM Category WHERE category_type = 'Income' ORDER BY category_name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Failed to load categories: " . $e->getMessage();
}

// 3. Fetch User's Income History using a relational JOIN
try {
    $history_sql = "SELECT i.*, c.category_name 
                    FROM Income i 
                    JOIN Category c ON i.category_id = c.category_id 
                    WHERE i.user_id = :user_id 
                    ORDER BY i.income_date DESC, i.income_id DESC";
    $history_stmt = $pdo->prepare($history_sql);
    $history_stmt->execute(['user_id' => $user_id]);
    $incomes = $history_stmt->fetchAll();

    // Calculate total income for display badge
    $total_income = 0;
    foreach ($incomes as $inc) {
        $total_income += $inc['amount'];
    }
} catch (PDOException $e) {
    $error = "Failed to load income history: " . $e->getMessage();
}
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h2 class="fw-bold mb-0">💰 Income Tracker</h2>
            <p class="text-muted mb-0">Record and monitor all your incoming funds, scholarships, and allowances.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <span class="badge bg-success fs-5 py-2 px-3 shadow-sm">
                Total Earned: ৳ <?php echo number_format($total_income, 2); ?>
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
    <!-- Left Column: Add Income Form -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-success text-white py-3">
                <h5 class="mb-0 fw-bold">➕ Add New Income</h5>
            </div>
            <div class="card-body p-4">
                <form action="income.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Income Source</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="e.g., 5000.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date Received <span class="text-danger">*</span></label>
                        <input type="date" name="income_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Specific Source (Optional)</label>
                        <input type="text" name="source" class="form-control" placeholder="e.g., DIU Merit Scholarship">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Note / Remarks (Optional)</label>
                        <textarea name="note" class="form-control" rows="2" placeholder="Any extra details..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success fw-bold py-2">Save Income Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Income History Table -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">📋 Recent Income History</h5>
                <small class="text-muted">Showing all records</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Category</th>
                                <th>Source / Note</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($incomes)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <div class="fs-1 mb-2">💸</div>
                                        No income records found yet. Use the form on the left to add your first income!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($incomes as $inc): ?>
                                    <tr>
                                        <td class="ps-4 text-nowrap">
                                            <?php echo date('d M, Y', strtotime($inc['income_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <?php echo htmlspecialchars($inc['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <?php echo !empty($inc['source']) ? htmlspecialchars($inc['source']) : '-'; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo !empty($inc['note']) ? htmlspecialchars($inc['note']) : ''; ?>
                                            </small>
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-success text-nowrap">
                                            + ৳ <?php echo number_format($inc['amount'], 2); ?>
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