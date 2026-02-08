<?php
/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user = $_SESSION['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nexus-IaaS - Enterprise Cloud Infrastructure Management">
    <meta name="author" content="Krzysztof Siek">
    <title><?= $pageTitle ?? 'Dashboard' ?> - Nexus-IaaS</title>
    
    <!-- Preconnect to external resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Font Awesome 6 (Free) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" crossorigin="anonymous"></script>
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.4/dist/sweetalert2.all.min.js"></script>
    
    <!-- Custom Styles -->
    <link rel="stylesheet" href="/assets/css/custom.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='0.9em' font-size='90'>☁️</text></svg>">
</head>
<body>
    <!-- Glassmorphic Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <i class="fas fa-cloud-bolt text-primary"></i>
                <h4 class="logo-text">Nexus-IaaS</h4>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-details">
                    <span class="user-email"><?= htmlspecialchars($user['email'] ?? 'User') ?></span>
                    <?php if (isset($user['is_admin']) && $user['is_admin']): ?>
                        <span class="badge badge-admin">
                            <i class="fas fa-shield-halved"></i> Admin
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
            <a href="/instances.php" class="nav-item <?= ($currentPage ?? '') === 'instances' ? 'active' : '' ?>">
                <i class="fas fa-server"></i>
                <span>Instances</span>
            </a>
            <a href="/billing.php" class="nav-item <?= ($currentPage ?? '') === 'billing' ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i>
                <span>Billing</span>
            </a>
            <a href="/settings.php" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-gear"></i>
                <span>Settings</span>
            </a>
            
            <?php if (isset($user['is_admin']) && $user['is_admin']): ?>
                <div class="nav-divider"></div>
                <a href="/admin.php" class="nav-item <?= ($currentPage ?? '') === 'admin' ? 'active' : '' ?>">
                    <i class="fas fa-shield-halved"></i>
                    <span>Admin Panel</span>
                </a>
            <?php endif; ?>
            
            <div class="nav-divider"></div>
            <a href="/logout.php" class="nav-item">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="balance-card">
                <i class="fas fa-wallet"></i>
                <div>
                    <small>Balance</small>
                    <strong>$<?= number_format($user['balance'] ?? 0, 2) ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="main-content" id="mainContent">
