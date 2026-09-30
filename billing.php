<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── CREATE bill ──────────────────────────────────────────────────────────────
if (isset($_POST['create_bill'])) {
    $bill_no = 'BILL' . date('Ymd') . rand(100, 999);
    $patient = (int)$_POST['patient_id'];
    $date    = $_POST['bill_date'];
    $method  = $_POST['payment_method'];
    $notes   = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("
        INSERT INTO bills (bill_no, patient_id, bill_date, notes, payment_method, created_by)
        VALUES (?,?,?,?,?,?)
    ");
    $uid = $_SESSION['user']['id'];
    $stmt->bind_param("sisssi", $bill_no, $patient, $date, $notes, $method, $uid);
    if ($stmt->execute()) {
        $bill_id = $conn->insert_id;

        // Insert line items
        $descriptions = $_POST['description'] ?? [];
        $qtys         = $_POST['qty']         ?? [];
        $prices       = $_POST['unit_price']  ?? [];
        $types        = $_POST['item_type']   ?? [];

        $sub = 0;
        $ist = $conn->prepare("INSERT INTO bill_items (bill_id, item_type, description, quantity, unit_price) VALUES (?,?,?,?,?)");
        foreach ($descriptions as $i => $desc) {
            if (!trim($desc)) continue;
            $qty   = (int)($qtys[$i]   ?? 1);
            $price = (float)($prices[$i] ?? 0);
            $type  = $types[$i] ?? 'Other';
            $ist->bind_param("issid", $bill_id, $type, $desc, $qty, $price);
            $ist->execute();
            $sub += $qty * $price;
        }

        // Recalculate totals
        $discount = (float)($_POST['discount'] ?? 0);
        $tax      = (float)($_POST['tax']      ?? 0);
        $total    = $sub - $discount + $tax;
        $conn->query("UPDATE bills SET subtotal=$sub, discount=$discount, tax=$tax, total_amount=$total WHERE id=$bill_id");

        // If paid at creation, record payment
        if (!empty($_POST['pay_now']) && $total > 0) {
            $ref  = trim($_POST['transaction_ref'] ?? '');
            $pst  = $conn->prepare("INSERT INTO payments (bill_id, amount, method, transaction_ref, received_by) VALUES (?,?,?,?,?)");
            $pst->bind_param("idssi", $bill_id, $total, $method, $ref, $uid);
            $pst->execute();
            $conn->query("UPDATE bills SET paid_amount=$total, status='Paid' WHERE id=$bill_id");
        }

        $msg = "Bill <strong>$bill_no</strong> created successfully.";
    } else {
        $err = $conn->error;
    }
}

// ── MARK paid ────────────────────────────────────────────────────────────────
if (isset($_GET['pay'])) {
    $bid    = (int)$_GET['pay'];
    $row    = $conn->query("SELECT total_amount, payment_method FROM bills WHERE id=$bid")->fetch_assoc();
    $amount = $row['total_amount'];
    $method = $row['payment_method'] ?? 'Cash';
    $uid    = $_SESSION['user']['id'];
    $conn->query("INSERT INTO payments (bill_id, amount, method, received_by) VALUES ($bid, $amount, '$method', $uid)");
    $conn->query("UPDATE bills SET paid_amount=total_amount, status='Paid' WHERE id=$bid");
    header("Location: billing.php");
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$patients = $conn->query("SELECT id, full_name, patient_code FROM patients WHERE is_active=1 ORDER BY full_name");
$bills    = $conn->query("
    SELECT b.*, p.full_name patient_name, p.patient_code,
           (SELECT COALESCE(SUM(amount),0) FROM payments WHERE bill_id=b.id) paid_sum
    FROM bills b
    JOIN patients p ON p.id = b.patient_id
    ORDER BY b.created_at DESC
    LIMIT 50
");
$total_revenue  = $conn->query("SELECT COALESCE(SUM(paid_amount),0) c FROM bills WHERE status='Paid'")->fetch_assoc()['c'];
$pending_amount = $conn->query("SELECT COALESCE(SUM(balance),0) c FROM bills WHERE status IN ('Pending','Partial')")->fetch_assoc()['c'];
?>

<div class="page-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="page-title">Billing</h1>
    <p class="text-muted mb-0">Manage patient invoices and payments</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#billForm">
    💳 Create Bill
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3 text-center">
      <div class="text-muted">Total Revenue Collected</div>
      <div class="stat text-success">₹<?= number_format($total_revenue, 2) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 text-center">
      <div class="text-muted">Pending / Outstanding</div>
      <div class="stat text-warning">₹<?= number_format($pending_amount, 2) ?></div>
    </div>
  </div>
</div>

<!-- Create Bill Form -->
<div class="collapse mb-4" id="billForm">
<div class="card p-4">
  <h5 class="mb-3">Create New Bill</h5>
  <form method="post" id="billFormEl">
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <label class="form-label">Patient *</label>
        <select class="form-select" name="patient_id" required>
          <option value="">— Select Patient —</option>
          <?php while ($p = $patients->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_code'] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Bill Date</label>
        <input type="date" class="form-control" name="bill_date" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Payment Method</label>
        <select class="form-select" name="payment_method">
          <option>Cash</option><option>Card</option><option>UPI</option>
          <option>Insurance</option><option>Online</option><option>Other</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Notes</label>
        <input class="form-control" name="notes" placeholder="Optional notes">
      </div>
    </div>

    <!-- Line Items -->
    <h6 class="mb-2">Bill Items</h6>
    <div id="lineItems">
      <div class="row g-2 mb-2 line-item">
        <div class="col-md-3"><input class="form-control form-control-sm" name="description[]" placeholder="Description" required></div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" name="item_type[]">
            <option>Consultation</option><option>Lab</option><option>Medicine</option>
            <option>Ward</option><option>Procedure</option><option>Other</option>
          </select>
        </div>
        <div class="col-md-2"><input type="number" class="form-control form-control-sm qty" name="qty[]" value="1" min="1"></div>
        <div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm price" name="unit_price[]" placeholder="Unit Price"></div>
        <div class="col-md-2"><input class="form-control form-control-sm" name="amount_display" placeholder="Amount" readonly></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-line">×</button></div>
      </div>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addLine">+ Add Item</button>

    <div class="row g-3 align-items-end">
      <div class="col-md-2">
        <label class="form-label">Discount (₹)</label>
        <input type="number" step="0.01" class="form-control" name="discount" value="0" id="discountInput">
      </div>
      <div class="col-md-2">
        <label class="form-label">Tax (₹)</label>
        <input type="number" step="0.01" class="form-control" name="tax" value="0" id="taxInput">
      </div>
      <div class="col-md-3">
        <label class="form-label">Grand Total</label>
        <input class="form-control fw-bold" id="grandTotal" readonly placeholder="₹ 0.00">
      </div>
      <div class="col-md-3">
        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="pay_now" id="payNow" value="1">
          <label class="form-check-label" for="payNow">Mark as Paid Now</label>
        </div>
        <div class="mt-2" id="refField" style="display:none">
          <input class="form-control form-control-sm" name="transaction_ref" placeholder="Transaction Ref">
        </div>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" name="create_bill">Create Bill</button>
      </div>
    </div>
  </form>
</div>
</div>

<!-- Bills Table -->
<div class="card p-4">
  <h5 class="mb-3">Bills (Recent 50)</h5>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>Bill No.</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Method</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php if ($bills->num_rows === 0): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No bills found.</td></tr>
      <?php endif; ?>
      <?php while ($b = $bills->fetch_assoc()): ?>
        <tr>
          <td><code><?= $b['bill_no'] ?></code></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($b['patient_name']) ?></div>
            <small class="text-muted"><?= $b['patient_code'] ?></small>
          </td>
          <td><?= date('d M Y', strtotime($b['bill_date'])) ?></td>
          <td>₹<?= number_format($b['total_amount'], 2) ?></td>
          <td class="text-success">₹<?= number_format($b['paid_amount'], 2) ?></td>
          <td class="<?= $b['balance'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">
            ₹<?= number_format($b['balance'], 2) ?>
          </td>
          <td><?= $b['payment_method'] ?? '—' ?></td>
          <td>
            <?php $sc = ['Pending'=>'warning','Partial'=>'info','Paid'=>'success','Cancelled'=>'danger','Refunded'=>'secondary','Draft'=>'light']; ?>
            <span class="badge bg-<?= $sc[$b['status']] ?? 'secondary' ?>"><?= $b['status'] ?></span>
          </td>
          <td>
            <?php if (in_array($b['status'], ['Pending','Partial'])): ?>
              <a class="btn btn-sm btn-success" onclick="return confirm('Mark as fully paid?')" href="?pay=<?= $b['id'] ?>">Mark Paid</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Line item template
const template = `<div class="row g-2 mb-2 line-item">
  <div class="col-md-3"><input class="form-control form-control-sm" name="description[]" placeholder="Description" required></div>
  <div class="col-md-2"><select class="form-select form-select-sm" name="item_type[]">
    <option>Consultation</option><option>Lab</option><option>Medicine</option>
    <option>Ward</option><option>Procedure</option><option>Other</option>
  </select></div>
  <div class="col-md-2"><input type="number" class="form-control form-control-sm qty" name="qty[]" value="1" min="1"></div>
  <div class="col-md-2"><input type="number" step="0.01" class="form-control form-control-sm price" name="unit_price[]" placeholder="Unit Price"></div>
  <div class="col-md-2"><input class="form-control form-control-sm" name="amount_display" placeholder="Amount" readonly></div>
  <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-line">×</button></div>
</div>`;

document.getElementById('addLine').onclick = () => {
  const div = document.createElement('div');
  div.innerHTML = template;
  document.getElementById('lineItems').appendChild(div.firstElementChild);
  attachHandlers();
};

function calcTotal() {
  let sub = 0;
  document.querySelectorAll('.line-item').forEach(row => {
    const q = parseFloat(row.querySelector('.qty')?.value) || 0;
    const p = parseFloat(row.querySelector('.price')?.value) || 0;
    const amt = q * p;
    const ad = row.querySelector('[name="amount_display"]');
    if (ad) ad.value = amt > 0 ? '₹' + amt.toFixed(2) : '';
    sub += amt;
  });
  const disc = parseFloat(document.getElementById('discountInput').value) || 0;
  const tax  = parseFloat(document.getElementById('taxInput').value) || 0;
  document.getElementById('grandTotal').value = '₹' + (sub - disc + tax).toFixed(2);
}

function attachHandlers() {
  document.querySelectorAll('.qty, .price').forEach(el => el.oninput = calcTotal);
  document.querySelectorAll('.remove-line').forEach(btn => btn.onclick = () => { btn.closest('.line-item').remove(); calcTotal(); });
}
document.getElementById('discountInput').oninput = calcTotal;
document.getElementById('taxInput').oninput = calcTotal;
attachHandlers();

document.getElementById('payNow').onchange = function() {
  document.getElementById('refField').style.display = this.checked ? 'block' : 'none';
};
</script>

<?php require 'partials/footer.php'; ?>