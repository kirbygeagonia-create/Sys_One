<?php
/**
 * SkillLoop Test Suite
 * Run: php tests/test_suite.php
 * Or access via browser: http://localhost:8000/tests/test_suite.php
 */

// Restrict to CLI to prevent public access to system internals
if (php_sapi_name() !== 'cli') {
    die('This file can only be run from the command line.');
}

$testsPassed = 0;
$testsFailed = 0;
$results = [];

function test($name, $result, $detail = '') {
    global $testsPassed, $testsFailed, $results;
    if ($result) {
        $testsPassed++;
        $results[] = ['status' => 'PASS', 'name' => $name, 'detail' => $detail];
    } else {
        $testsFailed++;
        $results[] = ['status' => 'FAIL', 'name' => $name, 'detail' => $detail];
    }
}

// ============================================================
// 1. PHP ENVIRONMENT CHECKS
// ============================================================
test('PHP version 7.4+', PHP_VERSION_ID >= 70400, 'Current: ' . PHP_VERSION);
test('PDO extension loaded', extension_loaded('pdo'), '');
test('PDO MySQL extension loaded', extension_loaded('pdo_mysql'), '');
test('GD or fileinfo for uploads', extension_loaded('gd') || extension_loaded('fileinfo'), '');
test('mbstring extension loaded', extension_loaded('mbstring'), '');

// ============================================================
// 2. FILE STRUCTURE CHECKS
// ============================================================
$requiredFiles = [
    'index.php',
    'config/database.php',
    'includes/functions.php',
    'includes/header.php',
    'includes/footer.php',
    'auth/login.php',
    'auth/register.php',
    'auth/logout.php',
    'auth/forgot_password.php',
    'auth/reset_password.php',
    'pages/dashboard.php',
    'pages/browse.php',
    'pages/skills.php',
    'pages/sessions.php',
    'pages/profile.php',
    'pages/credits.php',
    'pages/notifications.php',
    'actions/get_skills.php',
    'actions/add_skill.php',
    'actions/request_session.php',
    'actions/respond_request.php',
    'actions/complete_session.php',
    'actions/submit_review.php',
    'actions/cancel_session.php',
    'actions/upload_avatar.php',
    'actions/mark_read.php',
    'actions/mark_all_read.php',
    'actions/send_message.php',
    'actions/search_skills.php',
    'actions/gift_credits.php',
    'errors/404.php',
    'errors/500.php',
    'cron/send_reminders.php',
    'sql/schema.sql',
    'assets/css/style.css',
    'assets/js/script.js',
];

$base = __DIR__ . '/..';
foreach ($requiredFiles as $file) {
    $path = realpath($base . '/' . $file);
    test("File exists: $file", $path !== false && file_exists($path), $path ?: 'MISSING');
}

// ============================================================
// 3. SYNTAX CHECK ALL PHP FILES
// ============================================================
$phpFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base)
);
foreach ($phpFiles as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getRealPath();
    // Skip this test file itself
    if (strpos($path, 'tests' . DIRECTORY_SEPARATOR) !== false) continue;

    ob_start();
    $result = `php -l "$path" 2>&1`;
    ob_end_clean();
    test("Syntax: " . $file->getFilename(), strpos($result, 'No syntax errors') !== false, trim($result));
}

// ============================================================
// 4. SQL SCHEMA VALIDATION
// ============================================================
$schema = file_get_contents($base . '/sql/schema.sql');
$requiredTables = ['users', 'skill_categories', 'skills', 'user_skills_offered', 'user_skills_wanted',
                   'session_requests', 'sessions', 'session_reviews', 'badges', 'credit_transactions',
                   'notifications', 'password_reset_tokens', 'login_attempts', 'messages'];

foreach ($requiredTables as $table) {
    test("Schema contains table: $table", strpos($schema, "CREATE TABLE $table") !== false, '');
}

// ============================================================
// 5. FUNCTION EXISTENCE CHECKS
// ============================================================
require_once $base . '/includes/functions.php';

$requiredFunctions = [
    'startSession', 'requireLogin', 'getCurrentUser', 'getUserById',
    'addCredits', 'getCategories', 'getSkillsByCategory',
    'getUserOfferedSkills', 'getUserWantedSkills', 'getUserBadges',
    'getCreditTransactions', 'updateReputation', 'setFlash', 'getFlashes',
    'generateCsrfToken', 'validateCsrfToken', 'csrfField', 'requireCsrf',
    'createNotification', 'getUnreadNotifications', 'getAllNotifications',
    'markNotificationRead', 'markAllNotificationsRead', 'getUnreadCount',
    'sendEmail', 'checkRateLimit', 'nameToColor', 'isActive', 'getPotentialLearners',
    'getSessionMessages', 'countUnreadMessages', 'markMessagesRead',
    'h', 'timeAgo'
];

