<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── NEW ADMISSION ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admit'])) {
    $patient_id = (int)$_POST['patient_id'];
    $doctor_id  = (int)$_POST['doctor_id'];
    $ward_id    = (int)$_POST['ward_id'];
    $bed_no     = trim($_POST['bed_no'] ?? '');
    $diagnosis  = trim($_POST['diagnosis_at_admission'] ?? '');
    $created_by = $_SESSION['user']['id'] ?? null;

    if ($patient_id <= 0 || $doctor_id <= 0 || $ward_id <= 0) {
        $err = "Patient, attending doctor, and ward are required.";
    } else {
        // Check if patient already admitted
        $chk = $conn->query("SELECT id FROM admissions WHERE patient_id=$patient_id AND status='Admitted'");
        if ($chk->num_rows > 0) {
            $err = "Patient is already currently admitted.";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, ward_id, bed_no, diagnosis_at_admission, status, created_by) VALUES (?, ?, ?, ?, ?, 'Admitted', ?)");
                $stmt->bind_param("iiissi", $patient_id, $doctor_id, $ward_id, $bed_no, $diagnosis, $created_by);
                $stmt->execute();

                // Update available beds count in ward
                $conn->query("UPDATE wards SET available_beds = GREATEST(0, available_beds - 1) WHERE id = $ward_id");

                $conn->commit();
                $msg = "Patient successfully admitted to ward.";
            } catch (Exception $e) {
                $conn->rollback();
                $err = "Admission failed: " . $e->getMessage();
            }
        }
    }
}

// ── DISCHARGE PATIENT ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_discharge'])) {
    $admission_id    = (int)$_POST['admission_id'];
    $discharge_notes = trim($_POST['discharge_notes'] ?? '');

    if ($admission_id > 0) {
        $adm = $conn->query("SELECT ward_id FROM admissions WHERE id=$admission_id AND status='Admitted'")->fetch_assoc();
        if ($adm) {
            $ward_id = $adm['ward_id'];
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE admissions SET status='Discharged', discharge_date=NOW(), discharge_notes=? WHERE id=?");
                $stmt->bind_param("si", $discharge_notes, $admission_id);
                $stmt->execute();

                if ($ward_id) {
                    $conn->query("UPDATE wards SET available_beds = LEAST(total_beds, available_beds + 1) WHERE id = $ward_id");
                }

                $conn->commit();
                $msg = "Patient successfully discharged.";
            } catch (Exception $e) {
                $conn->rollback();
                $err = "Discharge failed: " . $e->getMessage();
            }
        }
    }
}

// ── ADD WARD ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_ward'])) {
    $ward_name   = trim($_POST['ward_name'] ?? '');
    $ward_type   = trim($_POST['ward_type'] ?? 'General');
    $total_beds  = (int)$_POST['total_beds'];
    $charge_day  = (float)$_POST['charge_per_day'];

    if (!empty($ward_name) && $total_beds > 0) {
        $stmt = $conn->prepare("INSERT INTO wards (ward_name, ward_type, total_beds, available_beds, charge_per_day) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiid", $ward_name, $ward_type, $total_beds, $total_beds, $charge_day);
        if ($stmt->execute()) $msg = "New Ward created successfully.";
        else $err = "Failed to create ward: " . $conn->error;
    }
}

// ── FETCH DATA ─────────────────────────────────────────────────────────────
$status_filter = $_GET['status'] ?? 'Admitted';
$search_q      = trim($_GET['q'] ?? '');

$where = ["1=1"];
if ($status_filter !== 'All') {
    $where[] = "a.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search_q !== '') {
    $st = "%" . $conn->real_escape_string($search_q) . "%";
    $where[] = "(p.full_name LIKE '$st' OR p.patient_code LIKE '$st' OR d.name LIKE '$st' OR w.ward_name LIKE '$st')";
}

