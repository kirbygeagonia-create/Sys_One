<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

// Get all sessions where user is involved
$stmt = $pdo->prepare("
    SELECT s.*, sr.requester_id, sr.teacher_id, sr.message, sr.skill_id,
           sk.name AS skill_name, sc.name AS category_name, sc.icon AS category_icon,
           req.name AS requester_name, t.name AS teacher_name,
           s2r.rating AS my_rating
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN skill_categories sc ON sk.category_id = sc.id
    JOIN users req ON sr.requester_id = req.id
    JOIN users t ON sr.teacher_id = t.id
    LEFT JOIN session_reviews s2r ON s2r.session_id = s.id AND s2r.reviewer_id = ?
    WHERE sr.requester_id = ? OR sr.teacher_id = ?
    ORDER BY s.scheduled_at DESC
");
$stmt->execute([$userId, $userId, $userId]);
$sessions = $stmt->fetchAll();

// Separate pending requests (where user is teacher)
$stmt = $pdo->prepare("
    SELECT sr.*, u.name AS requester_name, sk.name AS skill_name, sc.icon AS category_icon
    FROM session_requests sr
    JOIN users u ON sr.requester_id = u.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN skill_categories sc ON sk.category_id = sc.id
    WHERE sr.teacher_id = ? AND sr.status = 'pending'
    ORDER BY sr.created_at DESC
");
$stmt->execute([$userId]);
$pendingRequests = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<h1>Sessions</h1>

<!-- Pending Requests (teacher only) -->
<?php if (!empty($pendingRequests)): ?>
    <div class="card mb-24">
        <div class="card-header">
            <h2><i class="fas fa-envelope"></i> Pending Requests</h2>
        </div>
        <?php foreach ($pendingRequests as $req): ?>
            <div class="flex justify-between items-center border-bottom item-row">
                <div>
                    <strong><?= h($req['requester_name']) ?></strong> wants to learn
                    <span class="skill-tag"><i class="fas <?= h($req['category_icon']) ?>"></i> <?= h($req['skill_name']) ?></span>
                    <br>
                    <small class="text-muted"><?= h(substr($req['message'], 0, 100)) ?></small>
                </div>
                <div class="flex gap-8">
                    <form method="POST" action="/actions/respond_request.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="btn btn-success btn-sm">Accept</button>
                    </form>
                    <form method="POST" action="/actions/respond_request.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <input type="hidden" name="action" value="decline">
                        <button type="submit" class="btn btn-danger btn-sm">Decline</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- All Sessions -->
<?php if (empty($sessions)): ?>
    <div class="empty-state">
        <h3>No sessions yet</h3>
        <p>Browse teachers and request your first session!</p>
        <a href="/pages/browse.php" class="btn btn-primary mt-12">Find Teachers</a>
    </div>
<?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Skill</th>
                    <th>With</th>
                    <th>Role</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $isTeacher = $session['teacher_id'] == $userId;
                    $otherName = $isTeacher ? $session['requester_name'] : $session['teacher_name'];
                    ?>
                    <tr>
                        <td><?= date('M j, g:i A', strtotime($session['scheduled_at'])) ?></td>
                        <td>
                            <span class="skill-tag"><i class="fas <?= h($session['category_icon']) ?>"></i> <?= h($session['skill_name']) ?></span>
                        </td>
                        <td><?= h($otherName) ?></td>
                        <td><?= $isTeacher ? '<i class="fas fa-chalkboard-teacher"></i> Teacher' : '<i class="fas fa-graduation-cap"></i> Learner' ?></td>
                        <td><?= (int)$session['duration'] ?> min</td>
                        <td>
                            <span class="status-badge status-<?= h($session['status']) ?>">
                                <?= ucfirst(h($session['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($session['status'] === 'scheduled' && !$session['my_rating']): ?>
                                <div class="flex gap-4">
                                <form method="POST" action="/actions/complete_session.php" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <?php
                                    $userConfirmed = $isTeacher ? $session['teacher_confirmed'] : $session['requester_confirmed'];
                                    ?>
                                    <?php if (!$userConfirmed): ?>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> Complete</button>
                                    <?php else: ?>
                                        <span class="text-muted text-xs">Waiting...</span>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" action="/actions/cancel_session.php" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this session?')"><i class="fas fa-times"></i></button>
                                </form>
                                </div>
                            <?php elseif ($session['status'] === 'completed' && !$session['my_rating']): ?>
                                <a href="/actions/submit_review.php?session_id=<?= $session['id'] ?>" class="btn btn-primary btn-sm">Rate & Badge</a>
                            <?php elseif ($session['status'] === 'completed'): ?>
                                <span class="text-muted text-sm"><i class="fas fa-star"></i> Reviewed</span>
                                <?php elseif ($session['status'] === 'cancelled'): ?>
                                <span class="text-muted text-sm">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>