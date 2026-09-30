<?php
require 'config/db.php';
require 'partials/header.php';

$id = (int)($_GET['id'] ?? 0);
$r = $conn->query("SELECT * FROM patients WHERE id=$id")->fetch_assoc();

if (!$r) {
    echo '<div class="alert alert-danger">Patient not found.</div>';
    require 'partials/footer.php';
    exit;
}

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'];
$qr_url = "$protocol://$host/hospital_management_system/qr_patient.php?token=" . $r['qr_token'];
$qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($qr_url);
?>

<div class="row justify-content-center py-4">
  <div class="col-md-6 col-lg-5">
    <div class="card p-4 text-center shadow-sm">
      <div class="mb-3">
        <span class="badge bg-primary text-white px-3 py-2 fs-6"><?= htmlspecialchars($r['patient_code']) ?></span>
      </div>
      <h3 class="fw-bold mb-1"><?= htmlspecialchars($r['full_name']) ?></h3>
      <p class="text-muted mb-3">
        <?= htmlspecialchars($r['gender']) ?> &bull; 
        Blood Group: <strong><?= htmlspecialchars($r['blood_group']) ?></strong> &bull; 
        Phone: <?= htmlspecialchars($r['phone']) ?>
      </p>

      <div class="p-3 bg-light rounded d-inline-block mx-auto mb-3">
        <img class="qrbox img-fluid" src="<?= $qr_api_url ?>" alt="Patient QR Code" style="width: 220px; height: 220px;">
      </div>

      <p class="small text-muted mb-4">
        Scanning this unique QR code grants emergency response staff and authorized medical officers direct access to medical history, allergies, and emergency contact details.
      </p>

      <div class="d-flex gap-2 justify-content-center">
        <a href="patients.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to Patients</a>
        <a href="<?= $qr_url ?>" target="_blank" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i> Test Public View</a>
        <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer me-1"></i> Print Card</button>
      </div>
    </div>
  </div>
</div>

<?php require 'partials/footer.php'; ?>