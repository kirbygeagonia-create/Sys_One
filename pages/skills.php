<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$categories = getCategories($pdo);
$offeredSkills = getUserOfferedSkills($pdo, $userId);
$wantedSkills = getUserWantedSkills($pdo, $userId);

$pageTitle = 'My Skills'; require_once __DIR__ . '/../includes/header.php';
?>

<h1>My Skills</h1>
<p class="auth-subtitle mb-24">Manage what you teach and what you want to learn.</p>

<div class="grid-2">
    <!-- Skills I Offer -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-chalkboard-teacher"></i> I Can Teach</h2>
            <button class="btn btn-primary btn-sm" onclick="openModal('offerModal')">+ Add</button>
        </div>
        <?php if (empty($offeredSkills)): ?>
            <div class="empty-state">
                <p>No skills listed yet. Add what you can teach!</p>
            </div>
        <?php else: ?>
            <div class="flex flex-wrap gap-8">
                <?php foreach ($offeredSkills as $skill): ?>
                    <div class="skill-tag skill-tag-offer">
                        <i class="fas <?= h($skill['category_icon']) ?>"></i> <?= h($skill['skill_name']) ?>
                        <small>(<?= h($skill['proficiency']) ?>)</small>
                        <form method="POST" action="/actions/add_skill.php" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="type" value="offer">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="skill_id" value="<?= $skill['skill_id'] ?>">
                            <button type="submit" onclick="return confirm('Remove this skill?')" class="btn-text-icon text-error font-bold no-underline">&times;</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Skills I Want -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-bullseye"></i> I Want to Learn</h2>
            <button class="btn btn-primary btn-sm" onclick="openModal('wantModal')">+ Add</button>
        </div>
        <?php if (empty($wantedSkills)): ?>
            <div class="empty-state">
                <p>No skills listed yet. What do you want to learn?</p>
            </div>
        <?php else: ?>
            <div class="flex flex-wrap gap-8">
                <?php foreach ($wantedSkills as $skill): ?>
                    <div class="skill-tag skill-tag-want">
                        <i class="fas <?= h($skill['category_icon']) ?>"></i> <?= h($skill['skill_name']) ?>
                        <form method="POST" action="/actions/add_skill.php" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="type" value="want">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="skill_id" value="<?= $skill['skill_id'] ?>">
                            <button type="submit" onclick="return confirm('Remove this skill?')" class="btn-text-icon text-error font-bold no-underline">&times;</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Offer Modal -->
<div class="modal-overlay" id="offerModal">
    <div class="modal">
        <h2>Add a Skill You Can Teach</h2>
        <form method="POST" action="/actions/add_skill.php">
            <?= csrfField() ?>
            <input type="hidden" name="type" value="offer">
            <div class="form-group">
                <label for="offer_category">Category</label>
                <select id="offer_category" onchange="loadSkills('offer_category', 'offer_skill')">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="offer_skill">Skill</label>
                <select id="offer_skill" name="skill_id" required disabled>
                    <option value="">First select a category</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer_proficiency">Your Proficiency Level</label>
                <select id="offer_proficiency" name="proficiency">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate" selected>Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer_description">Description (optional)</label>
                <textarea id="offer_description" name="description" placeholder="What will you teach? Any prerequisites?"></textarea>
            </div>
            <div class="flex gap-8 justify-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('offerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Skill</button>
            </div>
        </form>
    </div>
</div>

<!-- Want Modal -->
<div class="modal-overlay" id="wantModal">
    <div class="modal">
        <h2>Add a Skill You Want to Learn</h2>
        <form method="POST" action="/actions/add_skill.php">
            <?= csrfField() ?>
            <input type="hidden" name="type" value="want">
            <div class="form-group">
                <label for="want_category">Category</label>
                <select id="want_category" onchange="loadSkills('want_category', 'want_skill')">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="want_skill">Skill</label>
                <select id="want_skill" name="skill_id" required disabled>
                    <option value="">First select a category</option>
                </select>
            </div>
            <div class="form-group">
                <label for="want_description">Why do you want to learn this? (optional)</label>
                <textarea id="want_description" name="description" placeholder="What's your goal? Any specific topics?"></textarea>
            </div>
            <div class="flex gap-8 justify-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('wantModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Skill</button>
            </div>
        </form>
    </div>
</div>

<!-- loadSkills() is defined globally in assets/js/script.js -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>