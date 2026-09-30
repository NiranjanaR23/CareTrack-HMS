<?php
require 'config/db.php';
require 'partials/header.php';

// ── Stats ──────────────────────────────────────────────────────────────────
$total_patients  = $conn->query("SELECT COUNT(*) c FROM patients WHERE is_active=1")->fetch_assoc()['c'];
$total_appts     = $conn->query("SELECT COUNT(*) c FROM appointments")->fetch_assoc()['c'];
$completed       = $conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Completed'")->fetch_assoc()['c'];
$revenue         = $conn->query("SELECT COALESCE(SUM(paid_amount),0) c FROM bills WHERE status='Paid'")->fetch_assoc()['c'];
$pending_rev     = $conn->query("SELECT COALESCE(SUM(balance),0) c FROM bills WHERE status IN ('Pending','Partial')")->fetch_assoc()['c'];
$total_records   = $conn->query("SELECT COUNT(*) c FROM medical_records")->fetch_assoc()['c'];
$admitted        = $conn->query("SELECT COUNT(*) c FROM admissions WHERE status='Admitted'")->fetch_assoc()['c'];

// ── By Department ──────────────────────────────────────────────────────────
$dept = $conn->query("
    SELECT dep.name department, COUNT(a.id) total
    FROM departments dep
    LEFT JOIN doctors d   ON d.department_id = dep.id
    LEFT JOIN appointments a ON a.doctor_id = d.id
    GROUP BY dep.id, dep.name
    ORDER BY total DESC
");

// ── Monthly revenue (last 6 months) ────────────────────────────────────────
$monthly = $conn->query("
    SELECT DATE_FORMAT(payment_date,'%b %Y') mon,
           DATE_FORMAT(payment_date,'%Y-%m') sort_key,
           SUM(amount) total
    FROM payments
    WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(payment_date,'%Y-%m')
    ORDER BY sort_key ASC
");

// ── Top doctors by appointments ─────────────────────────────────────────────
$top_doctors = $conn->query("
    SELECT d.name, dep.name department, COUNT(a.id) total
    FROM doctors d
    LEFT JOIN departments dep ON dep.id = d.department_id
    LEFT JOIN appointments a  ON a.doctor_id = d.id
    GROUP BY d.id
    ORDER BY total DESC
    LIMIT 5
");

// ── Blood group distribution ────────────────────────────────────────────────
$blood = $conn->query("SELECT blood_group, COUNT(*) cnt FROM patients GROUP BY blood_group ORDER BY cnt DESC");

// ── Low stock medicines ─────────────────────────────────────────────────────
$low_stock = $conn->query("SELECT * FROM v_low_stock");

$months = $month_rev = [];
while ($mr = $monthly->fetch_assoc()) {
    $months[]    = $mr['mon'];
    $month_rev[] = round($mr['total'], 2);
}
?>

<div class="page-header mb-4">
  <h1 class="page-title">Reports &amp; Analytics</h1>
  <p class="text-muted mb-0">Hospital performance overview</p>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Patients</div>
      <div class="stat"><?= $total_patients ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Appointments</div>
      <div class="stat"><?= $total_appts ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Completed</div>
      <div class="stat text-success"><?= $completed ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Revenue</div>
      <div class="stat text-success" style="font-size:1rem">₹<?= number_format($revenue, 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Pending</div>
      <div class="stat text-warning" style="font-size:1rem">₹<?= number_format($pending_rev, 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="card p-3 text-center">
      <div class="text-muted small">Admitted</div>
      <div class="stat text-danger"><?= $admitted ?></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Revenue Chart -->
  <div class="col-md-7">
    <div class="card p-4 h-100">
      <h5 class="mb-3">📈 Monthly Revenue (Last 6 Months)</h5>
      <?php if (empty($months)): ?>
        <div class="text-muted text-center py-4">No payment data yet.</div>
      <?php else: ?>
      <canvas id="revenueChart" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Department Table -->
  <div class="col-md-5">
    <div class="card p-4 h-100">
      <h5 class="mb-3">🏥 Appointments by Department</h5>
      <table class="table table-sm align-middle">
        <thead class="table-light"><tr><th>Department</th><th>Appointments</th><th>%</th></tr></thead>
        <tbody>
        <?php
        $dept_rows = $dept->fetch_all(MYSQLI_ASSOC);
        $dept_total = array_sum(array_column($dept_rows, 'total')) ?: 1;
        foreach ($dept_rows as $dr): ?>
          <tr>
            <td><?= htmlspecialchars($dr['department']) ?></td>
            <td><?= $dr['total'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?= round($dr['total']/$dept_total*100) ?>%
                <div class="progress flex-grow-1" style="height:6px">
                  <div class="progress-bar" style="width:<?= round($dr['total']/$dept_total*100) ?>%"></div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Top Doctors -->
  <div class="col-md-6">
    <div class="card p-4">
      <h5 class="mb-3">👨‍⚕️ Top Doctors by Appointments</h5>
      <table class="table table-sm">
        <thead class="table-light"><tr><th>Doctor</th><th>Department</th><th>Total</th></tr></thead>
        <tbody>
        <?php while ($td = $top_doctors->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($td['name']) ?></td>
            <td><small class="text-muted"><?= htmlspecialchars($td['department']) ?></small></td>
            <td><span class="badge bg-primary"><?= $td['total'] ?></span></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Blood Group Distribution -->
  <div class="col-md-6">
    <div class="card p-4">
      <h5 class="mb-3">🩸 Patient Blood Group Distribution</h5>
      <?php
      $blood_data = $blood->fetch_all(MYSQLI_ASSOC);
      $blood_total = array_sum(array_column($blood_data, 'cnt')) ?: 1;
      $bg_colors = ['A+'=>'#ef4444','A-'=>'#f97316','B+'=>'#f59e0b','B-'=>'#eab308','AB+'=>'#22c55e','AB-'=>'#14b8a6','O+'=>'#3b82f6','O-'=>'#8b5cf6','Unknown'=>'#6b7280'];
      ?>
      <div class="row">
        <?php foreach ($blood_data as $bd): ?>
        <div class="col-6 mb-2">
          <div class="d-flex align-items-center gap-2">
            <div style="width:12px;height:12px;border-radius:50%;background:<?= $bg_colors[$bd['blood_group']] ?? '#6b7280' ?>"></div>
            <span class="fw-semibold"><?= $bd['blood_group'] ?></span>
            <span class="text-muted ms-auto"><?= $bd['cnt'] ?> (<?= round($bd['cnt']/$blood_total*100) ?>%)</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Low Stock Warning -->
<?php if ($low_stock->num_rows > 0): ?>
<div class="card p-4 border-danger">
  <h5 class="mb-3 text-danger">⚠️ Low Stock Medicines</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead class="table-light"><tr><th>Medicine</th><th>Generic</th><th>Stock</th><th>Reorder Level</th><th>Expiry</th></tr></thead>
      <tbody>
      <?php while ($ls = $low_stock->fetch_assoc()): ?>
        <tr class="<?= $ls['stock_qty'] == 0 ? 'table-danger' : 'table-warning' ?>">
          <td><?= htmlspecialchars($ls['name']) ?></td>
          <td><?= htmlspecialchars($ls['generic_name']) ?></td>
          <td><strong><?= $ls['stock_qty'] ?></strong></td>
          <td><?= $ls['reorder_level'] ?></td>
          <td><?= $ls['expiry_date'] ? date('d M Y', strtotime($ls['expiry_date'])) : '—' ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($months)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($months) ?>,
    datasets: [{
      label: 'Revenue (₹)',
      data: <?= json_encode($month_rev) ?>,
      backgroundColor: 'rgba(59,130,246,0.7)',
      borderRadius: 6,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>
<?php endif; ?>

<?php require 'partials/footer.php'; ?>