<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── ADD / EDIT MEDICINE ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_medicine'])) {
    $med_id        = (int)($_POST['med_id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $generic_name  = trim($_POST['generic_name'] ?? '');
    $category      = trim($_POST['category'] ?? '');
    $dosage_form   = trim($_POST['dosage_form'] ?? 'Tablet');
    $strength      = trim($_POST['strength'] ?? '');
    $manufacturer  = trim($_POST['manufacturer'] ?? '');
    $unit_price    = (float)($_POST['unit_price'] ?? 0);
    $stock_qty     = (int)($_POST['stock_qty'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 10);
    $expiry_date   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $batch_no      = trim($_POST['batch_no'] ?? '');

    if (empty($name)) {
        $err = "Medicine name is required.";
    } else {
        if ($med_id > 0) {
            $stmt = $conn->prepare("UPDATE medicines SET name=?, generic_name=?, category=?, dosage_form=?, strength=?, manufacturer=?, unit_price=?, stock_qty=?, reorder_level=?, expiry_date=?, batch_no=? WHERE id=?");
            $stmt->bind_param("ssssssdiissi", $name, $generic_name, $category, $dosage_form, $strength, $manufacturer, $unit_price, $stock_qty, $reorder_level, $expiry_date, $batch_no, $med_id);
            if ($stmt->execute()) $msg = "Medicine updated successfully.";
            else $err = "Failed to update medicine: " . $conn->error;
        } else {
            $stmt = $conn->prepare("INSERT INTO medicines (name, generic_name, category, dosage_form, strength, manufacturer, unit_price, stock_qty, reorder_level, expiry_date, batch_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdiiss", $name, $generic_name, $category, $dosage_form, $strength, $manufacturer, $unit_price, $stock_qty, $reorder_level, $expiry_date, $batch_no);
            if ($stmt->execute()) $msg = "Medicine added to inventory successfully.";
            else $err = "Failed to add medicine: " . $conn->error;
        }
    }
}

// ── DISPENSE MEDICINE / STOCK ADJUSTMENT ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_stock'])) {
    $med_id = (int)$_POST['med_id'];
    $adjust_qty = (int)$_POST['adjust_qty']; // positive to restock, negative to dispense
    
    if ($med_id > 0 && $adjust_qty != 0) {
        $stmt = $conn->prepare("UPDATE medicines SET stock_qty = GREATEST(0, stock_qty + ?) WHERE id = ?");
        $stmt->bind_param("ii", $adjust_qty, $med_id);
        if ($stmt->execute()) $msg = "Stock quantity updated successfully.";
        else $err = "Failed to update stock: " . $conn->error;
    }
}

// ── FETCH MEDICINES ────────────────────────────────────────────────────────
$search_q  = trim($_GET['q'] ?? '');
$cat_filter = trim($_GET['cat'] ?? '');
$low_only  = isset($_GET['low_stock']) ? 1 : 0;

$where = ["is_active=1"];
$params = [];
$types = "";

if ($search_q !== '') {
    $where[] = "(name LIKE ? OR generic_name LIKE ? OR manufacturer LIKE ? OR batch_no LIKE ?)";
    $st = "%$search_q%";
    $params = array_merge($params, [$st, $st, $st, $st]);
    $types .= "ssss";
}

if ($cat_filter !== '') {
    $where[] = "category = ?";
    $params[] = $cat_filter;
    $types .= "s";
}

if ($low_only) {
    $where[] = "stock_qty <= reorder_level";
}

$where_sql = implode(" AND ", $where);
$sql = "SELECT * FROM medicines WHERE $where_sql ORDER BY (stock_qty <= reorder_level) DESC, name ASC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $medicines = $stmt->get_result();
} else {
    $medicines = $conn->query($sql);
}

$categories = $conn->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");

