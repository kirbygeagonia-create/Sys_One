<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        require_once __DIR__ . '/../config/database.php';

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/auth/reset_password.php?token=' . $token;

            $emailBody = "
            <p>Hi,</p>
            <p>You requested a password reset for your SkillLoop account.</p>
            <p><a href=\"{$resetLink}\" style=\"color:#FF6B35\">Click here to reset your password</a></p>
            <p>This link expires in 1 hour. If you did not request this, ignore this email.</p>
            <p>&mdash; The SkillLoop Team</p>
            ";

            sendEmail($user['email'], 'Reset your SkillLoop password', $emailBody);
            $sent = true;
        } else {
            // Same message whether found or not — prevents email enumeration
            $sent = true;
        }
    }
}

$pageTitle = 'Forgot Password'; require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Forgot Password</h1>
        <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($sent): ?>
            <div class="alert alert-success">
                If an account with that email exists, you'll receive a reset link shortly.
                (In dev mode, the link appears in the flash message if the email is registered.)
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
        </form>

        <p class="auth-footer-text">
            <a href="/auth/login.php">Back to Login</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>