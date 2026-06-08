<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$role = '';
$username = '';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields.';
    } else {
        $pdo = getDBConnection();
        if (loginUser($pdo, $username, $password, $role)) {
            redirect(BASE_URL . '/dashboard.php');
        } else {
            $error = 'Invalid username, password, or role selection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= LOGO_PATH ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--login">
                <h1><?= APP_NAME ?></h1>
                <p><?= APP_INSTITUTION ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= sanitize($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="role">Login As</label>
                    <select name="role" id="role" class="form-control" required>
                        <option value="" <?= $role === '' ? 'selected' : '' ?>>Select Role</option>
                        <option value="super_admin" <?= $role === 'super_admin' ? 'selected' : '' ?>>Super Administrator</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
                        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="faculty" <?= $role === 'faculty' ? 'selected' : '' ?>>Faculty</option>
                        <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" value="<?= sanitize($username) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" value="<?= sanitize($password) ?>" required>
                </div>

                <div class="btn-group" style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Login</button>
                    <a href="<?= BASE_URL ?>/" class="btn btn-secondary">Back to Home</a>
                </div>
                <p style="text-align:center;margin-top:16px;">
                    <a href="<?= BASE_URL ?>/forgot_password.php">Forgot password?</a>
                </p>
            </form>
        </div>
    </div>
</div>
</body>
</html>
