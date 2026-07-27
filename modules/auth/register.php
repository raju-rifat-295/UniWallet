<?php
require_once '../../config/db.php';
require_once '../../includes/header.php';

// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /uniwallet/modules/dashboard/index.php");
    exit;
}

$error = '';
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $department = trim($_POST['department']);
    $semester   = trim($_POST['semester']);
    $dob        = !empty($_POST['dob']) ? $_POST['dob'] : null;

    // Basic Validation
    if (empty($student_id) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($department) || empty($semester)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // 1. Check if student_id or email already exists in the database
            $check_stmt = $pdo->prepare("SELECT user_id FROM User WHERE student_id = :student_id OR email = :email");
            $check_stmt->execute(['student_id' => $student_id, 'email' => $email]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = "An account with this Student ID or Email already exists!";
            } else {
                // 2. Hash the password securely
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // 3. Insert user into the database using a Prepared Statement
                $sql = "INSERT INTO User (student_id, first_name, last_name, email, password, department, semester, date_of_birth) 
                        VALUES (:student_id, :first_name, :last_name, :email, :password, :department, :semester, :dob)";
                
                $insert_stmt = $pdo->prepare($sql);
                $insert_stmt->execute([
                    'student_id' => $student_id,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'email'      => $email,
                    'password'   => $hashed_password,
                    'department' => $department,
                    'semester'   => $semester,
                    'dob'        => $dob
                ]);

                $success = "Registration successful! You can now login.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center mt-4">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0 fw-bold">🎓 Create UniWallet Account</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?> 
                        <a href="login.php" class="alert-link">Click here to Login</a>.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" placeholder="e.g., Raju Ahmed" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" placeholder="e.g., Rifat" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Student ID <span class="text-danger">*</span></label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g., 242-15-295" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">University Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="rifat295@diu.edu.bd" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department <span class="text-danger">*</span></label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <option value="CSE" selected>Computer Science and Engineering (CSE)</option>
                                <option value="SWE">Software Engineering (SWE)</option>
                                <option value="EEE">Electrical and Electronic Engineering (EEE)</option>
                                <option value="BBA">Business Administration (BBA)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                            <input type="text" name="semester" class="form-control" placeholder="e.g., Fall 2025" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Create a strong password" required minlength="6">
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Register Account</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <small class="text-muted">Already have an account? <a href="login.php" class="text-decoration-none fw-semibold">Login here</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>