// Quick stats
$stat_total_items = $conn->query("SELECT COUNT(*) c FROM medicines WHERE is_active=1")->fetch_assoc()['c'];
$stat_low_stock  = $conn->query("SELECT COUNT(*) c FROM medicines WHERE is_active=1 AND stock_qty <= reorder_level")->fetch_assoc()['c'];
$stat_out_stock  = $conn->query("SELECT COUNT(*) c FROM medicines WHERE is_active=1 AND stock_qty = 0")->fetch_assoc()['c'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">💊 Pharmacy & Inventory</h1>
    <p class="text-muted mb-0">Track medicine stock, pricing, batch expiry, and reorder alerts</p>
  </div>
  <div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#medModal" onclick="clearMedForm()">
      <i class="bi bi-capsule"></i> + Add New Medicine
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-primary">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3 fs-4"><i class="bi bi-boxes"></i></div>
        <div>
          <h3 class="fw-bold mb-0"><?= $stat_total_items ?></h3>
          <small class="text-muted">Total Medicines Listed</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-warning">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-warning bg-opacity-10 text-warning p-3 me-3 fs-4"><i class="bi bi-exclamation-triangle"></i></div>
        <div>
          <h3 class="fw-bold mb-0 text-warning"><?= $stat_low_stock ?></h3>
          <small class="text-muted">Low Stock Warnings</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-danger">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-danger bg-opacity-10 text-danger p-3 me-3 fs-4"><i class="bi bi-x-circle"></i></div>
        <div>
          <h3 class="fw-bold mb-0 text-danger"><?= $stat_out_stock ?></h3>
          <small class="text-muted">Out of Stock</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card p-3 mb-4">
  <form method="GET" class="row g-3 align-items-center">
    <div class="col-md-5">
      <div class="input-group">
        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search medicine, generic name, batch..." value="<?= htmlspecialchars($search_q) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <select name="cat" class="form-select" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php while ($cat = $categories->fetch_assoc()): ?>
          <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $cat_filter === $cat['category'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-2">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="low_stock_chk" <?= $low_only ? 'checked' : '' ?> onchange="this.form.submit()">
        <label class="form-check-label small fw-semibold" for="low_stock_chk">Low Stock Only</label>
      </div>
    </div>
    <div class="col-md-2 text-end">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
    </div>
  </form>
</div>

<!-- Inventory Table -->
<div class="card shadow-sm">
  <div class="card-header bg-white py-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-table text-primary"></i> Inventory Master List</h5>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Medicine Name</th>
          <th>Form & Strength</th>
          <th>Batch / Expiry</th>
          <th>Unit Price</th>
          <th>Stock Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($medicines->num_rows === 0): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">No medicines found matching criteria.</td></tr>
        <?php else: ?>
          <?php while ($med = $medicines->fetch_assoc()): ?>
            <?php
              $is_low = $med['stock_qty'] <= $med['reorder_level'];
              $is_empty = $med['stock_qty'] === 0;
            ?>
            <tr>
              <td>
                <div class="fw-bold"><?= htmlspecialchars($med['name']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars($med['generic_name'] ?: 'N/A') ?></div>
              </td>
              <td>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars($med['dosage_form']) ?></span>
                <small class="text-muted ms-1"><?= htmlspecialchars($med['strength']) ?></small>
              </td>
              <td>
                <div class="small"><strong>Batch:</strong> <?= htmlspecialchars($med['batch_no'] ?: 'N/A') ?></div>
                <div class="small text-muted">
                  <strong>Exp:</strong> 
                  <?php if ($med['expiry_date']): ?>
                    <span class="<?= strtotime($med['expiry_date']) < strtotime('+30 days') ? 'text-danger fw-bold' : '' ?>">
                      <?= date('M Y', strtotime($med['expiry_date'])) ?>
                    </span>
                  <?php else: ?>
                    N/A
                  <?php endif; ?>
                </div>
              </td>
              <td class="fw-bold text-success">
                ₹<?= number_format($med['unit_price'], 2) ?>
              </td>
              <td>
                <div class="d-flex align-items-center">
                  <div class="me-2">
                    <?php if ($is_empty): ?>
                      <span class="badge bg-danger">OUT OF STOCK (0)</span>
                    <?php elseif ($is_low): ?>
                      <span class="badge bg-warning text-dark">LOW STOCK (<?= $med['stock_qty'] ?>)</span>
                    <?php else: ?>
                      <span class="badge bg-success"><?= $med['stock_qty'] ?> in stock</span>
                    <?php endif; ?>
                  </div>
                </div>
                <small class="text-muted">Reorder level: <?= $med['reorder_level'] ?></small>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-outline-success me-1" onclick="quickRestock(<?= $med['id'] ?>, '<?= htmlspecialchars($med['name'], ENT_QUOTES) ?>')">
                  <i class="bi bi-plus-circle"></i> Stock
                </button>
                <button class="btn btn-sm btn-outline-primary" onclick='editMed(<?= json_encode($med) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add/Edit Medicine -->
<div class="modal fade" id="medModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_medicine" value="1">
        <input type="hidden" name="med_id" id="med_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="medModalTitle">Add New Medicine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Medicine Brand Name *</label>
            <input type="text" name="name" id="med_name" class="form-control" required placeholder="Paracetamol 500mg">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Generic Name</label>
            <input type="text" name="generic_name" id="med_generic" class="form-control" placeholder="Acetaminophen">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Category</label>
            <input type="text" name="category" id="med_category" class="form-control" placeholder="Analgesics, Antibiotics...">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Dosage Form</label>
            <select name="dosage_form" id="med_dosage" class="form-select">
              <option value="Tablet">Tablet</option>
              <option value="Capsule">Capsule</option>
              <option value="Syrup">Syrup</option>
              <option value="Injection">Injection</option>
              <option value="Ointment">Ointment</option>
              <option value="Drops">Drops</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Strength</label>
            <input type="text" name="strength" id="med_strength" class="form-control" placeholder="500mg / 5ml">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Manufacturer</label>
            <input type="text" name="manufacturer" id="med_manufacturer" class="form-control" placeholder="Pharma Corp Ltd.">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Batch No.</label>
            <input type="text" name="batch_no" id="med_batch" class="form-control" placeholder="BATCH-2026-X">
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Unit Price (₹) *</label>
            <input type="number" step="0.01" name="unit_price" id="med_price" class="form-control" required value="10.00">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Current Stock Qty *</label>
            <input type="number" name="stock_qty" id="med_stock" class="form-control" required value="100">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Reorder Alert Level</label>
            <input type="number" name="reorder_level" id="med_reorder" class="form-control" value="20">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Expiry Date</label>
            <input type="date" name="expiry_date" id="med_expiry" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Medicine</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Quick Restock / Adjust -->
<div class="modal fade" id="restockModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_stock" value="1">
        <input type="hidden" name="med_id" id="stock_med_id" value="0">
        <div class="modal-header">
          <h5 class="modal-title">Adjust Stock Level</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Adjust stock quantity for: <strong id="stock_med_name"></strong></p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Quantity Change</label>
            <input type="number" name="adjust_qty" class="form-control" placeholder="+50 (restock) or -10 (dispensed/damaged)" required>
            <div class="form-text">Enter positive number to add stock, or negative number to reduce stock.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i> Update Stock</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function clearMedForm() {
  document.getElementById('med_id').value = '0';
  document.getElementById('med_name').value = '';
  document.getElementById('med_generic').value = '';
  document.getElementById('med_category').value = '';
  document.getElementById('med_dosage').value = 'Tablet';
  document.getElementById('med_strength').value = '';
  document.getElementById('med_manufacturer').value = '';
  document.getElementById('med_batch').value = '';
  document.getElementById('med_price').value = '10.00';
  document.getElementById('med_stock').value = '100';
  document.getElementById('med_reorder').value = '20';
  document.getElementById('med_expiry').value = '';
  document.getElementById('medModalTitle').innerText = 'Add New Medicine';
}

function editMed(med) {
  document.getElementById('med_id').value = med.id;
  document.getElementById('med_name').value = med.name;
  document.getElementById('med_generic').value = med.generic_name || '';
  document.getElementById('med_category').value = med.category || '';
  document.getElementById('med_dosage').value = med.dosage_form || 'Tablet';
  document.getElementById('med_strength').value = med.strength || '';
  document.getElementById('med_manufacturer').value = med.manufacturer || '';
  document.getElementById('med_batch').value = med.batch_no || '';
  document.getElementById('med_price').value = med.unit_price || '0.00';
  document.getElementById('med_stock').value = med.stock_qty || '0';
  document.getElementById('med_reorder').value = med.reorder_level || '10';
  document.getElementById('med_expiry').value = med.expiry_date || '';
  document.getElementById('medModalTitle').innerText = 'Edit Medicine Details';
  
  var modal = new bootstrap.Modal(document.getElementById('medModal'));
  modal.show();
}

function quickRestock(id, name) {
  document.getElementById('stock_med_id').value = id;
  document.getElementById('stock_med_name').innerText = name;
  var modal = new bootstrap.Modal(document.getElementById('restockModal'));
  modal.show();
}
</script>

<?php require 'partials/footer.php'; ?>
