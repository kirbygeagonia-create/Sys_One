<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

// IP-based rate limiting: track attempts in database
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    require_once __DIR__ . '/../config/database.php';

    // Periodic cleanup of old attempt records
    if (mt_rand(1, 20) === 1) {
        $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute();
    }

    // Check IP-based attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $ipAttempts = (int)$stmt->fetchColumn();
    if ($ipAttempts >= 5) {
        $error = 'Too many login attempts from this IP. Please wait 15 minutes before trying again.';
    }
} catch (Exception $e) {
    // If DB fails, fall through to session-based rate limiting
}

// Session-based rate limiting (secondary layer)
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['login_lockout'])) $_SESSION['login_lockout'] = 0;

if ($_SESSION['login_lockout'] > time()) {
    $minutes = ceil(($_SESSION['login_lockout'] - time()) / 60);
    $error = "Too many login attempts. Try again in $minutes minute" . ($minutes > 1 ? 's' : '') . '.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    requireCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Successful login: clear all attempt records
            $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lockout'] = 0;
            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            header('Location: /pages/dashboard.php');
            exit;
        } else {
            // Record failed attempt in DB
            $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lockout'] = time() + 900; // 15 min lockout
                $error = 'Too many failed attempts. Please wait 15 minutes before trying again.';
            } else {
                $remaining = 5 - $_SESSION['login_attempts'];
                $error = "Invalid email or password. $remaining attempt" . ($remaining > 1 ? 's' : '') . " remaining.";
            }
        }
    }
}

$pageTitle = 'Login'; require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <h1>Welcome Back</h1>
        <p class="auth-subtitle">Log in to continue your skill journey.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-footer-text">
            <a href="/auth/forgot_password.php">Forgot password?</a>
        </p>
        <p class="auth-footer-text">
            Don't have an account? <a href="/auth/register.php">Sign Up</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>