<?php
$pageTitle = 'Library Check-in';
require_once __DIR__ . '/../../includes/init.php';
requireModuleAccess('library');

$pdo = getDBConnection();
$students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name")->fetchAll();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO library_checkins (student_id, campus_id) VALUES (?, ?)");
    $stmt->execute([$_POST['student_id'], $_POST['campus_id'] ?: null]);
    setFlash('success', 'Student checked into library successfully.');
    redirect(BASE_URL . '/modules/library/checkin.php');
}

if (isset($_GET['checkout']) && is_numeric($_GET['checkout'])) {
    $stmt = $pdo->prepare("UPDATE library_checkins SET checkout_time = NOW() WHERE id = ? AND checkout_time IS NULL");
    $stmt->execute([$_GET['checkout']]);
    setFlash('success', 'Student checked out.');
    redirect(BASE_URL . '/modules/library/checkin.php');
}

$checkins = $pdo->query("
    SELECT lc.*, s.student_number, s.first_name, s.last_name, c.branch_name
    FROM library_checkins lc
    JOIN students s ON lc.student_id = s.id
    LEFT JOIN campus_branches c ON lc.campus_id = c.id
    ORDER BY lc.checkin_time DESC LIMIT 50
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Library Check-in</h3></div>
    <div class="card-body">
        <form method="POST">
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
                    <label>Campus</label>
                    <select name="campus_id" class="form-control">
                        <option value="">Select Campus</option>
                        <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Check In Student</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Library Visit Log</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Campus</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($checkins as $c): ?>
                    <tr>
                        <td><?= sanitize($c['student_number'] . ' - ' . $c['first_name'] . ' ' . $c['last_name']) ?></td>
                        <td><?= sanitize($c['branch_name'] ?? '-') ?></td>
                        <td><?= date('d M Y H:i', strtotime($c['checkin_time'])) ?></td>
                        <td><?= $c['checkout_time'] ? date('d M Y H:i', strtotime($c['checkout_time'])) : '-' ?></td>
                        <td>
                            <?php if ($c['checkout_time']): ?>
                                <span class="badge badge-secondary">Completed</span>
                            <?php else: ?>
                                <span class="badge badge-success">In Library</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$c['checkout_time']): ?>
                            <a href="?checkout=<?= $c['id'] ?>" class="btn btn-secondary btn-sm">Check Out</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
