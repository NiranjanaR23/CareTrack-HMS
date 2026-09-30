<?php
require 'config/db.php';
$token = $_GET['token'] ?? '';

$stmt = $conn->prepare("SELECT * FROM patients WHERE qr_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();

if (!$p) {
    http_response_code(404);
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Invalid or Expired QR Token</h2><p>Please scan a valid patient QR code.</p></div>");
}

$pid = (int)$p['id'];
$records = $conn->query("
    SELECT mr.*, d.name AS doctor_name, d.specialization 
    FROM medical_records mr 
    JOIN doctors d ON d.id = mr.doctor_id 
    WHERE mr.patient_id = $pid 
    ORDER BY mr.visit_date DESC
");

$vitals = $conn->query("SELECT * FROM vital_signs WHERE patient_id = $pid ORDER BY recorded_at DESC LIMIT 5");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Patient Medical Profile — <?= htmlspecialchars($p['full_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { background-color: #f4f6f9; font-family: system-ui, sans-serif; }
  .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
</style>
</head>
<body class="py-4">
<div class="container" style="max-width: 800px;">
  <!-- Header Card -->
  <div class="card p-4 mb-4 bg-primary text-white">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <span class="badge bg-light text-primary mb-2"><?= htmlspecialchars($p['patient_code']) ?></span>
        <h2 class="mb-1 fw-bold"><?= htmlspecialchars($p['full_name']) ?></h2>
        <p class="mb-0 text-white-50">
          <?= htmlspecialchars($p['gender']) ?> &bull; 
          DOB: <?= $p['dob'] ? date('d M Y', strtotime($p['dob'])) : 'N/A' ?> &bull; 
          Blood Group: <strong><?= htmlspecialchars($p['blood_group']) ?></strong>
        </p>
      </div>
      <i class="bi bi-qr-code-scan fs-1"></i>
    </div>
  </div>

  <!-- Medical Alerts & Contacts -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card p-3 h-100 border-start border-4 border-warning">
        <h6 class="fw-bold text-warning mb-2"><i class="bi bi-exclamation-triangle"></i> Allergies & Conditions</h6>
        <div class="small">
          <div><strong>Allergies:</strong> <?= htmlspecialchars($p['allergies'] ?: 'None reported') ?></div>
          <div><strong>Chronic Conditions:</strong> <?= htmlspecialchars($p['chronic_conditions'] ?: 'None reported') ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 h-100 border-start border-4 border-info">
        <h6 class="fw-bold text-info mb-2"><i class="bi bi-telephone"></i> Emergency Contact</h6>
        <div class="small">
          <div><strong>Name:</strong> <?= htmlspecialchars($p['emergency_contact_name'] ?: 'N/A') ?></div>
          <div><strong>Phone:</strong> <?= htmlspecialchars($p['emergency_contact_phone'] ?: 'N/A') ?></div>
          <div><strong>Insurance:</strong> <?= htmlspecialchars($p['insurance_provider'] ?: 'Self-Pay') ?> <?= $p['insurance_policy_no'] ? '('.$p['insurance_policy_no'].')' : '' ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Medical Records History -->
  <div class="card p-4">
    <h4 class="fw-bold mb-3"><i class="bi bi-journal-medical text-primary"></i> Medical Consultations History</h4>
    <?php if ($records->num_rows === 0): ?>
      <p class="text-muted text-center py-3">No medical consultation records found.</p>
    <?php else: ?>
      <?php while ($r = $records->fetch_assoc()): ?>
        <div class="border rounded p-3 mb-3 bg-light">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 fw-bold text-primary"><?= date('d M Y', strtotime($r['visit_date'])) ?> &bull; Dr. <?= htmlspecialchars($r['doctor_name']) ?></h6>
            <small class="badge bg-secondary"><?= htmlspecialchars($r['specialization']) ?></small>
          </div>
          <?php if ($r['chief_complaint']): ?>
            <div class="small mb-1"><strong>Chief Complaint:</strong> <?= htmlspecialchars($r['chief_complaint']) ?></div>
          <?php endif; ?>
          <div class="small mb-1"><strong>Diagnosis:</strong> <?= htmlspecialchars($r['diagnosis']) ?> <?= $r['icd_code'] ? '('.htmlspecialchars($r['icd_code']).')' : '' ?></div>
          <?php if ($r['prescription']): ?>
            <div class="small bg-white p-2 rounded border mt-2">
              <strong>Prescription / Dosage:</strong><br>
              <?= nl2br(htmlspecialchars($r['prescription'])) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>

  <div class="text-center mt-4 text-muted small">
    CareTrack Hospital Management System &bull; Authorized QR Verification System
  </div>
</div>
</body>
</html>