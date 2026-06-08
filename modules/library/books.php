<?php
$pageTitle = 'Add Books';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin', 'staff', 'faculty']);

$pdo = getDBConnection();
$campuses = $pdo->query("SELECT * FROM campus_branches WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $copies = (int)($_POST['total_copies'] ?? 1);
    $stmt = $pdo->prepare("INSERT INTO books (isbn, title, author, publisher, category, total_copies, available_copies, shelf_location, campus_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        trim($_POST['isbn'] ?? ''),
        trim($_POST['title']),
        trim($_POST['author'] ?? ''),
        trim($_POST['publisher'] ?? ''),
        trim($_POST['category'] ?? ''),
        $copies, $copies,
        trim($_POST['shelf_location'] ?? ''),
        $_POST['campus_id'] ?: null
    ]);
    setFlash('success', 'Book added to shelf successfully.');
    redirect(BASE_URL . '/modules/library/books.php');
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$_GET['delete']]);
    setFlash('success', 'Book removed.');
    redirect(BASE_URL . '/modules/library/books.php');
}

$books = $pdo->query("
    SELECT b.*, c.branch_name FROM books b
    LEFT JOIN campus_branches c ON b.campus_id = c.id
    ORDER BY b.title
")->fetchAll();

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Add Book to Shelf</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" name="author" class="form-control">
                </div>
                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" name="isbn" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Publisher</label>
                    <input type="text" name="publisher" class="form-control">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Computer Science">
                </div>
                <div class="form-group">
                    <label>Shelf Location</label>
                    <input type="text" name="shelf_location" class="form-control" placeholder="e.g. A-12">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total Copies *</label>
                    <input type="number" name="total_copies" class="form-control" value="1" min="1" required>
                </div>
                <div class="form-group">
                    <label>Campus</label>
                    <select name="campus_id" class="form-control">
                        <option value="">All Campuses</option>
                        <?php foreach ($campuses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Book</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Library Catalog</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>ISBN</th>
                        <th>Category</th>
                        <th>Available</th>
                        <th>Shelf</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $b): ?>
                    <tr>
                        <td><?= sanitize($b['title']) ?></td>
                        <td><?= sanitize($b['author'] ?? '-') ?></td>
                        <td><?= sanitize($b['isbn'] ?? '-') ?></td>
                        <td><?= sanitize($b['category'] ?? '-') ?></td>
                        <td><?= $b['available_copies'] ?> / <?= $b['total_copies'] ?></td>
                        <td><?= sanitize($b['shelf_location'] ?? '-') ?></td>
                        <td>
                            <a href="?delete=<?= $b['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Remove this book?">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
