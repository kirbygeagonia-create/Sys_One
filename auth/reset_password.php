<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) {
    header('Location: /auth/login.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Verify token
$stmt = $pdo->prepare("
    SELECT prt.*, u.email FROM password_reset_tokens prt
    JOIN users u ON prt.user_id = u.id
    WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    setFlash('error', 'Invalid or expired reset token. Request a new one.');
    header('Location: /auth/forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $reset['user_id']]);
        $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")->execute([$reset['id']]);

        setFlash('success', 'Password reset successfully! You can now log in.');
        header('Location: /auth/login.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Reset Password</h1>
        <p class="auth-subtitle">Choose a new password for your account.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>