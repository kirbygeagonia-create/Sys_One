<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($name) > 100) {
        $error = 'Name must be 100 characters or fewer.';
    } elseif (strlen($email) > 255) {
        $error = 'Email must be 255 characters or fewer.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        require_once __DIR__ . '/../config/database.php';

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);

            $userId = $pdo->lastInsertId();
            addCredits($pdo, $userId, 3, 'bonus', null, 'welcome', null, 'Welcome bonus credits');

            $_SESSION['user_id'] = $userId;
            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            setFlash('success', 'Welcome to SkillLoop! You received 3 free credits to get started.');
            header('Location: /pages/dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Sign Up'; require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Join SkillLoop</h1>
        <p class="auth-subtitle">Trade skills. Earn badges. Learn anything.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Juan Dela Cruz" value="<?= h($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters" oninput="checkPasswordStrength(this.value, 'passwordStrength')" required>
                <div class="password-strength" id="passwordStrength"></div>
                <p class="form-hint">Must be 8+ characters with 1 uppercase letter and 1 number.</p>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-footer-text">
            Already have an account? <a href="/auth/login.php">Login</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>