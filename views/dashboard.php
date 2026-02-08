<?php
/*
 * Nexus-IaaS Dashboard
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Instance.php';
require_once __DIR__ . '/../src/Billing.php';

use NexusIaaS\Core\Auth;
use NexusIaaS\Core\Instance;
use NexusIaaS\Core\Billing;

// Authenticate user
$user = Auth::check();

// Get dashboard data
$userId = (int)$user['id'];
$instances = Instance::getUserInstances($userId);
$billingSummary = Billing::getUserSummary($userId);
$balance = $user['balance'] ?? 0;

// Calculate statistics
$stats = [
    'total' => count($instances),
    'running' => count(array_filter($instances, fn($i) => $i['status'] === 'running')),
    'stopped' => count(array_filter($instances, fn($i) => $i['status'] === 'stopped')),
    'pending' => count(array_filter($instances, fn($i) => $i['status'] === 'pending'))
];

// Page metadata
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Include header
include __DIR__ . '/partials/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <div class="breadcrumb">
            <i class="fas fa-home"></i> Home / Dashboard
        </div>
    </div>
    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createVmModal">
        <i class="fas fa-plus-circle"></i> Create VM
    </button>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-server"></i>
            </div>
            <div class="stat-label">Total Instances</div>
            <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon success">
                <i class="fas fa-circle-play"></i>
            </div>
            <div class="stat-label">Running</div>
            <div class="stat-value"><?= $stats['running'] ?></div>
        </div>
                    </div>

    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-circle-pause"></i>
            </div>
            <div class="stat-label">Stopped</div>
            <div class="stat-value"><?= $stats['stopped'] ?></div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-label">Balance</div>
            <div class="stat-value">$<?= number_format($balance, 2) ?></div>
        </div>
    </div>
</div>

<!-- Instances Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-server me-2"></i>
            Your Instances
        </h5>
    </div>
    <div class="table-container">
        <table data-poll-instances data-instances-table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>VMID</th>
                    <th>IP Address</th>
                    <th>OS</th>
                    <th>Resources</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instances)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tertiary);">
                            <i class="fas fa-inbox fa-3x mb-3" style="display: block; opacity: 0.3;"></i>
                            <strong>No instances yet</strong><br>
                            <small>Click "Create VM" to deploy your first virtual machine</small>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($instances as $instance): ?>
                        <tr data-instance-id="<?= $instance['id'] ?>">
                            <td>
                                <strong><?= htmlspecialchars($instance['name']) ?></strong>
                            </td>
                            <td>
                                <span class="text-mono">#<?= $instance['vmid'] ?? 'N/A' ?></span>
                            </td>
                            <td>
                                <code class="text-mono"><?= $instance['ip_address'] ?? 'Pending' ?></code>
                            </td>
                            <td>
                                <i class="fab fa-<?= getOsIcon($instance['os_template'] ?? 'linux') ?> me-1"></i>
                                <?= htmlspecialchars($instance['os_template'] ?? 'Unknown') ?>
                            </td>
                            <td>
                                <small style="color: var(--text-secondary);">
                                    <i class="fas fa-microchip"></i> <?= $instance['vcpu'] ?>vCPU 
                                    <i class="fas fa-memory ms-2"></i> <?= $instance['ram'] ?>MB 
                                    <i class="fas fa-hard-drive ms-2"></i> <?= $instance['disk'] ?>GB
                                </small>
                            </td>
                            <td class="instance-status">
                                <?php
                                $status = $instance['status'];
                                $statusBadges = [
                                    'running' => '<span class="badge badge-running"><span class="pulse-dot"></span> Running</span>',
                                    'stopped' => '<span class="badge badge-stopped"><i class="fas fa-stop-circle"></i> Stopped</span>',
                                    'pending' => '<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>',
                                    'error' => '<span class="badge badge-error"><i class="fas fa-exclamation-triangle"></i> Error</span>'
                                ];
                                echo $statusBadges[$status] ?? $statusBadges['pending'];
                                ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($status === 'running'): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="NexusApp.openConsole(<?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                    <i class="fas fa-terminal"></i> Console
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="NexusApp.performVmAction('stop', <?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                    <i class="fas fa-stop"></i> Stop (Graceful)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="NexusApp.performVmAction('kill', <?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                    <i class="fas fa-skull"></i> Force Kill
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="NexusApp.performVmAction('reboot', <?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                    <i class="fas fa-rotate-right"></i> Reboot
                                                </a>
                                            </li>
                                        <?php elseif ($status === 'stopped'): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="NexusApp.performVmAction('start', <?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                    <i class="fas fa-play"></i> Start
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="NexusApp.showSnapshots(<?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                <i class="fas fa-camera"></i> Snapshots
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="NexusApp.performVmAction('delete', <?= $instance['vmid'] ?>, <?= $instance['id'] ?>); return false;">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Helper function for OS icons
function getOsIcon($osTemplate) {
    if (stripos($osTemplate, 'ubuntu') !== false) return 'ubuntu';
    if (stripos($osTemplate, 'debian') !== false) return 'debian';
    if (stripos($osTemplate, 'centos') !== false) return 'centos';
    if (stripos($osTemplate, 'fedora') !== false) return 'fedora';
    if (stripos($osTemplate, 'windows') !== false) return 'windows';
    return 'linux';
}
?>

<!-- Resource Usage Chart (Optional Enhancement) -->
<div class="card mt-4 glass-panel">
    <div class="card-header">
        <h5 class="card-title">
            <i class="fas fa-chart-line me-2"></i>
            Resource Usage Overview
        </h5>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="resourceChart"></canvas>
        </div>
        <p class="text-center mt-2 mb-0" style="font-size: 0.8rem; color: var(--text-tertiary);">
            <i class="fas fa-info-circle"></i> Real-time monitoring coming soon
        </p>
    </div>
</div>

<!-- Create VM Modal -->
<div class="modal fade" id="createVmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header glass-panel">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>
                    Create New Virtual Machine
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createVmForm" method="POST" action="/api.php">
                    <input type="hidden" name="action" value="create_instance">
                    <input type="hidden" name="csrf_token" value="<?= Auth::getCsrfToken() ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tag me-1"></i> Instance Name
                        </label>
                        <input type="text" class="form-control" name="name" placeholder="my-awesome-server" required>
                        <small class="text-muted">Choose a unique, memorable name for your instance</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-compact-disc me-1"></i> Operating System
                        </label>
                        <select class="form-select" name="os_template" required>
                            <option value="">-- Select OS --</option>
                            <option value="ubuntu-22.04">Ubuntu 22.04 LTS (Jammy)</option>
                            <option value="ubuntu-20.04">Ubuntu 20.04 LTS (Focal)</option>
                            <option value="debian-12">Debian 12 (Bookworm)</option>
                            <option value="debian-11">Debian 11 (Bullseye)</option>
                            <option value="centos-9">CentOS Stream 9</option>
                            <option value="fedora-39">Fedora 39</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fas fa-microchip me-1"></i> CPU Cores
                            </label>
                            <select class="form-select" name="vcpu" required>
                                <option value="1">1 vCPU</option>
                                <option value="2" selected>2 vCPU</option>
                                <option value="4">4 vCPU</option>
                                <option value="8">8 vCPU</option>
                                <option value="16">16 vCPU</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fas fa-memory me-1"></i> RAM
                            </label>
                            <select class="form-select" name="ram" required>
                                <option value="1024">1 GB</option>
                                <option value="2048" selected>2 GB</option>
                                <option value="4096">4 GB</option>
                                <option value="8192">8 GB</option>
                                <option value="16384">16 GB</option>
                                <option value="32768">32 GB</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                <i class="fas fa-hard-drive me-1"></i> Disk Storage
                            </label>
                            <select class="form-select" name="disk" required>
                                <option value="20">20 GB</option>
                                <option value="40" selected>40 GB</option>
                                <option value="80">80 GB</option>
                                <option value="160">160 GB</option>
                                <option value="320">320 GB</option>
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex align-items-center gap-3" style="background: rgba(6, 182, 212, 0.1); border: 1px solid rgba(6, 182, 212, 0.3); color: var(--info);">
                        <i class="fas fa-info-circle fa-2x"></i>
                        <div>
                            <strong>Billing Information</strong><br>
                            <small>$10.00 will be deducted from your balance for VM creation. Additional hourly rates apply based on resource usage.</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" form="createVmForm" class="btn btn-primary">
                    <i class="fas fa-rocket"></i> Create Instance
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include __DIR__ . '/partials/footer.php';
?>
