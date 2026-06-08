<?php
$pageTitle = 'Pay Slip';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$faculty = $pdo->query("
    SELECT f.id, f.staff_id, f.first_name, f.last_name, p.basic_salary, p.allowances, p.deductions
    FROM faculty f
    LEFT JOIN payroll p ON f.id = p.faculty_id
    WHERE f.status = 'active'
    ORDER BY f.first_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $basic = (float)$_POST['basic_salary'];
    $allow = (float)($_POST['allowances'] ?? 0);
    $deduct = (float)($_POST['deductions'] ?? 0);
    $net = $basic + $allow - $deduct;

    $stmt = $pdo->prepare("INSERT INTO payslips (faculty_id, payroll_id, basic_salary, allowances, deductions, net_salary, pay_period, pay_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['faculty_id'], $_POST['payroll_id'] ?: null,
        $basic, $allow, $deduct, $net,
        $_POST['pay_period'], $_POST['pay_date'], trim($_POST['notes'] ?? '')
    ]);

    redirect(BASE_URL . '/modules/faculty/payslip.php?view=' . $pdo->lastInsertId());
}

$payslip = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("
        SELECT ps.*, f.staff_id, f.first_name, f.last_name, f.department, f.faculty_type
        FROM payslips ps
        JOIN faculty f ON ps.faculty_id = f.id
        WHERE ps.id = ?
    ");
    $stmt->execute([$_GET['view']]);
    $payslip = $stmt->fetch();
}

$history = $pdo->query("
    SELECT ps.*, f.staff_id, f.first_name, f.last_name
    FROM payslips ps
    JOIN faculty f ON ps.faculty_id = f.id
    ORDER BY ps.pay_date DESC LIMIT 20
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($payslip): ?>
<div class="card">
    <div class="card-header">
        <h3>Pay Slip</h3>
        <button onclick="printSection('payslipPrint')" class="btn btn-primary btn-sm">Print Pay Slip</button>
    </div>
    <div class="card-body" id="payslipPrint">
        <div class="receipt-box">
            <div class="receipt-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--receipt">
                <p>Employee Pay Slip</p>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><label>Staff ID</label><span><?= sanitize($payslip['staff_id']) ?></span></div>
                <div class="detail-item"><label>Employee Name</label><span><?= sanitize($payslip['first_name'] . ' ' . $payslip['last_name']) ?></span></div>
                <div class="detail-item"><label>Department</label><span><?= sanitize($payslip['department'] ?? '-') ?></span></div>
                <div class="detail-item"><label>Pay Period</label><span><?= sanitize($payslip['pay_period']) ?></span></div>
                <div class="detail-item"><label>Pay Date</label><span><?= formatDate($payslip['pay_date']) ?></span></div>
                <div class="detail-item"><label>Basic Salary</label><span><?= formatCurrency((float)$payslip['basic_salary']) ?></span></div>
                <div class="detail-item"><label>Allowances</label><span><?= formatCurrency((float)$payslip['allowances']) ?></span></div>
                <div class="detail-item"><label>Deductions</label><span><?= formatCurrency((float)$payslip['deductions']) ?></span></div>
                <div class="detail-item"><label>Net Salary</label><span style="font-size:1.2rem;font-weight:700;color:var(--success);"><?= formatCurrency((float)$payslip['net_salary']) ?></span></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Generate Pay Slip</h3></div>
    <div class="card-body">
        <form method="POST" id="payslipForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="faculty_id" id="facultySelect" class="form-control" required onchange="loadSalary(this)">
                        <option value="">Select Employee</option>
                        <?php foreach ($faculty as $f): ?>
                        <option value="<?= $f['id'] ?>"
                            data-basic="<?= $f['basic_salary'] ?? 0 ?>"
                            data-allow="<?= $f['allowances'] ?? 0 ?>"
                            data-deduct="<?= $f['deductions'] ?? 0 ?>">
                            <?= sanitize($f['staff_id'] . ' - ' . $f['first_name'] . ' ' . $f['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pay Period *</label>
                    <input type="text" name="pay_period" class="form-control" value="<?= date('F Y') ?>" required>
                </div>
                <div class="form-group">
                    <label>Pay Date *</label>
                    <input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Basic Salary</label>
                    <input type="number" name="basic_salary" id="basicSalary" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Allowances</label>
                    <input type="number" name="allowances" id="allowances" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Deductions</label>
                    <input type="number" name="deductions" id="deductions" class="form-control" step="0.01" value="0">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Generate Pay Slip</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Pay Slip History</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Staff ID</th><th>Name</th><th>Period</th><th>Net Salary</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= sanitize($h['staff_id']) ?></td>
                        <td><?= sanitize($h['first_name'] . ' ' . $h['last_name']) ?></td>
                        <td><?= sanitize($h['pay_period']) ?></td>
                        <td><?= formatCurrency((float)$h['net_salary']) ?></td>
                        <td><?= formatDate($h['pay_date']) ?></td>
                        <td><a href="?view=<?= $h['id'] ?>" class="btn btn-secondary btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function loadSalary(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('basicSalary').value = opt.dataset.basic || 0;
    document.getElementById('allowances').value = opt.dataset.allow || 0;
    document.getElementById('deductions').value = opt.dataset.deduct || 0;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
