<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── SAVE medical record ──────────────────────────────────────────────────────
if (isset($_POST['save'])) {
    $stmt = $conn->prepare("
        INSERT INTO medical_records
          (patient_id, doctor_id, appointment_id, visit_date, chief_complaint,
           diagnosis, icd_code, prescription, follow_up_date, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $appt_id     = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
    $follow_up   = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $stmt->bind_param(
        "iiissssssss",
        $_POST['patient_id'],
        $_POST['doctor_id'],
        $appt_id,
        $_POST['visit_date'],
        $_POST['chief_complaint'],
        $_POST['diagnosis'],
        $_POST['icd_code'],
        $_POST['prescription'],
        $follow_up,
        $_POST['notes']
    );

    // fix: 10 params not 11
    $stmt = $conn->prepare("
        INSERT INTO medical_records
          (patient_id, doctor_id, appointment_id, visit_date, chief_complaint,
           diagnosis, icd_code, prescription, follow_up_date, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        "iiisssssss",
        $_POST['patient_id'],
        $_POST['doctor_id'],
        $appt_id,
        $_POST['visit_date'],
        $_POST['chief_complaint'],
        $_POST['diagnosis'],
        $_POST['icd_code'],
        $_POST['prescription'],
        $follow_up,
        $_POST['notes']
    );

    if ($stmt->execute()) {
        $record_id = $conn->insert_id;
        // Mark appointment completed if linked
        if ($appt_id) {
            $conn->query("UPDATE appointments SET status='Completed' WHERE id=$appt_id");
        }
        $msg = "Medical record saved successfully.";
    } else {
        $err = $conn->error;
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$filter_patient = (int)($_GET['patient_id'] ?? 0);

$patients = $conn->query("SELECT id, full_name, patient_code FROM patients WHERE is_active=1 ORDER BY full_name");
$doctors  = $conn->query("SELECT d.id, d.name, dep.name department FROM doctors d LEFT JOIN departments dep ON dep.id=d.department_id WHERE d.is_active=1 ORDER BY d.name");

// Open appointments for linking
$open_appts = $conn->query("
    SELECT a.id, a.appointment_date, a.token_no, p.full_name patient
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    WHERE a.status IN ('Waiting','In Consultation')
    ORDER BY a.appointment_date DESC, a.token_no
");

// Records query
if ($filter_patient) {
    $stmt2 = $conn->prepare("
        SELECT mr.*, p.full_name patient, p.patient_code,
               d.name doctor, dep.name department
        FROM medical_records mr
        JOIN patients p ON p.id = mr.patient_id
        JOIN doctors  d ON d.id = mr.doctor_id
        LEFT JOIN departments dep ON dep.id = d.department_id
        WHERE mr.patient_id = ?
        ORDER BY mr.visit_date DESC
    ");
    $stmt2->bind_param("i", $filter_patient);
    $stmt2->execute();
    $rows = $stmt2->get_result();
} else {
    $rows = $conn->query("
        SELECT mr.*, p.full_name patient, p.patient_code,
               d.name doctor, dep.name department
        FROM medical_records mr
        JOIN patients p ON p.id = mr.patient_id
        JOIN doctors  d ON d.id = mr.doctor_id
        LEFT JOIN departments dep ON dep.id = d.department_id
        ORDER BY mr.visit_date DESC
        LIMIT 50
    ");
}
?>

<div class="page-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="page-title">Medical Records</h1>
    <p class="text-muted mb-0">Patient consultation records and prescriptions</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#recForm">
    📝 Add Record
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Add Record Form -->
<div class="collapse mb-4" id="recForm">
<div class="card p-4">
  <h5 class="mb-3">New Medical Record</h5>
  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Patient *</label>
      <select class="form-select" name="patient_id" required>
        <option value="">— Select Patient —</option>
        <?php while ($p = $patients->fetch_assoc()): ?>
          <option value="<?= $p['id'] ?>" <?= $filter_patient == $p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_code'] ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Doctor *</label>
      <select class="form-select" name="doctor_id" required>
        <option value="">— Select Doctor —</option>
        <?php while ($d = $doctors->fetch_assoc()): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> — <?= htmlspecialchars($d['department']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Visit Date *</label>
      <input type="date" class="form-control" name="visit_date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">Link to Appointment (optional)</label>
      <select class="form-select" name="appointment_id">
        <option value="">— None —</option>
        <?php while ($oa = $open_appts->fetch_assoc()): ?>
          <option value="<?= $oa['id'] ?>"><?= htmlspecialchars($oa['patient']) ?> — Token #<?= $oa['token_no'] ?> (<?= $oa['appointment_date'] ?>)</option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">ICD-10 Code</label>
      <input class="form-control" name="icd_code" placeholder="e.g. J06.9">
    </div>
    <div class="col-md-4">
      <label class="form-label">Follow-up Date</label>
      <input type="date" class="form-control" name="follow_up_date">
    </div>
    <div class="col-md-6">
      <label class="form-label">Chief Complaint</label>
      <input class="form-control" name="chief_complaint" placeholder="e.g. Fever for 3 days">
    </div>
    <div class="col-md-6">
      <label class="form-label">Diagnosis</label>
      <input class="form-control" name="diagnosis" placeholder="e.g. Viral pharyngitis">
    </div>
    <div class="col-12">
      <label class="form-label">Prescription</label>
      <textarea class="form-control" name="prescription" rows="2" placeholder="e.g. Tab Paracetamol 500mg TID × 5 days"></textarea>
    </div>
    <div class="col-12">
      <label class="form-label">Doctor Notes</label>
      <textarea class="form-control" name="notes" rows="2" placeholder="Additional clinical notes…"></textarea>
    </div>
    <div class="col-12">
      <button class="btn btn-primary" name="save">Save Record</button>
      <button type="button" class="btn btn-outline-secondary ms-2" data-bs-toggle="collapse" data-bs-target="#recForm">Cancel</button>
    </div>
  </form>
</div>
</div>

<!-- Records Table -->
<div class="card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Medical Records <?= $filter_patient ? '— Filtered by Patient' : '(Recent 50)' ?></h5>
    <?php if ($filter_patient): ?>
      <a href="records.php" class="btn btn-sm btn-outline-secondary">Show All</a>
    <?php endif; ?>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>Date</th><th>Patient</th><th>Doctor</th><th>Complaint</th><th>Diagnosis</th><th>ICD</th><th>Follow-up</th><th>Prescription</th></tr>
      </thead>
      <tbody>
      <?php if ($rows->num_rows === 0): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>
      <?php endif; ?>
      <?php while ($r = $rows->fetch_assoc()): ?>
        <tr>
          <td><?= date('d M Y', strtotime($r['visit_date'])) ?></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($r['patient']) ?></div>
            <small class="text-muted"><?= $r['patient_code'] ?></small>
          </td>
          <td>
            <div><?= htmlspecialchars($r['doctor']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($r['department']) ?></small>
          </td>
          <td><?= $r['chief_complaint'] ? htmlspecialchars($r['chief_complaint']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $r['diagnosis'] ? htmlspecialchars($r['diagnosis']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $r['icd_code'] ? '<code>'.$r['icd_code'].'</code>' : '—' ?></td>
          <td><?= $r['follow_up_date'] ? '<span class="text-warning fw-semibold">'.date('d M Y', strtotime($r['follow_up_date'])).'</span>' : '—' ?></td>
          <td>
            <?php if ($r['prescription']): ?>
              <span data-bs-toggle="tooltip" title="<?= htmlspecialchars($r['prescription']) ?>">
                <span class="text-truncate d-inline-block" style="max-width:150px"><?= htmlspecialchars($r['prescription']) ?></span>
              </span>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Enable tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

<?php require 'partials/footer.php'; ?>