<?php
$pageTitle = 'Homepage Manager';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload = uploadHomepagePdf($_FILES['pdf_file'] ?? []);
    if (!$upload['ok']) {
        setFlash('error', $upload['error']);
        redirect(BASE_URL . '/modules/settings/homepage.php');
    }

    $stmt = $pdo->prepare("INSERT INTO homepage_documents (title, doc_type, file_name, file_path, academic_year, trimester, description, show_on_homepage, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        trim($_POST['title']),
        $_POST['doc_type'],
        $upload['file_name'],
        $upload['file_path'],
        trim($_POST['academic_year'] ?? '') ?: null,
        trim($_POST['trimester'] ?? '') ?: null,
        trim($_POST['description'] ?? '') ?: null,
        !empty($_POST['show_on_homepage']) ? 1 : 0,
        $_SESSION['user_id'],
    ]);

    setFlash('success', 'Document uploaded successfully.');
    redirect(BASE_URL . '/modules/settings/homepage.php');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT file_path FROM homepage_documents WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $doc = $stmt->fetch();
    if ($doc) {
        $file = UPLOAD_PATH . DIRECTORY_SEPARATOR . $doc['file_path'];
        if (is_file($file)) {
            unlink($file);
        }
        $pdo->prepare("UPDATE homepage_documents SET is_active = 0 WHERE id = ?")->execute([$_GET['delete']]);
        setFlash('success', 'Document removed.');
    }
    redirect(BASE_URL . '/modules/settings/homepage.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE homepage_documents SET show_on_homepage = NOT show_on_homepage WHERE id = ?")->execute([$_GET['toggle']]);
    setFlash('success', 'Homepage visibility updated.');
    redirect(BASE_URL . '/modules/settings/homepage.php');
}

$documents = $pdo->query("
    SELECT d.*, u.full_name as uploader
    FROM homepage_documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.is_active = 1
    ORDER BY d.created_at DESC
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Upload PDF Document</h3>
        <a href="<?= BASE_URL ?>/" target="_blank" class="btn btn-secondary btn-sm">View Campus Homepage</a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Trimester 2 Exam Timetable" required>
                </div>
                <div class="form-group">
                    <label>Document Type *</label>
                    <select name="doc_type" class="form-control" required>
                        <option value="events_calendar">Events Calendar</option>
                        <option value="timetable">Class Timetable</option>
                        <option value="exam_timetable">Exam Timetable</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="2025/2026" value="2025/2026">
                </div>
                <div class="form-group">
                    <label>Trimester</label>
                    <select name="trimester" class="form-control">
                        <option value="">—</option>
                        <option value="Trimester 1">Trimester 1</option>
                        <option value="Trimester 2">Trimester 2</option>
                        <option value="Trimester 3">Trimester 3</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" class="form-control" placeholder="Short note shown on homepage">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>PDF File * (max 10MB)</label>
                    <input type="file" name="pdf_file" class="form-control" accept="application/pdf,.pdf" required>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <label style="margin:0;"><input type="checkbox" name="show_on_homepage" value="1" checked> Show on public homepage</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Upload Document</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Published Documents</h3>
        <a href="<?= BASE_URL ?>/modules/communications/announcements.php" class="btn btn-secondary btn-sm">Manage Announcements</a>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($documents)): ?>
            <div class="empty-state" style="padding:32px;">
                <h4>No documents uploaded</h4>
                <p>Upload events calendars, class timetables, or exam timetables as PDF files.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Year / Trimester</th>
                        <th>Homepage</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= sanitize($d['title']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize(getDocumentTypeLabel($d['doc_type'])) ?></span></td>
                        <td><?= sanitize(($d['academic_year'] ?? '-') . ' / ' . ($d['trimester'] ?? '-')) ?></td>
                        <td><span class="badge badge-<?= $d['show_on_homepage'] ? 'success' : 'secondary' ?>"><?= $d['show_on_homepage'] ? 'Visible' : 'Hidden' ?></span></td>
                        <td><?= formatDate($d['created_at']) ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="<?= BASE_URL ?>/document.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-primary btn-sm">View</a>
                                <a href="?toggle=<?= $d['id'] ?>" class="btn btn-secondary btn-sm"><?= $d['show_on_homepage'] ? 'Hide' : 'Show' ?></a>
                                <a href="?delete=<?= $d['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Remove this document?">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
