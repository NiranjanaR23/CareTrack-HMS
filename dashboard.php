<?php
require 'config/db.php';
require 'partials/header.php';

// ── Stats ──────────────────────────────────────────────────────────────────
$total_patients   = $conn->query("SELECT COUNT(*) c FROM patients WHERE is_active=1")->fetch_assoc()['c'];
$today_appts      = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURDATE()")->fetch_assoc()['c'];
$waiting          = $conn->query("SELECT COUNT(*) c FROM appointments WHERE appointment_date=CURDATE() AND status='Waiting'")->fetch_assoc()['c'];
$total_records    = $conn->query("SELECT COUNT(*) c FROM medical_records")->fetch_assoc()['c'];
$revenue_paid     = $conn->query("SELECT COALESCE(SUM(paid_amount),0) c FROM bills WHERE status='Paid'")->fetch_assoc()['c'];
$pending_bills    = $conn->query("SELECT COUNT(*) c FROM bills WHERE status IN ('Pending','Partial')")->fetch_assoc()['c'];
$admitted         = $conn->query("SELECT COUNT(*) c FROM admissions WHERE status='Admitted'")->fetch_assoc()['c'];
$low_stock        = $conn->query("SELECT COUNT(*) c FROM v_low_stock")->fetch_assoc()['c'];

// ── Today's appointment queue ───────────────────────────────────────────────
$queue = $conn->query("
    SELECT a.token_no, a.appointment_time, a.status, a.priority,
           p.full_name patient, d.name doctor, dep.name department
    FROM appointments a
    JOIN patients p    ON p.id  = a.patient_id
    JOIN doctors  d    ON d.id  = a.doctor_id
    LEFT JOIN departments dep ON dep.id = d.department_id
    WHERE a.appointment_date = CURDATE()
    ORDER BY a.token_no ASC
    LIMIT 10
");

// ── Recent patients ─────────────────────────────────────────────────────────
$recent_patients = $conn->query("SELECT patient_code, full_name, phone, blood_group, created_at FROM patients ORDER BY created_at DESC LIMIT 5");
?>

<div class="page-header mb-4">
  <h1 class="page-title">Dashboard</h1>
  <p class="text-muted">Welcome back, <strong><?= htmlspecialchars($_SESSION['user']['full_name']) ?></strong> — <?= date('l, d M Y') ?></p>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card card p-3 h-100">
      <div class="stat-icon text-primary">👥</div>
      <div class="stat-value"><?= $total_patients ?></div>
      <div class="stat-label">Total Patients</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card p-3 h-100">
      <div class="stat-icon text-warning">📅</div>
      <div class="stat-value"><?= $today_appts ?></div>
      <div class="stat-label">Today's Appointments</div>
      <small class="text-muted"><?= $waiting ?> waiting</small>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card p-3 h-100">
      <div class="stat-icon text-success">₹</div>
      <div class="stat-value">₹<?= number_format($revenue_paid, 0) ?></div>
      <div class="stat-label">Revenue Collected</div>
      <small class="text-warning"><?= $pending_bills ?> pending bills</small>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card card p-3 h-100">
      <div class="stat-icon text-danger">🛏</div>
      <div class="stat-value"><?= $admitted ?></div>
      <div class="stat-label">Admitted Patients</div>
      <?php if ($low_stock > 0): ?>
      <small class="text-danger">⚠ <?= $low_stock ?> medicines low stock</small>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Today's Queue -->
  <div class="col-md-7">
    <div class="card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">📋 Today's Appointment Queue</h5>
        <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <?php if ($queue->num_rows === 0): ?>
        <div class="text-muted text-center py-4">No appointments today.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Patient</th><th>Doctor</th><th>Time</th><th>Status</th></tr>
          </thead>
          <tbody>
          <?php while ($row = $queue->fetch_assoc()): ?>
            <tr>
              <td><span class="badge bg-primary">#<?= $row['token_no'] ?></span></td>
              <td><?= htmlspecialchars($row['patient']) ?></td>
              <td>
                <?= htmlspecialchars($row['doctor']) ?>
                <div class="small text-muted"><?= htmlspecialchars($row['department']) ?></div>
              </td>
              <td><?= substr($row['appointment_time'], 0, 5) ?></td>
              <td>
                <?php
                  $sc = ['Waiting'=>'warning','In Consultation'=>'info','Completed'=>'success','Cancelled'=>'danger','No Show'=>'secondary'];
                  $s  = $row['status'];
                ?>
                <span class="badge bg-<?= $sc[$s] ?? 'secondary' ?>"><?= $s ?></span>
                <?php if ($row['priority'] !== 'Normal'): ?>
                  <span class="badge bg-danger ms-1"><?= $row['priority'] ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Patients & Quick Links -->
  <div class="col-md-5">
    <div class="card p-4 mb-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">🆕 Recent Patients</h5>
        <a href="patients.php" class="btn btn-sm btn-outline-primary">All Patients</a>
      </div>
      <?php while ($rp = $recent_patients->fetch_assoc()): ?>
      <div class="d-flex align-items-center py-2 border-bottom">
        <div class="avatar-circle me-3"><?= strtoupper($rp['full_name'][0]) ?></div>
        <div class="flex-grow-1">
          <div class="fw-semibold"><?= htmlspecialchars($rp['full_name']) ?></div>
          <div class="small text-muted"><?= $rp['patient_code'] ?> &bull; <?= $rp['blood_group'] ?></div>
        </div>
        <small class="text-muted"><?= date('d M', strtotime($rp['created_at'])) ?></small>
      </div>
      <?php endwhile; ?>
    </div>

    <div class="card p-4">
      <h5 class="mb-3">⚡ Quick Actions</h5>
      <div class="d-grid gap-2">
        <a href="patients.php" class="btn btn-outline-primary btn-sm">➕ Register Patient</a>
        <a href="appointments.php" class="btn btn-outline-success btn-sm">📅 Book Appointment</a>
        <a href="billing.php" class="btn btn-outline-warning btn-sm">💳 Create Bill</a>
        <a href="records.php" class="btn btn-outline-info btn-sm">📝 Add Medical Record</a>
      </div>
    </div>
  </div>
</div>

<?php require 'partials/footer.php'; ?>