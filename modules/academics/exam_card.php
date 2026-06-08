<?php
$pageTitle = 'Exam Card';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['student']);

$pdo = getDBConnection();
$student = getLinkedStudent($pdo);

if (!$student) {
    setFlash('error', 'Your student profile is not linked to this account.');
    redirect(BASE_URL . '/dashboard.php');
}

$examCheck = canPrintExamCard($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$registeredUnits = getStudentRegisteredUnits($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$feeBalance = getStudentFeeBalance($pdo, (int) $student['id'], $student['trimester'], $student['academic_year']);
$canPrint = $examCheck['allowed'];
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if (!$canPrint): ?>
<div class="alert alert-error">
    <?= sanitize($examCheck['reason']) ?>
    <?php if ((float) ($feeBalance['balance'] ?? 0) > 0): ?>
    <a href="<?= BASE_URL ?>/modules/fees/balance.php" class="btn btn-secondary btn-sm" style="margin-left:12px;">Pay Fees</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Examination Card</h3>
        <?php if ($canPrint): ?>
        <button onclick="printSection('examCardPrint')" class="btn btn-primary btn-sm no-print">Print Exam Card</button>
        <?php else: ?>
        <span class="badge badge-warning no-print">Printing locked — complete fee payment</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div id="examCardPrint" class="exam-card">
            <div class="exam-card-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--receipt">
                <h2><?= sanitize(APP_INSTITUTION) ?></h2>
                <p class="exam-card-subtitle">Student Examination Card</p>
            </div>

            <div class="exam-card-meta">
                <div class="exam-card-meta-row">
                    <div><strong>Registration No:</strong> <?= sanitize($student['student_number']) ?></div>
                    <div><strong>Semester:</strong> Sem <?= getStudentSemesterNumber($student) ?></div>
                </div>
                <div class="exam-card-meta-row">
                    <div><strong>Student Name:</strong> <?= sanitize(trim($student['first_name'] . ' ' . ($student['middle_name'] ?? '') . ' ' . $student['last_name'])) ?></div>
                    <div><strong>Academic Year:</strong> <?= sanitize($student['academic_year']) ?></div>
                </div>
                <div class="exam-card-meta-row">
                    <div><strong>Program:</strong> <?= sanitize($student['program_name'] ?? '-') ?></div>
                    <div><strong>Trimester:</strong> <?= sanitize($student['trimester']) ?></div>
                </div>
                <div class="exam-card-meta-row">
                    <div><strong>Campus:</strong> <?= sanitize($student['branch_name'] ?? '-') ?></div>
                    <div><strong>Date Issued:</strong> <?= date('d M Y') ?></div>
                </div>
            </div>

            <div class="exam-card-units">
                <h4>Registered Units</h4>
                <?php if (empty($registeredUnits)): ?>
                <p>No units registered for this semester.</p>
                <?php else: ?>
                <table class="exam-card-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Unit Code</th>
                            <th>Unit Name</th>
                            <th>Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registeredUnits as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= sanitize($u['unit_code']) ?></td>
                            <td><?= sanitize($u['unit_name']) ?></td>
                            <td><?= sanitize((string) $u['credit_hours']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right;font-weight:600;">Total Credits</td>
                            <td><?= array_sum(array_column($registeredUnits, 'credit_hours')) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>

            <?php if ($canPrint): ?>
            <div class="exam-card-signatures">
                <div class="exam-card-signature-block">
                    <div class="exam-card-stamp">REGISTRAR<br>OFFICIAL STAMP</div>
                    <div class="exam-card-sign-line"></div>
                    <p><strong>Academic Registrar</strong></p>
                    <p class="exam-card-sign-title">Kenya Methodist University</p>
                </div>
                <div class="exam-card-signature-block">
                    <div class="exam-card-stamp">FINANCE<br>OFFICIAL STAMP</div>
                    <div class="exam-card-sign-line"></div>
                    <p><strong>Finance Officer</strong></p>
                    <p class="exam-card-sign-title">Fees Cleared — <?= sanitize($student['trimester']) ?> <?= sanitize($student['academic_year']) ?></p>
                </div>
            </div>

            <div class="exam-card-footer">
                <p>This examination card is valid only for the units listed above. Present this card at all examination venues.</p>
                <p class="exam-card-verify">Verified: Full school fees paid for <?= sanitize($student['trimester']) ?>, <?= sanitize($student['academic_year']) ?>.</p>
            </div>
            <?php else: ?>
            <div class="exam-card-locked no-print">
                <p><strong>Preview only.</strong> Complete your fee payment to unlock printing with official signatures and stamps.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
