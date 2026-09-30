<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── ADD patient ──────────────────────────────────────────────────────────────
if (isset($_POST['add'])) {
    $code  = 'P' . date('y') . str_pad($conn->query("SELECT COALESCE(MAX(id),0)+1 n FROM patients")->fetch_assoc()['n'], 5, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(20));

    $stmt = $conn->prepare("
        INSERT INTO patients
          (patient_code, full_name, dob, gender, phone, email, address, blood_group,
           emergency_contact_name, emergency_contact_phone, allergies, chronic_conditions,
           insurance_provider, insurance_policy_no, qr_token)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "sssssssssssssss",
        $code,
        $_POST['full_name'],
        $_POST['dob'],
        $_POST['gender'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['address'],
        $_POST['blood_group'],
        $_POST['emergency_contact_name'],
        $_POST['emergency_contact_phone'],
        $_POST['allergies'],
        $_POST['chronic_conditions'],
        $_POST['insurance_provider'],
        $_POST['insurance_policy_no'],
        $token
    );
    if ($stmt->execute()) {
        $msg = "Patient <strong>" . htmlspecialchars($_POST['full_name']) . "</strong> registered. Code: <strong>$code</strong>";
    } else {
        $err = "Error: " . $conn->error;
    }
}

// ── DELETE patient ───────────────────────────────────────────────────────────
if (isset($_GET['delete']) && $_SESSION['user']['role'] === 'admin') {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM patients WHERE id=$id");
    header("Location: patients.php");
    exit;
}

// ── Search ───────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$where  = $search ? "WHERE full_name LIKE ? OR phone LIKE ? OR patient_code LIKE ?" : "";
$sql    = "SELECT * FROM patients $where ORDER BY id DESC";
if ($search) {
    $stmt2 = $conn->prepare($sql);
    $like  = "%$search%";
    $stmt2->bind_param("sss", $like, $like, $like);
    $stmt2->execute();
    $rows = $stmt2->get_result();
} else {
    $rows = $conn->query($sql);
}
?>

<div class="page-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="page-title">Patients</h1>
    <p class="text-muted mb-0">Register and manage patient records</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#regForm">
    ➕ Register Patient
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Registration Form -->
<div class="collapse mb-4" id="regForm">
<div class="card p-4">
  <h5 class="mb-3">New Patient Registration</h5>
  <form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Full Name *</label>
      <input class="form-control" name="full_name" required placeholder="Full Name"></div>
    <div class="col-md-3"><label class="form-label">Date of Birth</label>
      <input class="form-control" type="date" name="dob"></div>
    <div class="col-md-3"><label class="form-label">Gender</label>
      <select class="form-select" name="gender">
        <option>Male</option><option>Female</option><option>Other</option><option>Prefer not to say</option>
      </select></div>
    <div class="col-md-4"><label class="form-label">Phone *</label>
      <input class="form-control" name="phone" required placeholder="Phone"></div>
    <div class="col-md-4"><label class="form-label">Email</label>
      <input class="form-control" type="email" name="email" placeholder="Email"></div>
    <div class="col-md-4"><label class="form-label">Blood Group</label>
      <select class="form-select" name="blood_group">
        <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown'] as $bg): ?>
          <option><?= $bg ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-12"><label class="form-label">Address</label>
      <input class="form-control" name="address" placeholder="Address"></div>
    <div class="col-md-6"><label class="form-label">Emergency Contact Name</label>
      <input class="form-control" name="emergency_contact_name" placeholder="Contact Name"></div>
    <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label>
      <input class="form-control" name="emergency_contact_phone" placeholder="Contact Phone"></div>
    <div class="col-md-6"><label class="form-label">Known Allergies</label>
      <input class="form-control" name="allergies" placeholder="e.g. Penicillin, Pollen"></div>
    <div class="col-md-6"><label class="form-label">Chronic Conditions</label>
      <input class="form-control" name="chronic_conditions" placeholder="e.g. Diabetes, Hypertension"></div>
    <div class="col-md-6"><label class="form-label">Insurance Provider</label>
      <input class="form-control" name="insurance_provider" placeholder="Insurance Company"></div>
    <div class="col-md-6"><label class="form-label">Insurance Policy No.</label>
      <input class="form-control" name="insurance_policy_no" placeholder="Policy Number"></div>
    <div class="col-12">
      <button class="btn btn-primary" name="add">Register &amp; Generate QR Token</button>
      <button type="button" class="btn btn-outline-secondary ms-2" data-bs-toggle="collapse" data-bs-target="#regForm">Cancel</button>
    </div>
  </form>
</div>
</div>

<!-- Search + Table -->
<div class="card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Registered Patients</h5>
    <form class="d-flex gap-2" method="get">
      <input class="form-control form-control-sm" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search name / phone / code…" style="width:240px">
      <button class="btn btn-sm btn-outline-primary">Search</button>
      <?php if ($search): ?><a href="patients.php" class="btn btn-sm btn-outline-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Code</th><th>Name</th><th>DOB</th><th>Gender</th><th>Phone</th>
          <th>Blood</th><th>Insurance</th><th>QR</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows->num_rows === 0): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No patients found.</td></tr>
      <?php endif; ?>
      <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><code><?= $r['patient_code'] ?></code></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($r['full_name']) ?></div>
            <?php if ($r['allergies']): ?>
              <small class="text-danger">⚠ <?= htmlspecialchars($r['allergies']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= $r['dob'] ? date('d M Y', strtotime($r['dob'])) : '—' ?></td>
          <td><?= htmlspecialchars($r['gender']) ?></td>
          <td><?= htmlspecialchars($r['phone']) ?></td>
          <td><span class="badge bg-danger"><?= $r['blood_group'] ?></span></td>
          <td><?= $r['insurance_provider'] ? htmlspecialchars($r['insurance_provider']) : '<span class="text-muted">—</span>' ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="patient_qr.php?id=<?= $r['id'] ?>">QR</a></td>
          <td>
            <a class="btn btn-sm btn-outline-info" href="records.php?patient_id=<?= $r['id'] ?>">Records</a>
            <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this patient?')" href="?delete=<?= $r['id'] ?>">Del</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'partials/footer.php'; ?>