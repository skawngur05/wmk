<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/auth.php';

// Ensure PDO is available
global $pdo;
if (!isset($pdo)) {
    $pdo = $GLOBALS['pdo'] ?? null;
}

if (!$pdo) {
    die("Database connection error. Please try again later.");
}

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (login($username, $password, $pdo)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$page_title = 'Login - Lead Management System';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Favicon -->
    <link rel="icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-32x32.png" sizes="32x32" />
    <link rel="icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-192x192.png" sizes="192x192" />
    <link rel="apple-touch-icon" href="https://wrapmykitchen.com/wp-content/uploads/2023/04/cropped-WMK-FAVICON-180x180.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-lg mt-5">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <img src="images/wmk-wh.png" alt="Wrap My Kitchen Logo" class="mb-3" style="max-width: 150px; height: auto;">
                            <h3>Welcome back!</h3>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <div class="position-relative flex-grow-1">
                                        <input type="password" class="form-control" id="password" name="password" required style="padding-right: 40px;">
                                        <i class="fas fa-eye position-absolute" id="togglePassword" style="right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #6c757d;"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Default users: kim, patrick, lina (password: password)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
