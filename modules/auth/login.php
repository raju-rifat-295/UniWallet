<?php
require_once '../../config/db.php';
require_once '../../includes/header.php';

// If the user is already logged in, redirect them to the dashboard immediately
if (isset($_SESSION['user_id'])) {
    header("Location: /uniwallet/modules/dashboard/index.php");
    exit;
}

$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id']); // Can be either Email or Student ID
    $password = $_POST['password'];

    if (empty($login_id) || empty($password)) {
        $error = "Please enter both your Student ID/Email and Password.";
    } else {
        try {
            // Check for the user by either Student ID OR Email using unique placeholders!
            $stmt = $pdo->prepare("SELECT * FROM User WHERE student_id = :student_id OR email = :email");
            $stmt->execute([
                'student_id' => $login_id,
                'email'      => $login_id
            ]);
            $user = $stmt->fetch();

            // Verify the user exists AND the password matches the stored hash
            if ($user && password_verify($password, $user['password'])) {
                // Password is correct! Set session variables
                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name']  = $user['last_name'];
                $_SESSION['email']      = $user['email'];

                // Redirect to the dashboard
                header("Location: /uniwallet/modules/dashboard/index.php");
                exit;
            } else {
                $error = "Invalid Student ID/Email or Password.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow border-0 rounded-3">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0 fw-bold">🔐 UniWallet Login</h4>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Student ID or University Email</label>
                        <input type="text" name="login_id" class="form-control" placeholder="e.g., 242-15-295 or email@diu.edu.bd" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary fw-bold py-2">Login to Dashboard</button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <small class="text-muted">Don't have an account yet? <a href="register.php" class="text-decoration-none fw-semibold">Register here</a></small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>