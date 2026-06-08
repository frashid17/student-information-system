<?php
$pageTitle = 'Payroll';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$faculty = $pdo->query("SELECT id, staff_id, first_name, last_name FROM faculty WHERE status = 'active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $facultyId = $_POST['faculty_id'];

    $check = $pdo->prepare("SELECT id FROM payroll WHERE faculty_id = ?");
    $check->execute([$facultyId]);

    if ($check->fetch()) {
        $stmt = $pdo->prepare("UPDATE payroll SET basic_salary=?, allowances=?, deductions=?, pay_period=?, effective_date=? WHERE faculty_id=?");
        $stmt->execute([
            $_POST['basic_salary'], $_POST['allowances'] ?? 0, $_POST['deductions'] ?? 0,
            $_POST['pay_period'], $_POST['effective_date'], $facultyId
        ]);
        setFlash('success', 'Salary updated successfully.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO payroll (faculty_id, basic_salary, allowances, deductions, pay_period, effective_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $facultyId, $_POST['basic_salary'], $_POST['allowances'] ?? 0,
            $_POST['deductions'] ?? 0, $_POST['pay_period'], $_POST['effective_date']
        ]);
        setFlash('success', 'Salary set up successfully.');
    }
    redirect(BASE_URL . '/modules/faculty/payroll.php');
}

$payrolls = $pdo->query("
    SELECT p.*, f.staff_id, f.first_name, f.last_name, f.department
    FROM payroll p
    JOIN faculty f ON p.faculty_id = f.id
    ORDER BY f.first_name
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Set Up / Update Salary</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="faculty_id" class="form-control" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($faculty as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= sanitize($f['staff_id'] . ' - ' . $f['first_name'] . ' ' . $f['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Basic Salary (KES) *</label>
                    <input type="number" name="basic_salary" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Allowances (KES)</label>
                    <input type="number" name="allowances" class="form-control" step="0.01" value="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Deductions (KES)</label>
                    <input type="number" name="deductions" class="form-control" step="0.01" value="0">
                </div>
                <div class="form-group">
                    <label>Pay Period</label>
                    <input type="text" name="pay_period" class="form-control" placeholder="e.g. March 2026">
                </div>
                <div class="form-group">
                    <label>Effective Date</label>
                    <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Salary</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Employee Salaries</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Basic Salary</th>
                        <th>Allowances</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payrolls as $p): ?>
                    <?php $net = $p['basic_salary'] + $p['allowances'] - $p['deductions']; ?>
                    <tr>
                        <td><?= sanitize($p['staff_id']) ?></td>
                        <td><?= sanitize($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= sanitize($p['department'] ?? '-') ?></td>
                        <td><?= formatCurrency((float)$p['basic_salary']) ?></td>
                        <td><?= formatCurrency((float)$p['allowances']) ?></td>
                        <td><?= formatCurrency((float)$p['deductions']) ?></td>
                        <td><strong><?= formatCurrency((float)$net) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
