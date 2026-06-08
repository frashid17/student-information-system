<?php
$pageTitle = 'Campus Branches';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO campus_branches (branch_name, location, phone, email) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        trim($_POST['branch_name']),
        trim($_POST['location'] ?? ''),
        trim($_POST['phone'] ?? ''),
        trim($_POST['email'] ?? '')
    ]);
    setFlash('success', 'Campus branch added successfully.');
    redirect(BASE_URL . '/modules/institution/campus.php');
}

if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $pdo->prepare("UPDATE campus_branches SET is_active = NOT is_active WHERE id = ?")->execute([$_GET['toggle']]);
    setFlash('success', 'Campus status updated.');
    redirect(BASE_URL . '/modules/institution/campus.php');
}

$campuses = $pdo->query("SELECT * FROM campus_branches ORDER BY branch_name")->fetchAll();
$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Add Campus Branch</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Branch Name *</label>
                    <input type="text" name="branch_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Campus</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Campus Branches</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Location</th><th>Phone</th><th>Email</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($campuses as $c): ?>
                    <tr>
                        <td><?= sanitize($c['branch_name']) ?></td>
                        <td><?= sanitize($c['location'] ?? '-') ?></td>
                        <td><?= sanitize($c['phone'] ?? '-') ?></td>
                        <td><?= sanitize($c['email'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td>
                            <a href="?toggle=<?= $c['id'] ?>" class="btn btn-secondary btn-sm"><?= $c['is_active'] ? 'Deactivate' : 'Activate' ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
