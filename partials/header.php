<?php require_once __DIR__ . '/../config/auth.php'; require_login(); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CareTrack HMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary px-3 shadow-sm sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold fs-4 d-flex align-items-center" href="dashboard.php">
      <i class="bi bi-hospital me-2"></i> CareTrack HMS
    </a>
    <div class="d-flex align-items-center text-white">
      <div class="me-3 d-none d-sm-block text-end">
        <div class="fw-semibold small"><?= htmlspecialchars($_SESSION['user']['full_name']) ?></div>
        <small class="badge bg-light text-primary"><?= htmlspecialchars(strtoupper($_SESSION['user']['role'])) ?></small>
      </div>
      <a href="logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
<div class="row">
<aside class="col-md-3 col-lg-2 sidebar p-3 border-end">
  <div class="text-uppercase text-muted small fw-bold px-2 mb-2">Main Navigation</div>
  <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-speedometer2 me-2"></i> Dashboard
  </a>
  <a href="patients.php" class="<?= basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : '' ?>">
    <i class="bi bi-people me-2"></i> Patients
  </a>
  <a href="doctors.php" class="<?= basename($_SERVER['PHP_SELF']) == 'doctors.php' ? 'active' : '' ?>">
    <i class="bi bi-person-badge me-2"></i> Doctors & Depts
  </a>
  <a href="appointments.php" class="<?= basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : '' ?>">
    <i class="bi bi-calendar-event me-2"></i> Appointments
  </a>
  <a href="records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'records.php' ? 'active' : '' ?>">
    <i class="bi bi-file-earmark-medical me-2"></i> Medical Records
  </a>

  <div class="text-uppercase text-muted small fw-bold px-2 mt-4 mb-2">Hospital Services</div>
  <a href="pharmacy.php" class="<?= basename($_SERVER['PHP_SELF']) == 'pharmacy.php' ? 'active' : '' ?>">
    <i class="bi bi-capsule me-2"></i> Pharmacy Inventory
  </a>
  <a href="admissions.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admissions.php' ? 'active' : '' ?>">
    <i class="bi bi-door-open me-2"></i> Admissions & Wards
  </a>
  <a href="billing.php" class="<?= basename($_SERVER['PHP_SELF']) == 'billing.php' ? 'active' : '' ?>">
    <i class="bi bi-receipt me-2"></i> Billing & Payments
  </a>

  <div class="text-uppercase text-muted small fw-bold px-2 mt-4 mb-2">Analytics</div>
  <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
    <i class="bi bi-bar-chart-line me-2"></i> Reports & KPI
  </a>
</aside>
<main class="col-md-9 col-lg-10 p-4">
