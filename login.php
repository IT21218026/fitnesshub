<?php
session_start();
require_once 'includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    try {
        // Debug information
        error_log("Login attempt - Username: $username, Role: $role");

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch();

        if ($user) {
            error_log("User found in database");
            if (password_verify($password, $user['password'])) {
                error_log("Password verified successfully");
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Debug information
                error_log("Session created - User ID: " . $user['user_id'] . ", Role: " . $user['role']);
                
                // Redirect based on role
                switch ($role) {
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                    case 'trainer':
                        header('Location: trainer/dashboard.php');
                        break;
                    case 'user':
                        header('Location: dashboard.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit();
            } else {
                error_log("Password verification failed");
                $error_message = "Invalid password.";
            }
        } else {
            error_log("User not found in database");
            $error_message = "Invalid username or role.";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pulse Fitness Hub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-dumbbell"></i> Pulse Fitness Hub
                </div>
                <h1 class="auth-title">Login</h1>
                <p class="auth-subtitle">Welcome back! Please login to your account.</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <div class="input-group">
                        <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                        <select class="form-control" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="user">Member</option>
                            <option value="trainer">Trainer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="register.php" class="auth-link">Register here</a></p>
            </div>
        </div>
    </div>
</body>
</html> 