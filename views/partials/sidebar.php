<div class="col-md-2 sidebar p-3">
    <div class="mb-4">
        <h4 class="text-primary fw-bold">Nexus-IaaS</h4>
        <p class="small text-muted mb-0"><?= htmlspecialchars($user['email']) ?></p>
        <?php if (isset($user['is_admin']) && $user['is_admin']): ?>
            <span class="badge bg-danger">Admin</span>
        <?php endif; ?>
    </div>

    <nav class="nav flex-column">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>" href="/dashboard.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'instances.php' ? 'active' : '' ?>" href="/instances.php">
            <i class="bi bi-hdd-rack"></i> Instances
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'billing.php' ? 'active' : '' ?>" href="/billing.php">
            <i class="bi bi-credit-card"></i> Billing
        </a>
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" href="/settings.php">
            <i class="bi bi-gear"></i> Settings
        </a>
        
        <?php if (isset($user['is_admin']) && $user['is_admin']): ?>
            <hr class="text-muted">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin.php' ? 'active' : '' ?>" href="/admin.php">
                <i class="bi bi-shield-lock"></i> Admin Panel
            </a>
        <?php endif; ?>
        
        <hr class="text-muted">
        <a class="nav-link" href="/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </nav>
</div>
