<?php
$pageTitle = 'Fee Setup';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$programs = $pdo->query("SELECT * FROM programs WHERE is_active = 1 ORDER BY program_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO fee_structures (program_id, trimester, academic_year, amount, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['program_id'],
        $_POST['trimester'],
        $_POST['academic_year'],
        $_POST['amount'],
        trim($_POST['description'] ?? '')
    ]);
    setFlash('success', 'Fee structure added successfully.');
    redirect(BASE_URL . '/modules/institution/fees.php');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM fee_structures WHERE id = ?")->execute([$_GET['delete']]);
    setFlash('success', 'Fee structure removed.');
    redirect(BASE_URL . '/modules/institution/fees.php');
}

$fees = $pdo->query("
    SELECT fs.*, p.program_code, p.program_name
    FROM fee_structures fs
    JOIN programs p ON fs.program_id = p.id
    ORDER BY p.program_name, fs.trimester
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Set Up Institution Fees</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Program *</label>
                    <select name="program_id" class="form-control" required>
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= sanitize($p['program_code'] . ' - ' . $p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Trimester *</label>
                    <select name="trimester" class="form-control" required>
                        <option value="Trimester 1">Trimester 1</option>
                        <option value="Trimester 2">Trimester 2</option>
                        <option value="Trimester 3">Trimester 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Academic Year *</label>
                    <input type="text" name="academic_year" class="form-control" value="2025/2026" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (KES) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Tuition and Registration">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Fee Structure</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Fee Structures</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Program</th><th>Trimester</th><th>Year</th><th>Amount</th><th>Description</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($fees as $f): ?>
                    <tr>
                        <td><?= sanitize($f['program_code'] . ' - ' . $f['program_name']) ?></td>
                        <td><?= sanitize($f['trimester']) ?></td>
                        <td><?= sanitize($f['academic_year']) ?></td>
                        <td><?= formatCurrency((float)$f['amount']) ?></td>
                        <td><?= sanitize($f['description'] ?? '-') ?></td>
                        <td><a href="?delete=<?= $f['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete this fee structure?">Delete</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
