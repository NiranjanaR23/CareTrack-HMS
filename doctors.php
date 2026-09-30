<?php
require 'config/db.php';
require 'partials/header.php';

$msg = $err = '';

// ── ADD / EDIT DOCTOR ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_doctor'])) {
    $doctor_id      = (int)($_POST['doctor_id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $department_id  = (int)($_POST['department_id'] ?? 0);
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification  = trim($_POST['qualification'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $available_days = implode(', ', $_POST['available_days'] ?? ['Mon-Fri']);
    $consult_fee    = (float)($_POST['consult_fee'] ?? 0);

    if (empty($name)) {
        $err = "Doctor name is required.";
    } else {
        if ($doctor_id > 0) {
            $stmt = $conn->prepare("UPDATE doctors SET name=?, department_id=?, specialization=?, qualification=?, phone=?, email=?, available_days=?, consult_fee=? WHERE id=?");
            $stmt->bind_param("sisssssdi", $name, $department_id, $specialization, $qualification, $phone, $email, $available_days, $consult_fee, $doctor_id);
            if ($stmt->execute()) $msg = "Doctor details updated successfully.";
            else $err = "Failed to update doctor: " . $conn->error;
        } else {
            $stmt = $conn->prepare("INSERT INTO doctors (name, department_id, specialization, qualification, phone, email, available_days, consult_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssssd", $name, $department_id, $specialization, $qualification, $phone, $email, $available_days, $consult_fee);
            if ($stmt->execute()) $msg = "Doctor added successfully.";
            else $err = "Failed to add doctor: " . $conn->error;
        }
    }
}

// ── ADD DEPARTMENT ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_department'])) {
    $dept_name = trim($_POST['dept_name'] ?? '');
    $dept_desc = trim($_POST['dept_desc'] ?? '');

    if (empty($dept_name)) {
        $err = "Department name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description=VALUES(description)");
        $stmt->bind_param("ss", $dept_name, $dept_desc);
        if ($stmt->execute()) $msg = "Department saved successfully.";
        else $err = "Failed to save department: " . $conn->error;
    }
}

// ── TOGGLE STATUS ──────────────────────────────────────────────────────────
if (isset($_GET['toggle_id'])) {
    $tid = (int)$_GET['toggle_id'];
    $conn->query("UPDATE doctors SET is_active = NOT is_active WHERE id = $tid");
    header("Location: doctors.php");
    exit;
}

// ── FETCH DATA ─────────────────────────────────────────────────────────────
$search_q = trim($_GET['q'] ?? '');
$dept_filter = (int)($_GET['dept'] ?? 0);

$where = ["1=1"];
$params = [];
$types = "";

if ($search_q !== '') {
    $where[] = "(d.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?)";
    $st = "%$search_q%";
    $params = array_merge($params, [$st, $st, $st]);
    $types .= "sss";
}
if ($dept_filter > 0) {
    $where[] = "d.department_id = ?";
    $params[] = $dept_filter;
    $types .= "i";
}

$where_sql = implode(" AND ", $where);
$sql = "
    SELECT d.*, dep.name AS department_name,
           (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = d.id AND a.appointment_date = CURDATE()) AS today_appts,
           (SELECT COUNT(*) FROM medical_records mr WHERE mr.doctor_id = d.id) AS total_consultations
    FROM doctors d
    LEFT JOIN departments dep ON dep.id = d.department_id
    WHERE $where_sql
    ORDER BY d.is_active DESC, d.name ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $doctors = $stmt->get_result();
} else {
    $doctors = $conn->query($sql);
}

