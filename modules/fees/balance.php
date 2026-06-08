<?php
$pageTitle = 'Fee Balance';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'student']);

$pdo = getDBConnection();
$balanceData = null;
$linkedStudent = getLinkedStudent($pdo);
$isOwnBalance = isStudentUser();

if ($isOwnBalance && $linkedStudent) {
    $trimester = $_POST['trimester'] ?? $_GET['trimester'] ?? $linkedStudent['trimester'];
    $academicYear = $_POST['academic_year'] ?? $_GET['academic_year'] ?? $linkedStudent['academic_year'];
    $balanceData = getStudentFeeBalance($pdo, (int) $linkedStudent['id'], $trimester, $academicYear);
    $balanceData['trimester'] = $trimester;
    $balanceData['academic_year'] = $academicYear;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['student_number'])) {
    $studentNumber = trim($_POST['student_number'] ?? $_GET['student_number'] ?? '');
    $trimester = $_POST['trimester'] ?? $_GET['trimester'] ?? 'Trimester 1';
    $academicYear = $_POST['academic_year'] ?? $_GET['academic_year'] ?? '2025/2026';

    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_number = ?");
    $stmt->execute([$studentNumber]);
    $student = $stmt->fetch();

    if ($student) {
        $balanceData = getStudentFeeBalance($pdo, (int) $student['id'], $trimester, $academicYear);
        $balanceData['trimester'] = $trimester;
        $balanceData['academic_year'] = $academicYear;
    } else {
        setFlash('error', 'Student not found with that number.');
    }
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-error"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$isOwnBalance): ?>
<div class="card">
    <div class="card-header"><h3>Check Fee Balance</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Student Number *</label>
                    <input type="text" name="student_number" class="form-control" placeholder="e.g. STU20260001" value="<?= sanitize($_POST['student_number'] ?? $_GET['student_number'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Trimester *</label>
                    <select name="trimester" class="form-control" required>
                        <?php foreach (['Trimester 1','Trimester 2','Trimester 3'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($balanceData['trimester'] ?? 'Trimester 1') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= sanitize($balanceData['academic_year'] ?? '2025/2026') ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Check Balance</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header"><h3>My Fee Balance</h3></div>
    <div class="card-body">
        <?php if (!$linkedStudent): ?>
            <div class="alert alert-error">Your student profile is not linked to this account.</div>
        <?php else: ?>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Trimester *</label>
                    <select name="trimester" class="form-control" required>
                        <?php foreach (['Trimester 1','Trimester 2','Trimester 3'] as $t): ?>
                        <option value="<?= $t ?>" <?= ($balanceData['trimester'] ?? $linkedStudent['trimester']) === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= sanitize($balanceData['academic_year'] ?? $linkedStudent['academic_year']) ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Update Balance View</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($balanceData && isset($balanceData['student'])): ?>
<div class="card">
    <div class="card-header">
        <h3><?= $isOwnBalance ? 'My Fee Balance Report' : 'Fee Balance Report' ?></h3>
        <button onclick="printSection('balancePrint')" class="btn btn-secondary btn-sm no-print">Print</button>
    </div>
    <div class="card-body" id="balancePrint">
        <div class="detail-grid" style="margin-bottom:24px;">
            <div class="detail-item"><label>Student</label><span><?= sanitize($balanceData['student']['first_name'] . ' ' . $balanceData['student']['last_name']) ?></span></div>
            <div class="detail-item"><label>Student Number</label><span><?= sanitize($balanceData['student']['student_number']) ?></span></div>
            <div class="detail-item"><label>Trimester</label><span><?= sanitize($balanceData['trimester']) ?></span></div>
            <div class="detail-item"><label>Academic Year</label><span><?= sanitize($balanceData['academic_year']) ?></span></div>
        </div>
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="label">Total Fee</div>
                <div class="value" style="font-size:1.5rem;"><?= formatCurrency($balanceData['total_fee']) ?></div>
            </div>
            <div class="stat-card success">
                <div class="label">Total Paid</div>
                <div class="value" style="font-size:1.5rem;"><?= formatCurrency($balanceData['total_paid']) ?></div>
            </div>
            <div class="stat-card <?= $balanceData['balance'] > 0 ? 'warning' : 'accent' ?>">
                <div class="label">Balance</div>
                <div class="value" style="font-size:1.5rem;"><?= formatCurrency($balanceData['balance']) ?></div>
            </div>
        </div>
        <?php if ($balanceData['balance'] > 0): ?>
            <div class="alert alert-info" style="margin-top:16px;">Outstanding fee balance: <?= formatCurrency($balanceData['balance']) ?>.</div>
        <?php else: ?>
            <div class="alert alert-success" style="margin-top:16px;">Fees fully paid for this trimester.</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
