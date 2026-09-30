<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── BOOK appointment ─────────────────────────────────────────────────────────
if (isset($_POST['book'])) {
    $date     = $_POST['appointment_date'];
    $doctor   = (int)$_POST['doctor_id'];
    $patient  = (int)$_POST['patient_id'];
    $time     = $_POST['appointment_time'];
    $reason   = trim($_POST['reason'] ?? '');
    $priority = $_POST['priority'] ?? 'Normal';

    // Auto token number
    $q = $conn->prepare("SELECT COALESCE(MAX(token_no),0)+1 n FROM appointments WHERE appointment_date=? AND doctor_id=?");
    $q->bind_param("si", $date, $doctor);
    $q->execute();
    $token_no = $q->get_result()->fetch_assoc()['n'];

    $stmt = $conn->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, token_no, reason, priority, booked_by)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $uid = $_SESSION['user']['id'];
    $stmt->bind_param("iissisis", $patient, $doctor, $date, $time, $token_no, $reason, $priority, $uid);
    if ($stmt->execute()) {
        $msg = "Appointment booked! Token <strong>#$token_no</strong>";
    } else {
        $err = $conn->error;
    }
}

// ── UPDATE status ─────────────────────────────────────────────────────────────
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $status = $_GET['status'];
    $allowed = ['Waiting','In Consultation','Completed','Cancelled','No Show'];
    if (in_array($status, $allowed)) {
        $s = $conn->prepare("UPDATE appointments SET status=? WHERE id=?");
        $s->bind_param("si", $status, $id);
        $s->execute();
    }
    header("Location: appointments.php");
    exit;
}

// ── Data ──────────────────────────────────────────────────────────────────────
$filter_date = $_GET['date'] ?? date('Y-m-d');
$patients = $conn->query("SELECT id, full_name, patient_code FROM patients WHERE is_active=1 ORDER BY full_name");
$doctors  = $conn->query("SELECT d.id, d.name, dep.name department FROM doctors d LEFT JOIN departments dep ON dep.id=d.department_id WHERE d.is_active=1 ORDER BY d.name");

$appts = $conn->prepare("
    SELECT a.*, p.full_name patient, p.patient_code,
           d.name doctor, dep.name department, d.consult_fee
    FROM appointments a
    JOIN patients p     ON p.id  = a.patient_id
    JOIN doctors  d     ON d.id  = a.doctor_id
    LEFT JOIN departments dep ON dep.id = d.department_id
    WHERE a.appointment_date = ?
    ORDER BY a.token_no
");
$appts->bind_param("s", $filter_date);
$appts->execute();
$apps = $appts->get_result();
?>

<div class="page-header mb-4 d-flex justify-content-between align-items-center">
  <div>
    <h1 class="page-title">Appointments &amp; Tokens</h1>
    <p class="text-muted mb-0">Book appointments and manage patient queues</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#bookForm">
    📅 Book Appointment
  </button>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Booking Form -->
<div class="collapse mb-4" id="bookForm">
<div class="card p-4">
  <h5 class="mb-3">New Appointment</h5>
  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Patient *</label>
      <select class="form-select" name="patient_id" required>
        <option value="">— Select Patient —</option>
        <?php while ($p = $patients->fetch_assoc()): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?> (<?= $p['patient_code'] ?>)</option>
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
    <div class="col-md-2">
      <label class="form-label">Date *</label>
      <input type="date" class="form-control" name="appointment_date" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-md-2">
      <label class="form-label">Time *</label>
      <input type="time" class="form-control" name="appointment_time" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Reason / Chief Complaint</label>
      <input class="form-control" name="reason" placeholder="e.g. Fever, Follow-up, Chest pain">
    </div>
    <div class="col-md-3">
      <label class="form-label">Priority</label>
      <select class="form-select" name="priority">
        <option>Normal</option><option>Urgent</option><option>Emergency</option>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button name="book" class="btn btn-primary w-100">Book &amp; Generate Token</button>
    </div>
  </form>
</div>
</div>

<!-- Queue Table -->
<div class="card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">📋 Appointment Queue</h5>
    <form class="d-flex gap-2 align-items-center" method="get">
      <label class="fw-semibold mb-0">Date:</label>
      <input type="date" class="form-control form-control-sm" name="date" value="<?= $filter_date ?>" onchange="this.form.submit()">
    </form>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Token</th><th>Patient</th><th>Doctor / Dept</th><th>Time</th>
          <th>Reason</th><th>Priority</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($apps->num_rows === 0): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No appointments for this date.</td></tr>
      <?php endif; ?>
      <?php while ($a = $apps->fetch_assoc()): ?>
        <tr>
          <td><span class="badge bg-primary fs-6">#<?= $a['token_no'] ?></span></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($a['patient']) ?></div>
            <small class="text-muted"><?= $a['patient_code'] ?></small>
          </td>
          <td>
            <div><?= htmlspecialchars($a['doctor']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($a['department']) ?></small>
          </td>
          <td><?= substr($a['appointment_time'],0,5) ?></td>
          <td><?= $a['reason'] ? htmlspecialchars($a['reason']) : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php $pc = ['Normal'=>'secondary','Urgent'=>'warning','Emergency'=>'danger']; ?>
            <span class="badge bg-<?= $pc[$a['priority']] ?? 'secondary' ?>"><?= $a['priority'] ?></span>
          </td>
          <td>
            <?php $sc = ['Waiting'=>'warning','In Consultation'=>'info','Completed'=>'success','Cancelled'=>'danger','No Show'=>'secondary']; ?>
            <span class="badge bg-<?= $sc[$a['status']] ?? 'secondary' ?>"><?= $a['status'] ?></span>
          </td>
          <td class="d-flex gap-1 flex-wrap">
            <?php if ($a['status'] === 'Waiting'): ?>
              <a class="btn btn-sm btn-outline-info" href="?id=<?= $a['id'] ?>&status=In+Consultation&date=<?= $filter_date ?>">Call</a>
            <?php endif; ?>
            <?php if (in_array($a['status'], ['Waiting','In Consultation'])): ?>
              <a class="btn btn-sm btn-outline-success" href="?id=<?= $a['id'] ?>&status=Completed&date=<?= $filter_date ?>">Done</a>
              <a class="btn btn-sm btn-outline-danger"  href="?id=<?= $a['id'] ?>&status=Cancelled&date=<?= $filter_date ?>">Cancel</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'partials/footer.php'; ?>