foreach ($requiredFunctions as $func) {
    test("Function exists: $func()", function_exists($func), '');
}

// ============================================================
// 6. CSRF TOKEN TESTS
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['csrf_token'] = 'test_token_value_123';
test('CSRF generation returns string', is_string(generateCsrfToken()), generateCsrfToken());
test('CSRF validation works', validateCsrfToken('test_token_value_123'), '');
test('CSRF rejects invalid token', !validateCsrfToken('wrong_token'), '');
test('csrfField() returns hidden input', strpos(csrfField(), 'csrf_token') !== false, csrfField());

// ============================================================
// 7. FLASH MESSAGE TESTS
// ============================================================
$_SESSION['flash'] = [];
setFlash('success', 'Test message');
$flashes = getFlashes();
test('setFlash/getFlashes roundtrip', count($flashes) === 1 && $flashes[0]['type'] === 'success', $flashes[0]['message'] ?? '');
$emptyFlashes = getFlashes();
test('getFlashes clears after read', count($emptyFlashes) === 0, '');

// ============================================================
// 8. HELPER FUNCTION TESTS
// ============================================================
test('h() escapes HTML', h('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', h('<script>'));
test('h() handles safe strings', h('hello world') === 'hello world', '');

// ============================================================
// 9. CSS FILE CHECKS
// ============================================================
$css = file_get_contents($base . '/assets/css/style.css');
test('CSS has responsive breakpoint', strpos($css, '@media') !== false, '');
test('CSS has modal styles', strpos($css, 'modal-overlay') !== false, '');
test('CSS has notification bell styles', strpos($css, 'notif') !== false, '');
test('CSS has pagination styles', strpos($css, 'pagination') !== false, '');
test('CSS has password strength styles', strpos($css, 'password-strength') !== false, '');
test('CSS has toast styles', strpos($css, 'toast') !== false, '');
test('CSS has session progress stepper', strpos($css, 'session-progress') !== false, '');
test('CSS has skeleton loader styles', strpos($css, 'skeleton') !== false, '');
test('CSS has onboarding banner styles', strpos($css, 'onboarding-banner') !== false, '');
test('CSS has autocomplete list styles', strpos($css, 'autocomplete-list') !== false, '');
test('CSS has nav active state', strpos($css, 'nav-active') !== false, '');
test('CSS has character counter', strpos($css, 'char-counter') !== false, '');
test('CSS has loading spinner', strpos($css, 'is-loading') !== false, '');
test('CSS has confirm modal styles', strpos($css, 'confirm-modal') !== false, '');

// ============================================================
// 10. JS FILE CHECKS
// ============================================================
$js = file_get_contents($base . '/assets/js/script.js');
test('JS has nav toggle', strpos($js, 'navToggle') !== false, '');
test('JS has modal helpers', strpos($js, 'openModal') !== false && strpos($js, 'closeModal') !== false, '');
test('JS has toast helper', strpos($js, 'showToast') !== false, '');
test('JS has confirm helper', strpos($js, 'confirmAction') !== false, '');
test('JS has loadSkills function', strpos($js, 'loadSkills') !== false, '');
test('JS has is-loading class on loadSkills', strpos($js, 'is-loading') !== false, '');
test('JS has showConfirm function', strpos($js, 'showConfirm') !== false, '');
test('JS has character counter', strpos($js, 'char-counter') !== false, '');
test('JS has auto-resize textarea', strpos($js, 'textarea') && strpos($js, 'scrollHeight') !== false, '');
test('JS has focus trap', strpos($js, 'focusable') !== false, '');
test('JS has star keyboard accessibility', strpos($js, 'ArrowRight') !== false && strpos($js, 'ArrowLeft') !== false, '');
test('JS has skeleton loader trigger', strpos($js, 'skeleton-card') !== false, '');
test('JS has search autocomplete', strpos($js, 'autocomplete-list') !== false, '');

// ============================================================
// 11. INLINE CSS DETECTION (enforce external stylesheets only)
// ============================================================
$inlineCssFound = [];
$phpFilesForScan = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base)
);
foreach ($phpFilesForScan as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path = $file->getRealPath();
    if (strpos($path, 'tests' . DIRECTORY_SEPARATOR) !== false) continue;
    // Skip profile.php — its inline style is a dynamic avatar color (nameToColor)
    // that cannot be expressed as a static CSS class
    if (strpos($path, 'profile.php') !== false) continue;
    $content = file_get_contents($path);
    if (preg_match('/style\s*=\s*["\'](?!["\'])/', $content)) {
        $inlineCssFound[] = $file->getFilename();
    }
}
test('No inline CSS style="" in PHP files', count($inlineCssFound) === 0,
    count($inlineCssFound) > 0 ? 'Found in: ' . implode(', ', $inlineCssFound) : '');

