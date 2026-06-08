<?php
$pageTitle = 'View Students';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff']);

$pdo = getDBConnection();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    setFlash('success', 'Student deactivated successfully.');
    redirect(BASE_URL . '/modules/students/list.php');
}

$students = $pdo->query("
    SELECT s.*, p.program_name, c.branch_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN campus_branches c ON s.campus_id = c.id
    ORDER BY s.created_at DESC
")->fetchAll();

$viewStudent = null;
$viewStudentUnits = [];
$viewStudentFee = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT s.*, p.program_name, p.program_code, c.branch_name
        FROM students s
        LEFT JOIN programs p ON s.program_id = p.id
        LEFT JOIN campus_branches c ON s.campus_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $viewStudent = $stmt->fetch();

    if ($viewStudent) {
        $viewStudentUnits = getStudentRegisteredUnits(
            $pdo,
            (int) $viewStudent['id'],
            $viewStudent['trimester'],
            $viewStudent['academic_year']
        );
        $viewStudentFee = getStudentFeeBalance(
            $pdo,
            (int) $viewStudent['id'],
            $viewStudent['trimester'],
            $viewStudent['academic_year']
        );
    }
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($viewStudent): ?>
<div class="card">
    <div class="card-header">
        <h3>Student Details - <?= sanitize($viewStudent['student_number']) ?></h3>
        <div class="btn-group">
            <a href="<?= BASE_URL ?>/modules/students/edit.php?id=<?= $viewStudent['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/students/list.php" class="btn btn-secondary btn-sm">Back to List</a>
        </div>
    </div>
    <div class="card-body">
        <div class="form-section-title">Personal Information</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Full Name</label><span><?= sanitize($viewStudent['first_name'] . ' ' . ($viewStudent['middle_name'] ? $viewStudent['middle_name'] . ' ' : '') . $viewStudent['last_name']) ?></span></div>
            <div class="detail-item"><label>Gender</label><span><?= sanitize($viewStudent['gender']) ?></span></div>
            <div class="detail-item"><label>Date of Birth</label><span><?= formatDate($viewStudent['date_of_birth']) ?></span></div>
            <div class="detail-item"><label>Email</label><span><?= sanitize($viewStudent['email'] ?: '-') ?></span></div>
            <div class="detail-item"><label>Phone</label><span><?= sanitize($viewStudent['phone'] ?: '-') ?></span></div>
            <div class="detail-item"><label>National ID</label><span><?= sanitize($viewStudent['national_id'] ?: '-') ?></span></div>
        </div>
        <div class="form-section-title">Academic Information</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Program</label><span><?= sanitize($viewStudent['program_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Campus</label><span><?= sanitize($viewStudent['branch_name'] ?? '-') ?></span></div>
            <div class="detail-item"><label>Trimester</label><span><?= sanitize($viewStudent['trimester']) ?></span></div>
            <div class="detail-item"><label>Academic Year</label><span><?= sanitize($viewStudent['academic_year']) ?></span></div>
            <div class="detail-item"><label>Semester</label><span>Sem <?= getStudentSemesterNumber($viewStudent) ?></span></div>
            <div class="detail-item"><label>Enrollment Date</label><span><?= formatDate($viewStudent['enrollment_date']) ?></span></div>
            <div class="detail-item"><label>Status</label><span><span class="badge badge-<?= $viewStudent['status'] === 'active' ? 'success' : 'secondary' ?>"><?= sanitize($viewStudent['status']) ?></span></span></div>
        </div>

        <div class="form-section-title">Fee Status (<?= sanitize($viewStudent['trimester']) ?>, <?= sanitize($viewStudent['academic_year']) ?>)</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Total Fee</label><span><?= formatCurrency((float) ($viewStudentFee['total_fee'] ?? 0)) ?></span></div>
            <div class="detail-item"><label>Total Paid</label><span><?= formatCurrency((float) ($viewStudentFee['total_paid'] ?? 0)) ?></span></div>
            <div class="detail-item"><label>Balance</label><span><?= formatCurrency((float) ($viewStudentFee['balance'] ?? 0)) ?></span></div>
            <div class="detail-item"><label>Payment Progress</label><span><?= number_format(getFeePaymentPercent($pdo, (int) $viewStudent['id'], $viewStudent['trimester'], $viewStudent['academic_year']), 1) ?>%</span></div>
        </div>
        <div style="margin-bottom:16px;">
            <a href="<?= BASE_URL ?>/modules/fees/balance.php?student_number=<?= urlencode($viewStudent['student_number']) ?>&trimester=<?= urlencode($viewStudent['trimester']) ?>&academic_year=<?= urlencode($viewStudent['academic_year']) ?>" class="btn btn-secondary btn-sm">Fee Balance</a>
            <a href="<?= BASE_URL ?>/modules/fees/payments.php" class="btn btn-secondary btn-sm">Record Payment</a>
        </div>

        <div class="form-section-title">Registered Units (<?= count($viewStudentUnits) ?> / <?= MAX_UNITS_PER_SEMESTER ?>)</div>
        <?php if (empty($viewStudentUnits)): ?>
        <p style="color:var(--text-muted);margin-bottom:16px;">No units registered for the current semester.</p>
        <?php else: ?>
        <div class="table-responsive" style="margin-bottom:16px;">
            <table class="data-table">
                <thead>
                    <tr><th>Code</th><th>Unit Name</th><th>Credits</th><th>Category</th><th>Registered</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($viewStudentUnits as $u): ?>
                    <tr>
                        <td><?= sanitize($u['unit_code']) ?></td>
                        <td><?= sanitize($u['unit_name']) ?></td>
                        <td><?= sanitize((string) $u['credit_hours']) ?></td>
                        <td><?= sanitize($u['category']) ?></td>
                        <td><?= formatDate($u['registered_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="form-section-title">Next of Kin</div>
        <div class="detail-grid">
            <div class="detail-item"><label>Name</label><span><?= sanitize($viewStudent['kin_name'] ?: '-') ?></span></div>
            <div class="detail-item"><label>Relationship</label><span><?= sanitize($viewStudent['kin_relationship'] ?: '-') ?></span></div>
            <div class="detail-item"><label>Phone</label><span><?= sanitize($viewStudent['kin_phone'] ?: '-') ?></span></div>
            <div class="detail-item"><label>Address</label><span><?= sanitize($viewStudent['kin_address'] ?: '-') ?></span></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Registered Students</h3>
        <a href="<?= BASE_URL ?>/modules/students/register.php" class="btn btn-primary btn-sm">Add Student</a>
    </div>
    <div class="card-body">
        <div class="search-bar">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search students...">
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student No.</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Campus</th>
                        <th>Trimester</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:32px;">No students found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= sanitize($s['student_number']) ?></td>
                        <td><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= sanitize($s['program_name'] ?? '-') ?></td>
                        <td><?= sanitize($s['branch_name'] ?? '-') ?></td>
                        <td><?= sanitize($s['trimester']) ?></td>
                        <td><span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>"><?= sanitize($s['status']) ?></span></td>
                        <td>
                            <div class="btn-group">
                                <a href="?view=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                                <a href="<?= BASE_URL ?>/modules/students/edit.php?id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Edit</a>
                                <?php if ($s['status'] === 'active'): ?>
                                <a href="?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Deactivate this student?">Deactivate</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
