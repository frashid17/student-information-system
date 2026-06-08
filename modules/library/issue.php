<?php
$pageTitle = 'Issue / Return Books';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'faculty', 'student']);

$pdo = getDBConnection();
$linkedStudent = getLinkedStudent($pdo);
$canManageLibrary = canAccess('library');
$students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students WHERE status = 'active'")->fetchAll();
$books = $pdo->query("SELECT * FROM books WHERE available_copies > 0 ORDER BY title")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'issue') {
    if (!$canManageLibrary) {
        setFlash('error', 'You do not have permission to issue books.');
        redirect(BASE_URL . '/modules/library/issue.php');
    }
    $bookId = $_POST['book_id'];
    $dueDate = date('Y-m-d', strtotime('+14 days'));

    $book = $pdo->prepare("SELECT available_copies FROM books WHERE id = ?");
    $book->execute([$bookId]);
    $bookData = $book->fetch();

    if ($bookData && $bookData['available_copies'] > 0) {
        $stmt = $pdo->prepare("INSERT INTO book_issues (book_id, student_id, issue_date, due_date) VALUES (?, ?, CURDATE(), ?)");
        $stmt->execute([$bookId, $_POST['student_id'], $dueDate]);

        $pdo->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE id = ?")->execute([$bookId]);
        setFlash('success', 'Book issued successfully. Due: ' . formatDate($dueDate));
    } else {
        setFlash('error', 'No copies available.');
    }
    redirect(BASE_URL . '/modules/library/issue.php');
}

if (isset($_GET['return']) && is_numeric($_GET['return'])) {
    if (!$canManageLibrary) {
        setFlash('error', 'Only library staff can process returns.');
        redirect(BASE_URL . '/modules/library/issue.php');
    }

    $issue = $pdo->prepare("SELECT * FROM book_issues WHERE id = ? AND status = 'issued'");
    $issue->execute([$_GET['return']]);
    $issueData = $issue->fetch();

    if ($issueData) {
        $fine = 0;
        if ($issueData['due_date'] && strtotime($issueData['due_date']) < time()) {
            $daysLate = (int)((time() - strtotime($issueData['due_date'])) / 86400);
            $fine = $daysLate * 50;
        }

        $pdo->prepare("UPDATE book_issues SET return_date = CURDATE(), status = 'returned', fine_amount = ? WHERE id = ?")->execute([$fine, $_GET['return']]);
        $pdo->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE id = ?")->execute([$issueData['book_id']]);

        $msg = 'Book returned successfully.';
        if ($fine > 0) $msg .= ' Fine: ' . formatCurrency($fine);
        setFlash('success', $msg);
    }
    redirect(BASE_URL . '/modules/library/issue.php');
}

if (isStudentUser() && $linkedStudent) {
    $issueStmt = $pdo->prepare("
        SELECT bi.*, b.title, b.author, s.student_number, s.first_name, s.last_name
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        JOIN students s ON bi.student_id = s.id
        WHERE bi.student_id = ?
        ORDER BY bi.created_at DESC
    ");
    $issueStmt->execute([(int) $linkedStudent['id']]);
    $issues = $issueStmt->fetchAll();
} else {
    $issues = $pdo->query("
        SELECT bi.*, b.title, b.author, s.student_number, s.first_name, s.last_name
        FROM book_issues bi
        JOIN books b ON bi.book_id = b.id
        JOIN students s ON bi.student_id = s.id
        ORDER BY bi.created_at DESC
    ")->fetchAll();
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($canManageLibrary): ?>
<div class="card">
    <div class="card-header"><h3>Issue Book to Student</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="issue">
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['student_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Book *</label>
                    <select name="book_id" class="form-control" required>
                        <option value="">Select Book</option>
                        <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= sanitize($b['title'] . ' (' . $b['available_copies'] . ' available)') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Issue Book</button>
        </form>
    </div>
</div>
<?php elseif (isStudentUser() && !$linkedStudent): ?>
<div class="alert alert-error">Your student profile is not linked to this account.</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3><?= isStudentUser() ? 'My Borrowed Books' : 'Book Issue / Return Records' ?></h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Book</th>
                        <?php if ($canManageLibrary): ?><th>Student</th><?php endif; ?>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <?php if ($canManageLibrary): ?><th>Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $i): ?>
                    <tr>
                        <td><?= sanitize($i['title']) ?></td>
                        <?php if ($canManageLibrary): ?>
                        <td><?= sanitize($i['student_number'] . ' - ' . $i['first_name']) ?></td>
                        <?php endif; ?>
                        <td><?= formatDate($i['issue_date']) ?></td>
                        <td><?= formatDate($i['due_date']) ?></td>
                        <td><?= formatDate($i['return_date']) ?></td>
                        <td>
                            <span class="badge badge-<?= $i['status'] === 'issued' ? 'warning' : ($i['status'] === 'overdue' ? 'danger' : 'success') ?>">
                                <?= sanitize($i['status']) ?>
                            </span>
                        </td>
                        <?php if ($canManageLibrary): ?>
                        <td>
                            <?php if ($i['status'] === 'issued'): ?>
                            <a href="?return=<?= $i['id'] ?>" class="btn btn-success btn-sm">Return</a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
