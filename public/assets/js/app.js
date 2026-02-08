/*
 * Nexus-IaaS - Frontend Application
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 * 
 * Main JavaScript for interactivity, AJAX operations, and UI management
 */

/* ==================== GLOBAL STATE ==================== */
const NexusApp = {
    pollInterval: null,
    charts: {},
    
    /* ==================== INITIALIZATION ==================== */
    init() {
        console.log('🚀 Nexus-IaaS Application Initialized');
        this.setupEventListeners();
        this.initializeCharts();
        
        // Start polling for status updates if on dashboard/instances page
        if (document.querySelector('[data-poll-instances]')) {
            this.startPolling();
        }
    },
    
    setupEventListeners() {
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                document.getElementById('sidebar').classList.toggle('active');
            });
        }
    },
    
    /* ==================== TOAST NOTIFICATIONS ==================== */
    toast(message, type = 'info', duration = 4000) {
        const toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) return;
        
        const icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        };
        
        const colors = {
            success: 'var(--success)',
            error: 'var(--danger)',
            warning: 'var(--warning)',
            info: 'var(--info)'
        };
        
        const toastId = `toast-${Date.now()}`;
        const toastHTML = `
            <div class="toast align-items-center border-0" role="alert" id="${toastId}" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-3">
                        <i class="fas ${icons[type]} fa-lg" style="color: ${colors[type]}"></i>
                        <span>${message}</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { delay: duration });
        toast.show();
        
        // Remove from DOM after hidden
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    },
    
    /* ==================== SWEETALERT2 CONFIRMATIONS ==================== */
    async confirmAction(title, text, confirmButtonText = 'Confirm', icon = 'warning') {
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            background: 'var(--bg-secondary)',
            color: 'var(--text-primary)',
            confirmButtonColor: 'var(--primary)',
            cancelButtonColor: 'var(--bg-tertiary)'
        });
        
        return result.isConfirmed;
    },
    
    /* ==================== VM ACTIONS ==================== */
    async performVmAction(action, vmid, instanceId) {
        const actionConfig = {
            start: {
                title: 'Start Virtual Machine?',
                text: 'This will power on the VM.',
                confirmText: 'Start VM',
                icon: 'info',
                loadingText: 'Starting VM...',
                successText: 'VM started successfully!'
            },
            stop: {
                title: 'Stop Virtual Machine?',
                text: 'This will gracefully shutdown the VM.',
                confirmText: 'Stop VM',
                icon: 'warning',
                loadingText: 'Stopping VM...',
                successText: 'VM stopped successfully!'
            },
            kill: {
                title: 'Force Kill Virtual Machine?',
                text: 'This will forcefully terminate the VM. This may cause data loss!',
                confirmText: 'Force Kill',
                icon: 'error',
                loadingText: 'Killing VM...',
                successText: 'VM terminated successfully!'
            },
            reboot: {
                title: 'Reboot Virtual Machine?',
                text: 'This will restart the VM.',
                confirmText: 'Reboot VM',
                icon: 'warning',
                loadingText: 'Rebooting VM...',
                successText: 'VM rebooted successfully!'
            },
            delete: {
                title: 'Delete Virtual Machine?',
                text: 'This action cannot be undone! All data will be lost.',
                confirmText: 'Delete VM',
                icon: 'error',
                loadingText: 'Deleting VM...',
                successText: 'VM deleted successfully!'
            }
        };
        
        const config = actionConfig[action];
        if (!config) {
            this.toast('Unknown action', 'error');
            return;
        }
        
        // Confirmation dialog
        const confirmed = await this.confirmAction(
            config.title,
            config.text,
            config.confirmText,
            config.icon
        );
        
        if (!confirmed) return;
        
        // Show loading
        Swal.fire({
            title: config.loadingText,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    instance_id: instanceId,
                    vmid: vmid
                })
            });
            
            const result = await response.json();
            
            Swal.close();
            
            if (result.success) {
                this.toast(config.successText, 'success');
                
                // Refresh the instances list
                if (typeof this.refreshInstances === 'function') {
                    setTimeout(() => this.refreshInstances(), 1500);
                }
                
                // If delete, redirect after delay
                if (action === 'delete') {
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            } else {
                this.toast(result.message || 'Action failed', 'error');
            }
        } catch (error) {
            Swal.close();
            console.error('VM Action Error:', error);
            this.toast('An error occurred. Please try again.', 'error');
        }
    },
    
    /* ==================== CONSOLE ACCESS ==================== */
    async openConsole(vmid, instanceId) {
        // Show loading
        Swal.fire({
            title: 'Opening Console...',
            text: 'Generating VNC connection...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'console',
                    instance_id: instanceId,
                    vmid: vmid
                })
            });
            
            const result = await response.json();
            
            Swal.close();
            
            if (result.success && result.console_url) {
                // Open console in modal with iframe
                this.showConsoleModal(result.console_url, vmid);
            } else {
                this.toast(result.message || 'Failed to open console', 'error');
            }
        } catch (error) {
            Swal.close();
            console.error('Console Error:', error);
            this.toast('Failed to open console. Please try again.', 'error');
        }
    },
    
    showConsoleModal(consoleUrl, vmid) {
        const modalHTML = `
            <div class="modal fade" id="consoleModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
                    <div class="modal-content">
                        <div class="modal-header glass-panel">
                            <h5 class="modal-title">
                                <i class="fas fa-terminal me-2"></i>
                                VM Console - VMID ${vmid}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0" style="background: #000;">
                            <iframe 
                                src="${consoleUrl}" 
                                style="width: 100%; height: 600px; border: none;"
                                title="VM Console"
                            ></iframe>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('consoleModal');
        if (existingModal) existingModal.remove();
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('consoleModal'));
        modal.show();
        
        // Remove from DOM when hidden
        document.getElementById('consoleModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    },
    
    /* ==================== SNAPSHOTS ==================== */
    async showSnapshots(vmid, instanceId) {
        // Show loading
        Swal.fire({
            title: 'Loading Snapshots...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'snapshot_list',
                    instance_id: instanceId,
                    vmid: vmid
                })
            });
            
            const result = await response.json();
            
            Swal.close();
            
            if (result.success) {
                this.showSnapshotModal(vmid, instanceId, result.snapshots || []);
            } else {
                this.toast(result.message || 'Failed to load snapshots', 'error');
            }
        } catch (error) {
            Swal.close();
            console.error('Snapshot Error:', error);
            this.toast('Failed to load snapshots. Please try again.', 'error');
        }
    },
    
    showSnapshotModal(vmid, instanceId, snapshots) {
        const snapshotRows = snapshots.length > 0 
            ? snapshots.map(snap => `
                <tr>
                    <td>${snap.name}</td>
                    <td>${snap.description || 'N/A'}</td>
                    <td>${snap.snaptime ? new Date(snap.snaptime * 1000).toLocaleString() : 'N/A'}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="3" class="text-center text-muted">No snapshots found</td></tr>';
        
        const modalHTML = `
            <div class="modal fade" id="snapshotModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header glass-panel">
                            <h5 class="modal-title">
                                <i class="fas fa-camera me-2"></i>
                                Snapshots - VMID ${vmid}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <button class="btn btn-primary btn-sm" onclick="NexusApp.createSnapshot(${vmid}, ${instanceId})">
                                    <i class="fas fa-plus"></i> Create New Snapshot
                                </button>
                            </div>
                            
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${snapshotRows}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('snapshotModal');
        if (existingModal) existingModal.remove();
        
        // Add new modal to body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('snapshotModal'));
        modal.show();
        
        // Remove from DOM when hidden
        document.getElementById('snapshotModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    },
    
    async createSnapshot(vmid, instanceId) {
        const { value: snapshotName } = await Swal.fire({
            title: 'Create Snapshot',
            input: 'text',
            inputLabel: 'Snapshot Name',
            inputPlaceholder: 'e.g., backup-2026-02-08',
            showCancelButton: true,
            confirmButtonText: 'Create',
            background: 'var(--bg-secondary)',
            color: 'var(--text-primary)',
            inputValidator: (value) => {
                if (!value) {
                    return 'You need to provide a snapshot name!';
                }
            }
        });
        
        if (!snapshotName) return;
        
        // Show loading
        Swal.fire({
            title: 'Creating Snapshot...',
            text: 'This may take a few moments...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            const response = await fetch('/api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'snapshot_create',
                    instance_id: instanceId,
                    vmid: vmid,
                    snapshot_name: snapshotName
                })
            });
            
            const result = await response.json();
            
            Swal.close();
            
            if (result.success) {
                this.toast('Snapshot created successfully!', 'success');
                
                // Refresh snapshots list
                setTimeout(() => {
                    this.showSnapshots(vmid, instanceId);
                }, 1000);
            } else {
                this.toast(result.message || 'Failed to create snapshot', 'error');
            }
        } catch (error) {
            Swal.close();
            console.error('Create Snapshot Error:', error);
            this.toast('Failed to create snapshot. Please try again.', 'error');
        }
    },
    
    /* ==================== POLLING (Auto-refresh status) ==================== */
    startPolling() {
        // Poll every 10 seconds
        this.pollInterval = setInterval(() => {
            this.refreshInstances();
        }, 10000);
        
        console.log('✅ Status polling started (10s interval)');
    },
    
    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
            console.log('⏸ Status polling stopped');
        }
    },
    
    async refreshInstances() {
        const tableBody = document.querySelector('[data-instances-table]');
        if (!tableBody) return;
        
        try {
            const response = await fetch('/api.php?action=list_instances');
            const result = await response.json();
            
            if (result.success && result.instances) {
                result.instances.forEach(instance => {
                    const row = document.querySelector(`tr[data-instance-id="${instance.id}"]`);
                    if (row) {
                        // Update status badge
                        const statusCell = row.querySelector('.instance-status');
                        if (statusCell) {
                            statusCell.innerHTML = this.getStatusBadge(instance.status);
                        }
                        
                        // Update any other dynamic fields as needed
                    }
                });
            }
        } catch (error) {
            console.error('Refresh Error:', error);
        }
    },
    
    getStatusBadge(status) {
        const badges = {
            running: '<span class="badge badge-running"><span class="pulse-dot"></span> Running</span>',
            stopped: '<span class="badge badge-stopped"><i class="fas fa-stop-circle"></i> Stopped</span>',
            pending: '<span class="badge badge-pending"><i class="fas fa-clock"></i> Pending</span>',
            error: '<span class="badge badge-error"><i class="fas fa-exclamation-triangle"></i> Error</span>'
        };
        
        return badges[status] || badges.pending;
    },
    
    /* ==================== CHARTS (Resource Usage) ==================== */
    initializeCharts() {
        // Initialize CPU/RAM usage chart if container exists
        const chartCanvas = document.getElementById('resourceChart');
        if (chartCanvas) {
            this.createResourceChart(chartCanvas);
        }
    },
    
    createResourceChart(canvas) {
        // Mock data for demonstration (replace with real data from API)
        const labels = ['6h ago', '5h ago', '4h ago', '3h ago', '2h ago', '1h ago', 'Now'];
        const cpuData = [45, 52, 38, 61, 55, 48, 42];
        const ramData = [62, 65, 58, 70, 68, 72, 69];
        
        this.charts.resource = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'CPU Usage (%)',
                    data: cpuData,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'RAM Usage (%)',
                    data: ramData,
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: 'rgb(148, 163, 184)',
                            font: {
                                family: "'Inter', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgb(30, 41, 59)',
                        titleColor: 'rgb(241, 245, 249)',
                        bodyColor: 'rgb(148, 163, 184)',
                        borderColor: 'rgb(51, 65, 85)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(51, 65, 85, 0.5)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgb(148, 163, 184)',
                            font: {
                                family: "'Inter', sans-serif"
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(51, 65, 85, 0.5)',
                            drawBorder: false
                        },
                        ticks: {
                            color: 'rgb(148, 163, 184)',
                            font: {
                                family: "'Inter', sans-serif"
                            },
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
};

/* ==================== AUTO-INITIALIZE ON PAGE LOAD ==================== */
document.addEventListener('DOMContentLoaded', () => {
    NexusApp.init();
});

/* ==================== CLEANUP ON PAGE UNLOAD ==================== */
window.addEventListener('beforeunload', () => {
    NexusApp.stopPolling();
});