$departments = $conn->query("SELECT dep.*, COUNT(d.id) AS doctor_count FROM departments dep LEFT JOIN doctors d ON d.department_id = dep.id GROUP BY dep.id ORDER BY dep.name ASC");
$all_depts = $conn->query("SELECT * FROM departments ORDER BY name ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 fw-bold mb-1">👨‍⚕️ Doctors & Departments</h1>
    <p class="text-muted mb-0">Manage hospital medical staff, specialties, schedules, and departments</p>
  </div>
  <div>
    <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#deptModal">
      <i class="bi bi-building"></i> + Add Department
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#doctorModal" onclick="clearDoctorForm()">
      <i class="bi bi-person-plus"></i> + Add Doctor
    </button>
  </div>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Filters -->
<div class="card p-3 mb-4">
  <form method="GET" class="row g-3">
    <div class="col-md-5">
      <div class="input-group">
        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search doctor by name, specialty, degree..." value="<?= htmlspecialchars($search_q) ?>">
      </div>
    </div>
    <div class="col-md-4">
      <select name="dept" class="form-select" onchange="this.form.submit()">
        <option value="0">All Departments</option>
        <?php while ($dp = $all_depts->fetch_assoc()): ?>
          <option value="<?= $dp['id'] ?>" <?= $dept_filter == $dp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($dp['name']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary w-100">Filter</button>
      <?php if ($search_q || $dept_filter): ?>
        <a href="doctors.php" class="btn btn-outline-secondary">Reset</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Doctors Grid -->
<div class="row g-4 mb-5">
  <?php if ($doctors->num_rows === 0): ?>
    <div class="col-12 text-center py-5 text-muted card">
      <i class="bi bi-person-badge fs-1 mb-2"></i>
      <h5>No doctors found</h5>
      <p>Try clearing filters or add a new doctor.</p>
    </div>
  <?php else: ?>
    <?php while ($doc = $doctors->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0 position-relative <?= $doc['is_active'] ? '' : 'bg-light opacity-75' ?>">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold me-3" style="width: 48px; height: 48px; font-size: 1.2rem;">
                  <?= strtoupper(substr($doc['name'], 0, 1)) ?>
                </div>
                <div>
                  <h5 class="card-title mb-0 fw-bold"><?= htmlspecialchars($doc['name']) ?></h5>
                  <span class="badge bg-info text-dark"><?= htmlspecialchars($doc['specialization'] ?: 'General Practice') ?></span>
                </div>
              </div>
              <span class="badge <?= $doc['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $doc['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </div>

            <div class="small text-muted mb-3">
              <div class="mb-1"><i class="bi bi-building me-2"></i><strong>Dept:</strong> <?= htmlspecialchars($doc['department_name'] ?: 'Unassigned') ?></div>
              <div class="mb-1"><i class="bi bi-award me-2"></i><strong>Degree:</strong> <?= htmlspecialchars($doc['qualification'] ?: 'N/A') ?></div>
              <div class="mb-1"><i class="bi bi-telephone me-2"></i><strong>Phone:</strong> <?= htmlspecialchars($doc['phone'] ?: 'N/A') ?></div>
              <div class="mb-1"><i class="bi bi-envelope me-2"></i><strong>Email:</strong> <?= htmlspecialchars($doc['email'] ?: 'N/A') ?></div>
              <div class="mb-1"><i class="bi bi-calendar-week me-2"></i><strong>Available:</strong> <?= htmlspecialchars($doc['available_days'] ?: 'Mon-Fri') ?></div>
            </div>

            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-3">
              <div>
                <small class="text-muted d-block">Consult Fee</small>
                <strong class="text-primary fs-6">₹<?= number_format($doc['consult_fee'], 2) ?></strong>
              </div>
              <div class="text-end">
                <small class="text-muted d-block">Today's Queue</small>
                <span class="badge bg-warning text-dark fs-6"><?= $doc['today_appts'] ?> Patients</span>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick='editDoctor(<?= json_encode($doc) ?>)'>
                <i class="bi bi-pencil me-1"></i> Edit
              </button>
              <a href="appointments.php?doctor_id=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-success flex-grow-1">
                <i class="bi bi-calendar-event me-1"></i> Appts
              </a>
              <a href="doctors.php?toggle_id=<?= $doc['id'] ?>" class="btn btn-sm <?= $doc['is_active'] ? 'btn-outline-danger' : 'btn-outline-secondary' ?>" title="Toggle status">
                <i class="bi bi-power"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php endif; ?>
</div>

<!-- Department Overview -->
<h4 class="h5 fw-bold mb-3"><i class="bi bi-diagram-3 text-primary"></i> Department Breakdown</h4>
<div class="row g-3 mb-4">
  <?php 
  $departments->data_seek(0);
  while ($dp = $departments->fetch_assoc()): 
  ?>
    <div class="col-md-4 col-lg-3">
      <div class="card p-3 h-100 border-start border-4 border-primary">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="fw-bold mb-1"><?= htmlspecialchars($dp['name']) ?></h6>
            <small class="text-muted"><?= htmlspecialchars($dp['description'] ?: 'Medical Department') ?></small>
          </div>
          <span class="badge bg-primary rounded-pill"><?= $dp['doctor_count'] ?> Doctors</span>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
</div>

<!-- Modal: Doctor Add/Edit -->
<div class="modal fade" id="doctorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_doctor" value="1">
        <input type="hidden" name="doctor_id" id="doc_id" value="0">
        
        <div class="modal-header">
          <h5 class="modal-title" id="doctorModalTitle">Add New Doctor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Doctor Full Name *</label>
            <input type="text" name="name" id="doc_name" class="form-control" required placeholder="Dr. John Smith">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Department</label>
            <select name="department_id" id="doc_dept" class="form-select">
              <option value="0">Select Department</option>
              <?php 
              $all_depts->data_seek(0);
              while ($dp = $all_depts->fetch_assoc()): 
              ?>
                <option value="<?= $dp['id'] ?>"><?= htmlspecialchars($dp['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          
          <div class="col-md-6">
            <label class="form-label fw-semibold">Specialization</label>
            <input type="text" name="specialization" id="doc_spec" class="form-control" placeholder="Cardiologist, Neurologist, etc.">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Qualification / Degree</label>
            <input type="text" name="qualification" id="doc_qual" class="form-control" placeholder="MBBS, MD, FRCS">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Phone Number</label>
            <input type="text" name="phone" id="doc_phone" class="form-control" placeholder="+91 9876543210">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email Address</label>
            <input type="email" name="email" id="doc_email" class="form-control" placeholder="doctor@hospital.com">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Consultation Fee (₹)</label>
            <input type="number" step="0.01" name="consult_fee" id="doc_fee" class="form-control" value="500.00">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Available Days</label>
            <div class="d-flex flex-wrap gap-2 pt-1">
              <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                <div class="form-check form-check-inline">
                  <input class="form-check-input doc-day" type="checkbox" name="available_days[]" value="<?= $day ?>" id="day_<?= $day ?>" checked>
                  <label class="form-check-label small" for="day_<?= $day ?>"><?= $day ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Doctor</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Department Add -->
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action_department" value="1">
        <div class="modal-header">
          <h5 class="modal-title">Add / Edit Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body g-3">
          <div class="mb-3">
            <label class="form-label fw-semibold">Department Name *</label>
            <input type="text" name="dept_name" class="form-control" required placeholder="Cardiology, Pediatrics, etc.">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="dept_desc" class="form-control" rows="3" placeholder="Brief description of department services..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Save Department</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function clearDoctorForm() {
  document.getElementById('doc_id').value = '0';
  document.getElementById('doc_name').value = '';
  document.getElementById('doc_dept').value = '0';
  document.getElementById('doc_spec').value = '';
  document.getElementById('doc_qual').value = '';
  document.getElementById('doc_phone').value = '';
  document.getElementById('doc_email').value = '';
  document.getElementById('doc_fee').value = '500.00';
  document.getElementById('doctorModalTitle').innerText = 'Add New Doctor';
}

function editDoctor(doc) {
  document.getElementById('doc_id').value = doc.id;
  document.getElementById('doc_name').value = doc.name;
  document.getElementById('doc_dept').value = doc.department_id || '0';
  document.getElementById('doc_spec').value = doc.specialization || '';
  document.getElementById('doc_qual').value = doc.qualification || '';
  document.getElementById('doc_phone').value = doc.phone || '';
  document.getElementById('doc_email').value = doc.email || '';
  document.getElementById('doc_fee').value = doc.consult_fee || '0.00';
  document.getElementById('doctorModalTitle').innerText = 'Edit Doctor Details';
  
  var days = (doc.available_days || '').split(', ');
  document.querySelectorAll('.doc-day').forEach(cb => {
    cb.checked = days.includes(cb.value);
  });
  
  var modal = new bootstrap.Modal(document.getElementById('doctorModal'));
  modal.show();
}
</script>

<?php require 'partials/footer.php'; ?>
