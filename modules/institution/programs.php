<?php
$pageTitle = 'Campus Programs';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO programs (program_code, program_name, campus_id, duration_years, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        strtoupper(trim($_POST['program_code'])),
        trim($_POST['program_name']),
        $_POST['campus_id'] ?: null,
        $_POST['duration_years'] ?? 4,
        trim($_POST['description'] ?? '')
    ]);
    setFlash('success', 'Program added successfully.');
    redirect(BASE_URL . '/modules/institution/programs.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE programs SET is_active = NOT is_active WHERE id = ?")->execute([$_GET['toggle']]);
    setFlash('success', 'Program status updated.');
    redirect(BASE_URL . '/modules/institution/programs.php');
}

$programs = $pdo->query("
    SELECT p.*, c.branch_name FROM programs p
    LEFT JOIN campus_branches c ON p.campus_id = c.id
    ORDER BY p.program_name
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Add Program</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Program Code *</label>
                    <input type="text" name="program_code" class="form-control" placeholder="e.g. DBIT" required>
                </div>
                <div class="form-group">
                    <label>Program Name *</label>
                    <input type="text" name="program_name" class="form-control" required>
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
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (Years)</label>
                    <input type="number" name="duration_years" class="form-control" value="4" min="1">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Program</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Academic Programs</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Code</th><th>Program</th><th>Campus</th><th>Duration</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                    <tr>
                        <td><?= sanitize($p['program_code']) ?></td>
                        <td><?= sanitize($p['program_name']) ?></td>
                        <td><?= sanitize($p['branch_name'] ?? '-') ?></td>
                        <td><?= $p['duration_years'] ?> years</td>
                        <td><span class="badge badge-<?= $p['is_active'] ? 'success' : 'secondary' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><a href="?toggle=<?= $p['id'] ?>" class="btn btn-secondary btn-sm"><?= $p['is_active'] ? 'Deactivate' : 'Activate' ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
