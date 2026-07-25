<?php require_once 'session.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/default-avatar.png">
    <title>The Professional Barbershop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-expand top-navbar">
    <div class="container-fluid">
        <button class="btn btn-outline-light me-2 d-lg-none" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand" href="#">
            <img src="../assets/images/logo.png" alt="Logo" height="40" class="d-inline-block align-text-top me-2">
            <span class="text-white fw-bold">The Professional 🟢  Barbershop</span>
        </a>
        <div class="ms-auto d-flex align-items-center">
            <!-- Notifications -->
<div class="dropdown d-inline-block me-2">
    <button class="btn btn-outline-light btn-sm position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifCount" style="display:none;">0</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" aria-labelledby="notifDropdown" id="notifList" style="max-height:250px; overflow-y:auto;">
        <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
    </ul>
</div>

<!-- User info -->
<span class="text-white me-3 d-none d-md-inline"><i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">Logout</span></a>
        </div>
    </div>
</nav>
<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>