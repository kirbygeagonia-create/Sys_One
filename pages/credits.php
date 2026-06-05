<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);
$transactions = getCreditTransactions($pdo, $userId);

$pageTitle = 'Credits'; require_once __DIR__ . '/../includes/header.php';
?>

<h1>Credits</h1>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-value"><i class="fas fa-coins"></i> <?= (int)$user['credits'] ?></div>
        <div class="stat-label">Available Credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-earned">+<?= getTotalEarned($pdo, $userId) ?></div>
        <div class="stat-label">Earned (Teaching)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-spent">-<?= getTotalSpent($pdo, $userId) ?></div>
        <div class="stat-label">Spent (Learning)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= getTotalSessions($pdo, $userId) ?></div>
        <div class="stat-label">Sessions Done</div>
    </div>
</div>

<div class="flex gap-8 mb-24">
    <button class="btn btn-primary" onclick="openModal('giftCreditsModal')"><i class="fas fa-gift"></i> Gift Credits</button>
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

<!-- Gift Credits Modal -->
<div class="modal-overlay" id="giftCreditsModal">
    <div class="modal">
        <h2><i class="fas fa-gift"></i> Gift Credits</h2>
        <form method="POST" action="/actions/gift_credits.php">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="recipient_id">Recipient User ID</label>
                <input type="number" id="recipient_id" name="recipient_id" placeholder="Enter the user's ID" min="1" required>
                <p class="form-hint">You can find a user's ID in their profile URL.</p>
            </div>
            <div class="form-group">
                <label for="amount">Credits to Gift</label>
                <input type="number" id="amount" name="amount" placeholder="How many credits?" min="1" required>
                <p class="form-hint">Your balance: <?= (int)$user['credits'] ?> credits</p>
            </div>
            <div class="flex gap-8 justify-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('giftCreditsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-gift"></i> Send Gift</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>