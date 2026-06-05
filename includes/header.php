<?php
require_once __DIR__ . '/functions.php';
startSession();
$currentUser = null;
$unreadCount = 0;
$unreadMsgCount = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    $currentUser = getCurrentUser($pdo);
    $unreadCount = getUnreadCount($pdo, $_SESSION['user_id']);
    $unreadMsgCount = countUnreadMessages($pdo, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' - SkillLoop' : 'SkillLoop - Trade Skills, Earn Badges' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='14' fill='none' stroke='%23FF6B35' stroke-width='3'/><path d='M16 6v10l8 4' fill='none' stroke='%23FF6B35' stroke-width='2.5' stroke-linecap='round'/></svg>">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="/index.php" class="nav-logo">
                <span class="logo-icon"><i class="fas fa-sync"></i></span>
                <span class="logo-text">SkillLoop</span>
            </a>
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
            <ul class="nav-links" id="navLinks">
                <?php if ($currentUser): ?>
                    <li><a href="/pages/dashboard.php" class="<?= isActive('/pages/dashboard.php') ?>">Dashboard</a></li>
                    <li><a href="/pages/browse.php" class="<?= isActive('/pages/browse.php') ?>">Browse</a></li>
                    <li><a href="/pages/skills.php" class="<?= isActive('/pages/skills.php') ?>">My Skills</a></li>
                    <li><a href="/pages/sessions.php" class="<?= isActive('/pages/sessions.php') ?>">Sessions</a></li>
                    <li>
                        <a href="/pages/credits.php" class="credits-badge">
                            <i class="fas fa-coins"></i> <?= (int)($currentUser['credits'] ?? 0) ?> credits
                        </a>
                    </li>
                    <li class="notif-li">
                        <a href="/pages/sessions.php" class="message-badge">
                            <i class="fas fa-comment"></i>
                            <?php if ($unreadMsgCount > 0): ?>
                                <span class="message-count"><?= $unreadMsgCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="notif-li">
                        <a href="/pages/notifications.php" class="notif-bell">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notif-count"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-profile">
                        <a href="/pages/profile.php?id=<?= $currentUser['id'] ?>">
                            <i class="fas fa-user"></i> <?= h($currentUser['name']) ?>
                        </a>
                    </li>
                    <li>
                            <form method="POST" action="/auth/logout.php" class="inline">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline">Logout</button>
                            </form>
                        </li>
                <?php else: ?>
                    <li><a href="/index.php">Home</a></li>
                    <li><a href="/pages/browse.php">Browse</a></li>
                    <li><a href="/auth/login.php" class="btn btn-sm btn-outline">Login</a></li>
                    <li><a href="/auth/register.php" class="btn btn-sm btn-primary">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <main class="container main-content">
        <?= displayFlashes() ?>