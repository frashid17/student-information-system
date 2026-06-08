<?php
$pageTitle = 'Fee Records';
require_once __DIR__ . '/../../includes/init.php';
requireRole(['super_admin', 'admin']);

$pdo = getDBConnection();
$students = $pdo->query("SELECT id, student_number, first_name, last_name FROM students WHERE status = 'active' ORDER BY first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiptNumber = generateReceiptNumber($pdo);

    $stmt = $pdo->prepare("INSERT INTO fee_payments (student_id, amount, trimester, academic_year, payment_method, receipt_number, payment_date, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['student_id'],
        $_POST['amount'],
        $_POST['trimester'],
        $_POST['academic_year'],
        $_POST['payment_method'],
        $receiptNumber,
        $_POST['payment_date'],
        trim($_POST['notes'] ?? ''),
        $_SESSION['user_id']
    ]);

    setFlash('success', "Payment recorded. Receipt: $receiptNumber");
    redirect(BASE_URL . '/modules/fees/payments.php?receipt=' . $pdo->lastInsertId());
}

$payments = $pdo->query("
    SELECT fp.*, s.student_number, s.first_name, s.last_name, u.full_name as recorded_by_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    LEFT JOIN users u ON fp.recorded_by = u.id
    ORDER BY fp.created_at DESC
")->fetchAll();

$receipt = null;
if (isset($_GET['receipt'])) {
    $stmt = $pdo->prepare("
        SELECT fp.*, s.student_number, s.first_name, s.last_name, s.middle_name, p.program_name
        FROM fee_payments fp
        JOIN students s ON fp.student_id = s.id
        LEFT JOIN programs p ON s.program_id = p.id
        WHERE fp.id = ?
    ");
    $stmt->execute([$_GET['receipt']]);
    $receipt = $stmt->fetch();
}

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
<?php endif; ?>

<?php if ($receipt): ?>
<div class="card no-print">
    <div class="card-header">
        <h3>Payment Receipt</h3>
        <button onclick="printSection('receiptPrint')" class="btn btn-primary btn-sm">Print Receipt</button>
    </div>
    <div class="card-body" id="receiptPrint">
        <div class="receipt-box">
            <div class="receipt-header">
                <img src="<?= LOGO_PATH ?>" alt="<?= APP_INSTITUTION ?>" class="brand-logo brand-logo--receipt">
                <p>Fee Payment Receipt</p>
            </div>
            <div class="detail-grid">
                <div class="detail-item"><label>Receipt No.</label><span><?= sanitize($receipt['receipt_number']) ?></span></div>
                <div class="detail-item"><label>Date</label><span><?= formatDate($receipt['payment_date']) ?></span></div>
                <div class="detail-item"><label>Student No.</label><span><?= sanitize($receipt['student_number']) ?></span></div>
                <div class="detail-item"><label>Student Name</label><span><?= sanitize($receipt['first_name'] . ' ' . $receipt['last_name']) ?></span></div>
                <div class="detail-item"><label>Program</label><span><?= sanitize($receipt['program_name'] ?? '-') ?></span></div>
                <div class="detail-item"><label>Trimester</label><span><?= sanitize($receipt['trimester']) ?></span></div>
                <div class="detail-item"><label>Academic Year</label><span><?= sanitize($receipt['academic_year']) ?></span></div>
                <div class="detail-item"><label>Payment Method</label><span><?= sanitize(ucfirst(str_replace('_', ' ', $receipt['payment_method']))) ?></span></div>
                <div class="detail-item"><label>Amount Paid</label><span style="font-size:1.2rem;font-weight:700;color:var(--success);"><?= formatCurrency((float)$receipt['amount']) ?></span></div>
            </div>
            <?php if ($receipt['notes']): ?>
            <p style="margin-top:16px;"><strong>Notes:</strong> <?= sanitize($receipt['notes']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Record Fee Payment</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Student *</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['student_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (KES) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-row">
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
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" class="form-control" required>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Record Payment</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Payment History</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Student</th>
                        <th>Amount</th>
                        <th>Trimester</th>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= sanitize($p['receipt_number']) ?></td>
                        <td><?= sanitize($p['student_number'] . ' - ' . $p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= formatCurrency((float)$p['amount']) ?></td>
                        <td><?= sanitize($p['trimester']) ?></td>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><?= sanitize(ucfirst(str_replace('_', ' ', $p['payment_method']))) ?></td>
                        <td><a href="?receipt=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">View Receipt</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