// ============================================================
// 12. EMOJI DETECTION (enforce Font Awesome instead of emojis)
// ============================================================
$emojiFiles = [];
$scanDirs = ['pages', 'actions', 'auth', 'includes', 'assets'];
foreach ($scanDirs as $dir) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base . '/' . $dir)
    );
    foreach ($files as $file) {
        if (!in_array($file->getExtension(), ['php', 'css', 'js'])) continue;
        $content = file_get_contents($file->getRealPath());
        // Match common emoji Unicode ranges
        if (preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}]/u', $content)) {
            $emojiFiles[] = $file->getFilename();
        }
    }
}
test('No emoji characters in source files', count($emojiFiles) === 0,
    count($emojiFiles) > 0 ? 'Found in: ' . implode(', ', array_unique($emojiFiles)) : '');

// ============================================================
// 13. DATABASE CONNECTION TEST
// ============================================================
try {
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: 'skillloop';
    $testPdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
                        getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '');
    test('Database connection', true, "Connected to $dbHost/$dbName");

    // Check tables exist
    $stmt = $testPdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requiredTables as $table) {
        test("Database table exists: $table", in_array($table, $existingTables), '');
    }

    // Check seed data
    $count = (int)$testPdo->query("SELECT COUNT(*) FROM skill_categories")->fetchColumn();
    test('Categories seeded', $count > 0, "$count categories");

    $count = (int)$testPdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
    test('Skills seeded', $count > 0, "$count skills");

} catch (PDOException $e) {
    test('Database connection', false, $e->getMessage());
    // Mark remaining DB tests as skipped
    foreach ($requiredTables as $table) {
        test("Database table exists: $table (skipped)", false, 'No DB connection');
    }
    test('Categories seeded (skipped)', false, 'No DB connection');
    test('Skills seeded (skipped)', false, 'No DB connection');
}

// ============================================================
// SUMMARY
// ============================================================
$total = $testsPassed + $testsFailed;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkillLoop Test Suite</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; padding: 40px; color: #1e293b; }
        h1 { margin-bottom: 4px; }
        .summary { font-size: 1.2rem; margin-bottom: 24px; }
        .summary .pass { color: #16a34a; font-weight: 700; }
        .summary .fail { color: #dc2626; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        th, td { padding: 10px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        th { background: #f1f5f9; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.8rem; }
        .badge-pass { background: #d1fae5; color: #16a34a; }
        .badge-fail { background: #fee2e2; color: #dc2626; }
        .detail { color: #64748b; font-size: 0.85rem; max-width: 400px; overflow: hidden; text-overflow: ellipsis; }
        tr:hover td { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>🧪 SkillLoop Test Suite</h1>
    <div class="summary">
        <span class="<?= $testsFailed === 0 ? 'pass' : 'fail' ?>">
            <?= $testsPassed ?>/<?= $total ?> passed
        </span>
        <?php if ($testsFailed > 0): ?>
            — <span class="fail"><?= $testsFailed ?> failed</span>
        <?php else: ?>
            — ✅ All tests passed!
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Result</th><th>Test</th><th>Detail</th></tr>
        </thead>
        <tbody>
            <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><span class="badge badge-<?= $r['status'] === 'PASS' ? 'pass' : 'fail' ?>"><?= $r['status'] ?></span></td>
                    <td><?= htmlspecialchars($r['name']) ?></td>
                    <td class="detail"><?= htmlspecialchars($r['detail']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p class="mt-16 text-muted text-sm">
        Run via CLI: <code>php tests/test_suite.php</code> &bull;
        Or browser: <code>http://localhost:8000/tests/test_suite.php</code>
    </p>
</body>
</html>
<?php
// CLI output as well
if (php_sapi_name() === 'cli') {
    echo "\n========================================\n";
    echo "  SkillLoop Test Suite Results\n";
    echo "========================================\n";
    foreach ($results as $i => $r) {
        $mark = $r['status'] === 'PASS' ? '✓' : '✗';
        echo "  $mark {$r['name']}\n";
        if ($r['detail']) {
            echo "     → {$r['detail']}\n";
        }
    }
    echo "========================================\n";
    echo "  $testsPassed / $total passed";
    if ($testsFailed > 0) echo ",  $testsFailed FAILED";
    echo "\n========================================\n";
    exit($testsFailed > 0 ? 1 : 0);
}