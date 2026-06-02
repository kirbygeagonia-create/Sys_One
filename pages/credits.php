<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);
$transactions = getCreditTransactions($pdo, $userId);

require_once __DIR__ . '/../includes/header.php';
?>

<h1>Credits</h1>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-value"><i class="fas fa-coins"></i> <?= (int)$user['credits'] ?></div>
        <div class="stat-label">Available Credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php
            $earned = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM credit_transactions WHERE user_id = ? AND type = 'earn'");
            $earned->execute([$userId]);
            echo '+' . (int)$earned->fetchColumn();
        ?></div>
        <div class="stat-label">Earned (Teaching)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php
            $spent = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM credit_transactions WHERE user_id = ? AND type = 'spend'");
            $spent->execute([$userId]);
            echo '-' . (int)$spent->fetchColumn();
        ?></div>
        <div class="stat-label">Spent (Learning)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php
            $count = $pdo->prepare("SELECT COUNT(*) FROM sessions s JOIN session_requests sr ON s.request_id = sr.id WHERE (sr.requester_id = ? OR sr.teacher_id = ?) AND s.status = 'completed'");
            $count->execute([$userId, $userId]);
            echo (int)$count->fetchColumn();
        ?></div>
        <div class="stat-label">Sessions Done</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Transaction History</h2>
    </div>
    <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <p>No transactions yet. Start teaching to earn credits!</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>With</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><?= date('M j, g:i A', strtotime($txn['created_at'])) ?></td>
                            <td>
                                <?php if ($txn['type'] === 'earn'): ?>
                                    <span class="text-earned">Earned</span>
                                <?php elseif ($txn['type'] === 'spend'): ?>
                                    <span class="text-spent">Spent</span>
                                <?php else: ?>
                                    <span class="text-bonus">Bonus</span>
                                <?php endif; ?>
                            </td>
                            <td class="font-bold">
                                <?php if ($txn['type'] === 'spend'): ?>
                                    -<?= (int)$txn['amount'] ?>
                                <?php else: ?>
                                    +<?= (int)$txn['amount'] ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $txn['counterparty_name'] ? h($txn['counterparty_name']) : '—' ?></td>
                            <td class="text-muted"><?= h($txn['description'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>