<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count total sessions
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    WHERE sr.requester_id = ? OR sr.teacher_id = ?
");
$countStmt->execute([$userId, $userId]);
$totalSessions = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalSessions / $perPage));

// Get sessions with pagination
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
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $userId, $userId, $perPage, $offset]);
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

/**
 * Get session lifecycle step for progress indicator
 */
function getSessionStep($session, $userId) {
    if ($session['status'] === 'cancelled') return -1;
    if ($session['status'] === 'completed')  return 4;
    $isTeacher = $session['teacher_id'] == $userId;
    $myConfirmed = $isTeacher ? $session['teacher_confirmed'] : $session['requester_confirmed'];
    if ($myConfirmed) return 3;
    return 2;
}

$pageTitle = 'Sessions'; require_once __DIR__ . '/../includes/header.php';
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
        <table class="sessions-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Skill</th>
                    <th>With</th>
                    <th>Role</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Chat</th>
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
                        <td data-label="Date"><?= date('M j, g:i A', strtotime($session['scheduled_at'])) ?></td>
                        <td data-label="Skill">
                            <span class="skill-tag"><i class="fas <?= h($session['category_icon']) ?>"></i> <?= h($session['skill_name']) ?></span>
                        </td>
                        <td data-label="With"><?= h($otherName) ?></td>
                        <td data-label="Role"><?= $isTeacher ? '<i class="fas fa-chalkboard-teacher"></i> Teacher' : '<i class="fas fa-graduation-cap"></i> Learner' ?></td>
                        <td data-label="Duration"><?= (int)$session['duration'] ?> min</td>
                        <td data-label="Status">
                            <span class="status-badge status-<?= h($session['status']) ?>">
                                <?= ucfirst(h($session['status'])) ?>
                            </span>
                            <?php $step = getSessionStep($session, $userId); ?>
                            <?php if ($session['status'] === 'scheduled'): ?>
                            <div class="session-progress">
                                <div class="progress-step <?= $step >= 2 ? 'done' : '' ?>">
                                    <i class="fas fa-check-circle"></i><span>Accepted</span>
                                </div>
                                <div class="progress-line <?= $step >= 3 ? 'done' : '' ?>"></div>
                                <div class="progress-step <?= $step >= 3 ? 'done' : ($step >= 2 ? 'active' : '') ?>">
                                    <i class="fas fa-user-check"></i><span>Your Confirm</span>
                                </div>
                                <div class="progress-line <?= $step >= 4 ? 'done' : '' ?>"></div>
                                <div class="progress-step <?= $step >= 4 ? 'done' : '' ?>">
                                    <i class="fas fa-flag-checkered"></i><span>Complete</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Chat">
                            <?php if ($session['status'] === 'scheduled' || $session['status'] === 'completed'): ?>
                                <button class="btn btn-sm btn-outline" onclick="openModal('chatModal_<?= $session['id'] ?>')" title="Chat about this session">
                                    <i class="fas fa-comment"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-muted text-sm">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
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
                                    <button type="button" class="btn btn-danger btn-sm" onclick="showConfirm('Cancel this session?', function(){ this.closest('form').submit(); }.bind(this), 'Cancel Session', 'btn-danger')"><i class="fas fa-times"></i></button>
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

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Chat Modals -->
<?php foreach ($sessions as $session): ?>
    <?php if ($session['status'] === 'scheduled' || $session['status'] === 'completed'): ?>
        <div class="modal-overlay" id="chatModal_<?= $session['id'] ?>">
            <div class="modal chat-modal">
                <div class="flex justify-between items-center mb-16">
                    <h2><i class="fas fa-comment"></i> Chat — <?= h($session['skill_name']) ?></h2>
                    <button class="modal-close" onclick="closeModal('chatModal_<?= $session['id'] ?>')">&times;</button>
                </div>
                <p class="text-muted text-sm mb-16">With <?= h($session['teacher_id'] == $userId ? $session['requester_name'] : $session['teacher_name']) ?></p>

                <div class="chat-messages chat-msg-area">
                    <?php
                    $chatMsgs = getSessionMessages($pdo, $session['id'], $userId);
                    if (empty($chatMsgs)): ?>
                        <p class="text-muted text-sm text-center chat-empty">No messages yet. Start the conversation!</p>
                    <?php else: ?>
                        <?php foreach ($chatMsgs as $msg): ?>
                            <div class="chat-msg <?= $msg['sender_id'] == $userId ? 'chat-msg-self' : 'chat-msg-other' ?>">
                                <div class="chat-msg-bubble">
                                    <p class="chat-msg-text"><?= h($msg['message']) ?></p>
                                    <span class="chat-msg-time"><?= h($msg['sender_name']) ?>, <?= timeAgo($msg['created_at']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form class="flex gap-8" id="chatForm_<?= $session['id'] ?>"
                      onsubmit="sendChatMessage(event, <?= $session['id'] ?>, '<?= generateCsrfToken() ?>')">
                    <?= csrfField() ?>
                    <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                    <textarea name="message" placeholder="Type your message..." required
                              class="chat-input" id="chatInput_<?= $session['id'] ?>"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm chat-send-btn">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>