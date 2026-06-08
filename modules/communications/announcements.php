<?php
$pageTitle = 'Announcements';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'faculty', 'student']);

$pdo = getDBConnection();
$canPost = in_array($_SESSION['user_role'], ['super_admin', 'admin', 'staff'], true);
$canHomepage = canManageHomepage();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canPost) {
    $showHomepage = $canHomepage && !empty($_POST['show_on_homepage']) ? 1 : 0;
    $homepageCategory = $canHomepage ? ($_POST['homepage_category'] ?? 'general') : 'general';
    $homepageSort = $canHomepage ? (int) ($_POST['homepage_sort'] ?? 0) : 0;

    $stmt = $pdo->prepare("INSERT INTO announcements (title, message, target_audience, show_on_homepage, homepage_category, homepage_sort, posted_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        trim($_POST['title']),
        trim($_POST['message']),
        $_POST['target_audience'],
        $showHomepage,
        $homepageCategory,
        $homepageSort,
        $_SESSION['user_id']
    ]);
    setFlash('success', 'Announcement posted successfully.');
    redirect(BASE_URL . '/modules/communications/announcements.php');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $canPost) {
    $pdo->prepare("UPDATE announcements SET is_active = 0 WHERE id = ?")->execute([$_GET['delete']]);
    setFlash('success', 'Announcement removed.');
    redirect(BASE_URL . '/modules/communications/announcements.php');
}

if (isset($_GET['homepage_toggle']) && is_numeric($_GET['homepage_toggle']) && $canHomepage) {
    $pdo->prepare("UPDATE announcements SET show_on_homepage = NOT show_on_homepage WHERE id = ?")->execute([$_GET['homepage_toggle']]);
    setFlash('success', 'Homepage visibility updated.');
    redirect(BASE_URL . '/modules/communications/announcements.php');
}

$role = $_SESSION['user_role'];
$audienceFilter = match($role) {
    'student' => "AND (target_audience IN ('all', 'students'))",
    'faculty' => "AND (target_audience IN ('all', 'faculty', 'staff'))",
    'staff' => "AND (target_audience IN ('all', 'staff', 'faculty'))",
    default => ''
};

$announcements = $pdo->query("
    SELECT a.*, u.full_name as author
    FROM announcements a
    LEFT JOIN users u ON a.posted_by = u.id
    WHERE a.is_active = 1 $audienceFilter
    ORDER BY a.created_at DESC
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($canPost): ?>
<div class="card">
    <div class="card-header"><h3>Post Announcement</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group" style="flex:2;">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Target Audience *</label>
                    <select name="target_audience" class="form-control" required>
                        <option value="all">Everyone</option>
                        <option value="students">Students</option>
                        <option value="staff">Staff</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Message *</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <?php if ($canHomepage): ?>
            <div class="form-section-title">Public Homepage</div>
            <div class="form-row">
                <div class="form-group">
                    <label><input type="checkbox" name="show_on_homepage" value="1"> Show on public homepage</label>
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Visible to visitors before they log in.</p>
                </div>
                <div class="form-group">
                    <label>Homepage Category</label>
                    <select name="homepage_category" class="form-control">
                        <option value="general">General Announcement</option>
                        <option value="results">Results</option>
                        <option value="exam_calendar">Exam Calendar</option>
                        <option value="intake">Admissions / Intake</option>
                        <option value="portal">Portal Access (Login CTA)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Display Order</label>
                    <input type="number" name="homepage_sort" class="form-control" value="0" min="0" step="1">
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:6px;">Lower numbers appear first.</p>
                </div>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Post Announcement</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canHomepage): ?>
<div class="card">
    <div class="card-header">
        <h3>Public Homepage</h3>
        <div class="btn-group">
            <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-sm">View Campus Homepage</a>
            <a href="<?= BASE_URL ?>/modules/settings/homepage.php" class="btn btn-secondary btn-sm">Upload PDFs</a>
        </div>
    </div>
    <div class="card-body">
        <p style="color:var(--text-muted);margin-bottom:16px;">
            Choose which announcements appear on the public landing page (intake, results, exam calendars, etc.).
        </p>
        <?php
        $homepageItems = array_filter($announcements, fn($a) => !empty($a['show_on_homepage']));
        if (empty($homepageItems)):
        ?>
            <div class="empty-state">
                <h4>No homepage announcements selected</h4>
                <p>Post an announcement and check "Show on public homepage", or toggle an existing one below.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Title</th><th>Category</th><th>Order</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($homepageItems as $a): ?>
                        <tr>
                            <td><?= sanitize($a['title']) ?></td>
                            <td><span class="badge badge-info"><?= sanitize(getHomepageCategoryLabel($a['homepage_category'] ?? 'general')) ?></span></td>
                            <td><?= (int) ($a['homepage_sort'] ?? 0) ?></td>
                            <td><a href="?homepage_toggle=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">Remove from Homepage</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Announcements</h3></div>
    <div class="card-body">
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <h4>No announcements</h4>
                <p>Check back later for updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($announcements as $a): ?>
            <div style="padding:20px 0;border-bottom:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                            <?php if (!empty($a['show_on_homepage'])): ?>
                            <span class="badge badge-warning">On Homepage</span>
                            <?php endif; ?>
                            <?php if (!empty($a['homepage_category']) && $a['homepage_category'] !== 'general'): ?>
                            <span class="badge badge-info"><?= sanitize(getHomepageCategoryLabel($a['homepage_category'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <h4 style="margin-bottom:8px;"><?= sanitize($a['title']) ?></h4>
                        <p style="color:var(--text-muted);margin-bottom:8px;"><?= nl2br(sanitize($a['message'])) ?></p>
                        <small style="color:var(--text-muted);">
                            Posted by <?= sanitize($a['author'] ?? 'System') ?> on <?= formatDate($a['created_at']) ?>
                            | Audience: <?= sanitize(ucfirst($a['target_audience'])) ?>
                        </small>
                    </div>
                    <div class="btn-group">
                        <?php if ($canHomepage): ?>
                        <a href="?homepage_toggle=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">
                            <?= !empty($a['show_on_homepage']) ? 'Remove from Homepage' : 'Show on Homepage' ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($canPost): ?>
                        <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Remove this announcement?">Remove</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
