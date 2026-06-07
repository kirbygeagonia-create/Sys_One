<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$categories = getCategories($pdo);

// Filters + Pagination
$selectedCategory = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$selectedSkill = isset($_GET['skill_id']) ? (int)$_GET['skill_id'] : 0;
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Count query first
$countSql = "
    SELECT COUNT(DISTINCT u.id)
    FROM user_skills_offered uso
    JOIN users u ON uso.user_id = u.id
    JOIN skills s ON uso.skill_id = s.id
    JOIN skill_categories sc ON s.category_id = sc.id
    WHERE 1=1
";
$countParams = [];

if ($selectedSkill) {
    $countSql .= " AND uso.skill_id = ?";
    $countParams[] = $selectedSkill;
} elseif ($selectedCategory) {
    $countSql .= " AND s.category_id = ?";
    $countParams[] = $selectedCategory;
}

if ($search) {
    $countSql .= " AND (u.name LIKE ? OR s.name LIKE ? OR sc.name LIKE ?)";
    $st = "%$search%";
    $countParams[] = $st; $countParams[] = $st; $countParams[] = $st;
}

$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$totalResults = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalResults / $perPage));

// Build data query
$sql = "
    SELECT u.id, u.name, u.bio, u.location, u.credits, u.reputation, u.availability,
           GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS skill_names,
           GROUP_CONCAT(DISTINCT sc.icon ORDER BY sc.name SEPARATOR ' ') AS category_icons,
           GROUP_CONCAT(DISTINCT uso.proficiency ORDER BY uso.proficiency SEPARATOR ', ') AS proficiencies,
           GROUP_CONCAT(DISTINCT s.id ORDER BY s.name SEPARATOR ',') AS skill_ids
    FROM user_skills_offered uso
    JOIN users u ON uso.user_id = u.id
    JOIN skills s ON uso.skill_id = s.id
    JOIN skill_categories sc ON s.category_id = sc.id
    WHERE 1=1
";
$params = [];

if ($selectedSkill) {
    $sql .= " AND uso.skill_id = ?";
    $params[] = $selectedSkill;
} elseif ($selectedCategory) {
    $sql .= " AND s.category_id = ?";
    $params[] = $selectedCategory;
}

if ($search) {
    $sql .= " AND (u.name LIKE ? OR s.name LIKE ? OR sc.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " GROUP BY u.id ORDER BY u.reputation DESC, u.name LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// Get skills for selected category (for the filter dropdown)
if ($selectedCategory) {
    $skills = getSkillsByCategory($pdo, $selectedCategory);
} else {
    $skills = [];
}

$pageTitle = 'Find Teachers'; require_once __DIR__ . '/../includes/header.php';
?>

<h1>Find Teachers</h1>
<p class="auth-subtitle mb-24">Browse skilled people ready to teach you something new.</p>

<!-- Filters -->
<form method="GET" class="browse-controls">
    <div class="form-group">
        <label for="search">Search</label>
        <input type="text" id="search" name="search" placeholder="Skill, category, or name..." value="<?= h($search) ?>">
    </div>
    <div class="form-group">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $selectedCategory == $cat['id'] ? 'selected' : '' ?>>
                    <?= h($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="skill_id">Skill</label>
        <select id="skill_id" name="skill_id" onchange="this.form.submit()" <?= !$selectedCategory ? 'disabled' : '' ?>>
            <option value="">All Skills</option>
            <?php foreach ($skills as $skill): ?>
                <option value="<?= $skill['id'] ?>" <?= $selectedSkill == $skill['id'] ? 'selected' : '' ?>>
                    <?= h($skill['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group flex items-end">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($selectedCategory || $selectedSkill || $search): ?>
            <a href="/pages/browse.php" class="btn btn-outline ml-8">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Results -->
<?php if (empty($teachers)): ?>
    <div class="empty-state">
        <h3>No teachers found</h3>
        <p>Try changing your filters or check back later.</p>
    </div>
<?php else: ?>
    <div class="grid-2">
        <?php foreach ($teachers as $teacher): ?>
            <div class="card">
                <div class="flex justify-between items-start mb-12">
                    <div>
                        <a href="/pages/profile.php?id=<?= $teacher['id'] ?>" class="font-bold text-lg">
                            <?= h($teacher['name']) ?>
                        </a>
                        <?php if ($teacher['location']): ?>
                            <span class="text-muted text-sm"><i class="fas fa-location-dot"></i> <?= h($teacher['location']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($teacher['reputation'] > 0): ?>
                        <span class="skill-tag"><i class="fas fa-star"></i> <?= number_format($teacher['reputation'], 1) ?></span>
                    <?php endif; ?>
                </div>

                <div class="mb-8 flex flex-wrap gap-4">
                    <?php
                    $skillNames = explode(', ', $teacher['skill_names']);
                    $icons = explode(' ', $teacher['category_icons']);
                    $profs = explode(', ', $teacher['proficiencies']);
                    foreach ($skillNames as $si => $sn):
                        $icon = $icons[$si] ?? 'fa-star';
                        $prof = $profs[$si] ?? '';
                    ?>
                        <span class="skill-tag skill-tag-offer">
                            <i class="fas <?= h($icon) ?>"></i> <?= h($sn) ?>
                            <?php if ($prof): ?><small>(<?= h($prof) ?>)</small><?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php if ($teacher['availability']): ?>
                    <p class="text-sm text-muted mb-8">
                        <i class="fas fa-clock"></i> <?= h($teacher['availability']) ?>
                    </p>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $teacher['id']): ?>
                    <?php
                    $skillIdArr   = explode(',', $teacher['skill_ids'] ?? '');
                    $skillNameArr = explode(', ', $teacher['skill_names'] ?? '');
                    $totalTeacherSkills = count(array_filter($skillIdArr));
                    ?>
                    <?php if ($totalTeacherSkills === 1): ?>
                        <a href="/actions/request_session.php?teacher_id=<?= $teacher['id'] ?>&skill_id=<?= (int)$skillIdArr[0] ?>"
                           class="btn btn-primary btn-sm">Request Session</a>
                    <?php else: ?>
                        <form method="GET" action="/actions/request_session.php" class="flex gap-8 items-center flex-wrap">
                            <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                            <select name="skill_id" class="form-select-inline" required>
                                <?php foreach ($skillIdArr as $si => $sid): ?>
                                    <?php if ((int)$sid > 0): ?>
                                    <option value="<?= (int)$sid ?>">
                                        <?= h($skillNameArr[$si] ?? '') ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">Request</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>