$where_sql = implode(" AND ", $where);
$admissions = $conn->query("
    SELECT a.*, p.full_name AS patient_name, p.patient_code, p.blood_group, p.phone,
           d.name AS doctor_name, w.ward_name, w.ward_type, w.charge_per_day
    FROM admissions a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN wards w ON w.id = a.ward_id
    WHERE $where_sql
    ORDER BY a.admission_date DESC
");

$wards = $conn->query("SELECT * FROM wards ORDER BY ward_name ASC");
$active_patients = $conn->query("SELECT id, full_name, patient_code FROM patients WHERE is_active=1 ORDER BY full_name ASC");
$doctors = $conn->query("SELECT id, name, specialization FROM doctors WHERE is_active=1 ORDER BY name ASC");

// Quick stats
$stat_admitted = $conn->query("SELECT COUNT(*) c FROM admissions WHERE status='Admitted'")->fetch_assoc()['c'];
$stat_beds_avail = $conn->query("SELECT COALESCE(SUM(available_beds),0) c FROM wards")->fetch_assoc()['c'];
$stat_total_beds = $conn->query("SELECT COALESCE(SUM(total_beds),0) c FROM wards")->fetch_assoc()['c'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">🛏 Admissions & Wards</h1>
    <p class="text-muted mb-0">Inpatient management, ward bed allocation, and discharge tracking</p>
  </div>
  <div>
    <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#wardModal">
      <i class="bi bi-plus-lg"></i> + Add Ward
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#admitModal">
      <i class="bi bi-box-arrow-in-right"></i> + New Admission
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-primary">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 me-3 fs-4"><i class="bi bi-person-workspace"></i></div>
        <div>
          <h3 class="fw-bold mb-0"><?= $stat_admitted ?></h3>
          <small class="text-muted">Currently Admitted Patients</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-success">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-success bg-opacity-10 text-success p-3 me-3 fs-4"><i class="bi bi-door-open"></i></div>
        <div>
          <h3 class="fw-bold mb-0 text-success"><?= $stat_beds_avail ?> / <?= $stat_total_beds ?></h3>
          <small class="text-muted">Available Beds across Wards</small>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card p-3 border-start border-4 border-info">
      <div class="d-flex align-items-center">
        <div class="rounded-circle bg-info bg-opacity-10 text-info p-3 me-3 fs-4"><i class="bi bi-pie-chart"></i></div>
        <div>
          <h3 class="fw-bold mb-0 text-info">
            <?= $stat_total_beds > 0 ? round((($stat_total_beds - $stat_beds_avail) / $stat_total_beds) * 100) : 0 ?>%
          </h3>
          <small class="text-muted">Bed Occupancy Rate</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Ward Availability Cards -->
<h5 class="fw-bold mb-3"><i class="bi bi-hospital text-primary"></i> Ward Overview</h5>
<div class="row g-3 mb-4">
  <?php while ($w = $wards->fetch_assoc()): ?>
    <div class="col-md-4 col-lg-3">
      <div class="card p-3 h-100 shadow-sm border-0">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="fw-bold mb-0"><?= htmlspecialchars($w['ward_name']) ?></h6>
          <span class="badge bg-secondary"><?= htmlspecialchars($w['ward_type']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center my-2">
          <span class="small text-muted">Beds Available:</span>
          <span class="badge <?= $w['available_beds'] > 0 ? 'bg-success' : 'bg-danger' ?> fs-6">
            <?= $w['available_beds'] ?> / <?= $w['total_beds'] ?>
          </span>
        </div>
        <small class="text-muted">Daily Rate: ₹<?= number_format($w['charge_per_day'], 2) ?></small>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<!-- Admissions Table -->
<div class="card shadow-sm">
  <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-list-check text-primary"></i> Patient Admissions Record</h5>
    
    <form method="GET" class="d-flex gap-2 align-items-center">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Search patient/doctor/ward..." value="<?= htmlspecialchars($search_q) ?>">
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="Admitted" <?= $status_filter === 'Admitted' ? 'selected' : '' ?>>Admitted</option>
        <option value="Discharged" <?= $status_filter === 'Discharged' ? 'selected' : '' ?>>Discharged</option>
        <option value="All" <?= $status_filter === 'All' ? 'selected' : '' ?>>All Statuses</option>
      </select>
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Patient Details</th>
          <th>Ward / Bed</th>
          <th>Attending Doctor</th>
          <th>Admission Date</th>
          <th>Diagnosis</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($admissions->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">No admission records found matching filters.</td></tr>
        <?php else: ?>
          <?php while ($adm = $admissions->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="fw-bold"><?= htmlspecialchars($adm['patient_name']) ?></div>
                <div class="small text-muted"><?= $adm['patient_code'] ?> &bull; <?= $adm['blood_group'] ?> &bull; <?= $adm['phone'] ?></div>
              </td>
              <td>
                <span class="badge bg-light text-primary border fw-bold"><?= htmlspecialchars($adm['ward_name'] ?: 'Unassigned') ?></span>
                <?php if ($adm['bed_no']): ?>
                  <span class="badge bg-info text-dark">Bed #<?= htmlspecialchars($adm['bed_no']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($adm['doctor_name']) ?></div>
              </td>
              <td>
                <div><?= date('d M Y, h:i A', strtotime($adm['admission_date'])) ?></div>
                <?php if ($adm['discharge_date']): ?>
                  <small class="text-muted">Discharged: <?= date('d M Y', strtotime($adm['discharge_date'])) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <small><?= htmlspecialchars($adm['diagnosis_at_admission'] ?: 'N/A') ?></small>
              </td>
              <td>
                <?php if ($adm['status'] === 'Admitted'): ?>
                  <span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i> Admitted</span>
                <?php else: ?>
                  <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i> Discharged</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?php if ($adm['status'] === 'Admitted'): ?>
                  <button class="btn btn-sm btn-outline-danger" onclick="openDischarge(<?= $adm['id'] ?>, '<?= htmlspecialchars($adm['patient_name'], ENT_QUOTES) ?>')">
                    <i class="bi bi-box-arrow-right me-1"></i> Discharge
                  </button>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-secondary" disabled>Discharged</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: New Admission -->
<div class="modal fade" id="admitModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_admit" value="1">
        <div class="modal-header">
          <h5 class="modal-title">New Inpatient Admission</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Select Patient *</label>
            <select name="patient_id" class="form-select" required>
              <option value="">-- Choose Patient --</option>
              <?php while ($p = $active_patients->fetch_assoc()): ?>
                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_code'] ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Attending Doctor *</label>
            <select name="doctor_id" class="form-select" required>
              <option value="">-- Choose Doctor --</option>
              <?php while ($d = $doctors->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= $d['specialization'] ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Ward *</label>
            <select name="ward_id" class="form-select" required>
              <option value="">-- Select Ward --</option>
              <?php 
              $wards->data_seek(0);
              while ($w = $wards->fetch_assoc()): 
              ?>
                <option value="<?= $w['id'] ?>" <?= $w['available_beds'] <= 0 ? 'disabled' : '' ?>>
                  <?= htmlspecialchars($w['ward_name']) ?> (<?= $w['available_beds'] ?> beds free - ₹<?= number_format($w['charge_per_day'], 0) ?>/day)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Bed Number / Designation</label>
            <input type="text" name="bed_no" class="form-control" placeholder="e.g. Bed 104-B">
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Diagnosis at Admission</label>
            <textarea name="diagnosis_at_admission" class="form-control" rows="3" placeholder="Primary condition, symptoms, or reason for admission..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i> Admit Patient</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Discharge Patient -->
<div class="modal fade" id="dischargeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_discharge" value="1">
        <input type="hidden" name="admission_id" id="dc_admission_id" value="0">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title">Discharge Patient</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p>Discharging patient: <strong id="dc_patient_name"></strong></p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Discharge Summary & Notes</label>
            <textarea name="discharge_notes" class="form-control" rows="4" placeholder="Recovery status, advice on discharge, prescribed medications..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-check-circle me-1"></i> Confirm Discharge</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Add Ward -->
<div class="modal fade" id="wardModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_add_ward" value="1">
        <div class="modal-header">
          <h5 class="modal-title">Create New Ward</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body g-3">
          <div class="mb-3">
            <label class="form-label fw-semibold">Ward Name *</label>
            <input type="text" name="ward_name" class="form-control" required placeholder="e.g. ICU Wing A">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Ward Type</label>
            <select name="ward_type" class="form-select">
              <option value="General">General</option>
              <option value="ICU">ICU</option>
              <option value="Maternity">Maternity</option>
              <option value="Pediatric">Pediatric</option>
              <option value="Private">Private</option>
              <option value="Semi-Private">Semi-Private</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Total Bed Capacity *</label>
            <input type="number" name="total_beds" class="form-control" required value="10">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Daily Room Charge (₹)</label>
            <input type="number" step="0.01" name="charge_per_day" class="form-control" value="1500.00">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Ward</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openDischarge(id, name) {
  document.getElementById('dc_admission_id').value = id;
  document.getElementById('dc_patient_name').innerText = name;
  var modal = new bootstrap.Modal(document.getElementById('dischargeModal'));
  modal.show();
}
</script>

<?php require 'partials/footer.php'; ?>
