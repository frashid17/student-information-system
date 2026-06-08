<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../../includes/init.php';
requireModuleAccess('reports');

$pdo = getDBConnection();
$reportType = $_GET['type'] ?? 'enrollment';

$enrollmentStats = $pdo->query("
    SELECT p.program_name, COUNT(s.id) as student_count
    FROM programs p
    LEFT JOIN students s ON s.program_id = p.id AND s.status = 'active'
    GROUP BY p.id, p.program_name
    ORDER BY student_count DESC
")->fetchAll();

$feeSummary = $pdo->query("
    SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount) as total
    FROM fee_payments
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
")->fetchAll();

$libraryStats = $pdo->query("
    SELECT COUNT(*) as total_checkins,
           COUNT(DISTINCT student_id) as unique_visitors
    FROM library_checkins
")->fetch();

$allStudents = $pdo->query("
    SELECT s.*, p.program_name, c.branch_name
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.id
    LEFT JOIN campus_branches c ON s.campus_id = c.id
    WHERE s.status = 'active'
    ORDER BY s.student_number
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="tabs">
    <a href="?type=enrollment" class="tab-link <?= $reportType === 'enrollment' ? 'active' : '' ?>">Enrollment Statistics</a>
    <a href="?type=students" class="tab-link <?= $reportType === 'students' ? 'active' : '' ?>">Student List</a>
    <a href="?type=fees" class="tab-link <?= $reportType === 'fees' ? 'active' : '' ?>">Fee Collection</a>
    <a href="?type=library" class="tab-link <?= $reportType === 'library' ? 'active' : '' ?>">Library Usage</a>
</div>

<?php if ($reportType === 'enrollment'): ?>
<div class="card">
    <div class="card-header">
        <h3>Enrollment Statistics by Program</h3>
        <button onclick="printSection('reportContent')" class="btn btn-secondary btn-sm">Print</button>
    </div>
    <div class="card-body" id="reportContent">
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card primary">
                <div class="label">Total Active Students</div>
                <div class="value"><?= $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn() ?></div>
            </div>
            <div class="stat-card success">
                <div class="label">Total Programs</div>
                <div class="value"><?= $pdo->query("SELECT COUNT(*) FROM programs WHERE is_active=1")->fetchColumn() ?></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Program</th><th>Enrolled Students</th></tr></thead>
                <tbody>
                    <?php foreach ($enrollmentStats as $e): ?>
                    <tr>
                        <td><?= sanitize($e['program_name']) ?></td>
                        <td><?= $e['student_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'students'): ?>
<div class="card">
    <div class="card-header">
        <h3>Registered Students Report</h3>
        <button onclick="printSection('reportContent')" class="btn btn-secondary btn-sm">Print</button>
    </div>
    <div class="card-body" id="reportContent" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Student No.</th><th>Name</th><th>Program</th><th>Campus</th><th>Trimester</th><th>Enrolled</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($allStudents as $s): ?>
                    <tr>
                        <td><?= sanitize($s['student_number']) ?></td>
                        <td><?= sanitize($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= sanitize($s['program_name'] ?? '-') ?></td>
                        <td><?= sanitize($s['branch_name'] ?? '-') ?></td>
                        <td><?= sanitize($s['trimester']) ?></td>
                        <td><?= formatDate($s['enrollment_date']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'fees'): ?>
<div class="card">
    <div class="card-header">
        <h3>Fee Collection Summary</h3>
        <button onclick="printSection('reportContent')" class="btn btn-secondary btn-sm">Print</button>
    </div>
    <div class="card-body" id="reportContent">
        <div class="stat-card accent" style="margin-bottom:24px;">
            <div class="label">Total Fees Collected (All Time)</div>
            <div class="value" style="font-size:1.5rem;"><?= formatCurrency((float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_payments")->fetchColumn()) ?></div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Month</th><th>Total Collected</th></tr></thead>
                <tbody>
                    <?php foreach ($feeSummary as $f): ?>
                    <tr>
                        <td><?= sanitize($f['month']) ?></td>
                        <td><?= formatCurrency((float)$f['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($reportType === 'library'): ?>
<div class="card">
    <div class="card-header">
        <h3>Library Usage Report</h3>
        <button onclick="printSection('reportContent')" class="btn btn-secondary btn-sm">Print</button>
    </div>
    <div class="card-body" id="reportContent">
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="label">Total Library Visits</div>
                <div class="value"><?= $libraryStats['total_checkins'] ?></div>
            </div>
            <div class="stat-card success">
                <div class="label">Unique Student Visitors</div>
                <div class="value"><?= $libraryStats['unique_visitors'] ?></div>
            </div>
            <div class="stat-card warning">
                <div class="label">Books in Catalog</div>
                <div class="value"><?= $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn() ?></div>
            </div>
            <div class="stat-card accent">
                <div class="label">Books Currently Issued</div>
                <div class="value"><?= $pdo->query("SELECT COUNT(*) FROM book_issues WHERE status='issued'")->fetchColumn() ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
