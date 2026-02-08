<?php
/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

declare(strict_types=1);

namespace NexusIaaS\Core;

use NexusIaaS\Config\Database;

/**
 * Instance (VM) Management
 * 
 * Handles virtual machine lifecycle operations through the task queue.
 */
class Instance
{
    /**
     * Create a new VM instance (queues the task)
     * 
     * @param int $userId Owner user ID
     * @param array $config VM configuration
     * @return array ['success' => bool, 'message' => string, 'instance_id' => int|null, 'task_id' => int|null]
     */
    public static function create(int $userId, array $config): array
    {
        // Validate required fields
        $required = ['name', 'vcpu', 'ram', 'disk', 'os_template'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}", 'instance_id' => null, 'task_id' => null];
            }
        }

        // Check user balance
        $vmCreationCost = (float)Database::getConfig('VM_CREATION_COST', 10.00);
        $user = Database::queryOne("SELECT balance FROM users WHERE id = ?", [$userId]);
        
        if (!$user || $user['balance'] < $vmCreationCost) {
            return ['success' => false, 'message' => 'Insufficient balance', 'instance_id' => null, 'task_id' => null];
        }

        // Allocate IP address
        $ip = self::allocateIp($userId);
        if (!$ip) {
            return ['success' => false, 'message' => 'No available IP addresses', 'instance_id' => null, 'task_id' => null];
        }

        try {
            Database::beginTransaction();

            // Deduct balance
            Billing::deductBalance($userId, $vmCreationCost, "VM creation: {$config['name']}");

            // Get next available VMID
            $vmid = self::getNextVmid();

            // Insert instance record
            Database::execute(
                "INSERT INTO instances (user_id, name, vmid, ip_address, vcpu, ram, disk, os_template, status, proxmox_node, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
                [
                    $userId,
                    $config['name'],
                    $vmid,
                    $ip['ip_address'],
                    $config['vcpu'],
                    $config['ram'],
                    $config['disk'],
                    $config['os_template'],
                    $config['node'] ?? Database::getConfig('PROXMOX_NODE', 'pve')
                ]
            );

            $instanceId = (int)Database::lastInsertId();

            // Queue creation task
            $taskPayload = [
                'user_id' => $userId,
                'instance_id' => $instanceId,
                'vmid' => $vmid,
                'name' => $config['name'],
                'vcpu' => $config['vcpu'],
                'ram' => $config['ram'],
                'disk' => $config['disk'],
                'os_template' => $config['os_template'],
                'ip_address' => $ip['ip_address'],
                'gateway' => $ip['gateway'],
                'node' => $config['node'] ?? Database::getConfig('PROXMOX_NODE', 'pve')
            ];

            $taskId = Queue::push('create', $taskPayload);

            Database::commit();

            AuditLog::log($userId, 'instance_create_queued', 'instance', $instanceId, $config);

            return [
                'success' => true,
                'message' => 'VM creation queued successfully',
                'instance_id' => $instanceId,
                'task_id' => $taskId
            ];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Instance creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to queue VM creation', 'instance_id' => null, 'task_id' => null];
        }
    }

    /**
     * Start a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'task_id' => int|null]
     */
    public static function start(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'task_id' => null];
        }

        if ($instance['status'] === 'running') {
            return ['success' => false, 'message' => 'Instance is already running', 'task_id' => null];
        }

        // Update status
        Database::execute(
            "UPDATE instances SET status = 'pending', updated_at = NOW() WHERE id = ?",
            [$instanceId]
        );

        // Queue start task
        $taskId = Queue::push('start', [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'vmid' => $instance['vmid'],
            'node' => $instance['proxmox_node']
        ]);

        AuditLog::log($userId, 'instance_start_queued', 'instance', $instanceId);

        return ['success' => true, 'message' => 'VM start queued', 'task_id' => $taskId];
    }

    /**
     * Stop a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'task_id' => int|null]
     */
    public static function stop(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'task_id' => null];
        }

        if ($instance['status'] === 'stopped') {
            return ['success' => false, 'message' => 'Instance is already stopped', 'task_id' => null];
        }

        // Update status
        Database::execute(
            "UPDATE instances SET status = 'pending', updated_at = NOW() WHERE id = ?",
            [$instanceId]
        );

        // Queue stop task
        $taskId = Queue::push('stop', [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'vmid' => $instance['vmid'],
            'node' => $instance['proxmox_node']
        ]);

        AuditLog::log($userId, 'instance_stop_queued', 'instance', $instanceId);

        return ['success' => true, 'message' => 'VM stop queued', 'task_id' => $taskId];
    }

    /**
     * Delete a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'task_id' => int|null]
     */
    public static function delete(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'task_id' => null];
        }

        // Update status
        Database::execute(
            "UPDATE instances SET status = 'pending', updated_at = NOW() WHERE id = ?",
            [$instanceId]
        );

        // Queue delete task
        $taskId = Queue::push('delete', [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'vmid' => $instance['vmid'],
            'node' => $instance['proxmox_node'],
            'ip_address' => $instance['ip_address']
        ]);

        AuditLog::log($userId, 'instance_delete_queued', 'instance', $instanceId);

        return ['success' => true, 'message' => 'VM deletion queued', 'task_id' => $taskId];
    }

    /**
     * Get instance details
     * 
     * @param int $instanceId Instance ID
     * @param int|null $userId User ID (for ownership verification, null to skip check)
     * @return array|null Instance data
     */
    public static function get(int $instanceId, ?int $userId = null): ?array
    {
        $sql = "SELECT * FROM instances WHERE id = ?";
        $params = [$instanceId];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $instance = Database::queryOne($sql, $params);
        return $instance ?: null;
    }

    /**
     * Get all instances for a user
     * 
     * @param int $userId User ID
     * @return array List of instances
     */
    public static function getUserInstances(int $userId): array
    {
        return Database::query(
            "SELECT * FROM instances WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
    }

    /**
     * Get all instances (admin only)
     * 
     * @return array List of all instances
     */
    public static function getAllInstances(): array
    {
        return Database::query(
            "SELECT i.*, u.email as user_email 
             FROM instances i 
             LEFT JOIN users u ON i.user_id = u.id 
             ORDER BY i.created_at DESC"
        );
    }

    /**
     * Update instance status (called by worker)
     * 
     * @param int $instanceId Instance ID
     * @param string $status New status
     * @return bool
     */
    public static function updateStatus(int $instanceId, string $status): bool
    {
        $affected = Database::execute(
            "UPDATE instances SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $instanceId]
        );

        return $affected > 0;
    }

    /**
     * Allocate an IP address from the pool
     * 
     * @param int $userId User ID
     * @return array|null IP data or null if no IPs available
     */
    private static function allocateIp(int $userId): ?array
    {
        Database::beginTransaction();

        try {
            $ip = Database::queryOne(
                "SELECT * FROM ip_pool WHERE is_allocated = 0 LIMIT 1 FOR UPDATE"
            );

            if (!$ip) {
                Database::rollback();
                return null;
            }

            Database::execute(
                "UPDATE ip_pool SET is_allocated = 1, allocated_to = ? WHERE id = ?",
                [$userId, $ip['id']]
            );

            Database::commit();
            return $ip;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("IP allocation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Release an IP address back to the pool
     * 
     * @param string $ipAddress IP address to release
     * @return bool
     */
    public static function releaseIp(string $ipAddress): bool
    {
        $affected = Database::execute(
            "UPDATE ip_pool SET is_allocated = 0, allocated_to = NULL WHERE ip_address = ?",
            [$ipAddress]
        );

        return $affected > 0;
    }

    /**
     * Get next available VMID
     * 
     * @return int
     */
    private static function getNextVmid(): int
    {
        $result = Database::queryOne(
            "SELECT MAX(vmid) as max_vmid FROM instances WHERE vmid IS NOT NULL"
        );

        $maxVmid = (int)($result['max_vmid'] ?? 100);
        return $maxVmid + 1;
    }

    /**
     * Get instance statistics for a user
     * 
     * @param int $userId User ID
     * @return array Statistics
     */
    public static function getUserStats(int $userId): array
    {
        $stats = Database::queryOne(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                SUM(CASE WHEN status = 'stopped' THEN 1 ELSE 0 END) as stopped,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error,
                SUM(vcpu) as total_vcpu,
                SUM(ram) as total_ram,
                SUM(disk) as total_disk
             FROM instances 
             WHERE user_id = ? AND status != 'deleted'",
            [$userId]
        );

        return $stats ?: [
            'total' => 0,
            'running' => 0,
            'stopped' => 0,
            'error' => 0,
            'total_vcpu' => 0,
            'total_ram' => 0,
            'total_disk' => 0
        ];
    }
    
    /**
     * Reboot a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'task_id' => int|null]
     */
    public static function reboot(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'task_id' => null];
        }

        if ($instance['status'] !== 'running') {
            return ['success' => false, 'message' => 'Instance must be running to reboot', 'task_id' => null];
        }

        // Update status
        Database::execute(
            "UPDATE instances SET status = 'pending', updated_at = NOW() WHERE id = ?",
            [$instanceId]
        );

        // Queue reboot task
        $taskId = Queue::push('reboot', [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'vmid' => $instance['vmid'],
            'node' => $instance['proxmox_node']
        ]);

        AuditLog::log($userId, 'instance_reboot_queued', 'instance', $instanceId);

        return ['success' => true, 'message' => 'VM reboot queued', 'task_id' => $taskId];
    }
    
    /**
     * Force kill a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'task_id' => int|null]
     */
    public static function kill(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'task_id' => null];
        }

        if ($instance['status'] === 'stopped') {
            return ['success' => false, 'message' => 'Instance is already stopped', 'task_id' => null];
        }

        // Update status
        Database::execute(
            "UPDATE instances SET status = 'pending', updated_at = NOW() WHERE id = ?",
            [$instanceId]
        );

        // Queue force stop task
        $taskId = Queue::push('stop', [
            'user_id' => $userId,
            'instance_id' => $instanceId,
            'vmid' => $instance['vmid'],
            'node' => $instance['proxmox_node'],
            'force' => true
        ]);

        AuditLog::log($userId, 'instance_kill_queued', 'instance', $instanceId);

        return ['success' => true, 'message' => 'VM force stop queued', 'task_id' => $taskId];
    }
    
    /**
     * Get console URL for a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'console_url' => string|null, 'ticket' => string|null]
     */
    public static function getConsole(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'console_url' => null, 'ticket' => null];
        }

        if ($instance['status'] !== 'running') {
            return ['success' => false, 'message' => 'Instance must be running to access console', 'console_url' => null, 'ticket' => null];
        }

        // Call Python bridge to get console URL
        $scriptPath = __DIR__ . '/../scripts/proxmox_bridge.py';
        $pythonPath = Database::getConfig('PYTHON_PATH', 'python3');
        
        $cmd = sprintf(
            '%s %s --action console --vmid %d',
            escapeshellcmd($pythonPath),
            escapeshellarg($scriptPath),
            (int)$instance['vmid']
        );
        
        exec($cmd, $output, $returnCode);
        $result = json_decode(implode('', $output), true);
        
        if ($result && $result['success'] === true) {
            AuditLog::log($userId, 'instance_console_accessed', 'instance', $instanceId);
            
            return [
                'success' => true,
                'message' => 'Console URL generated',
                'console_url' => $result['console_url'] ?? null,
                'ticket' => $result['ticket'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to generate console URL',
                'console_url' => null,
                'ticket' => null
            ];
        }
    }
    
    /**
     * List snapshots for a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @return array ['success' => bool, 'message' => string, 'snapshots' => array]
     */
    public static function listSnapshots(int $instanceId, int $userId): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied', 'snapshots' => []];
        }

        // Call Python bridge to list snapshots
        $scriptPath = __DIR__ . '/../scripts/proxmox_bridge.py';
        $pythonPath = Database::getConfig('PYTHON_PATH', 'python3');
        
        $cmd = sprintf(
            '%s %s --action snapshot_list --vmid %d',
            escapeshellcmd($pythonPath),
            escapeshellarg($scriptPath),
            (int)$instance['vmid']
        );
        
        exec($cmd, $output, $returnCode);
        $result = json_decode(implode('', $output), true);
        
        if ($result && $result['success'] === true) {
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Snapshots retrieved',
                'snapshots' => $result['snapshots'] ?? []
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to list snapshots',
                'snapshots' => []
            ];
        }
    }
    
    /**
     * Create a snapshot for a VM instance
     * 
     * @param int $instanceId Instance ID
     * @param int $userId User ID (for ownership verification)
     * @param string $snapshotName Snapshot name
     * @return array ['success' => bool, 'message' => string]
     */
    public static function createSnapshot(int $instanceId, int $userId, string $snapshotName): array
    {
        $instance = self::get($instanceId, $userId);
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance not found or access denied'];
        }

        // Validate snapshot name
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $snapshotName)) {
            return ['success' => false, 'message' => 'Invalid snapshot name. Use only letters, numbers, hyphens, and underscores.'];
        }

        // Call Python bridge to create snapshot
        $scriptPath = __DIR__ . '/../scripts/proxmox_bridge.py';
        $pythonPath = Database::getConfig('PYTHON_PATH', 'python3');
        
        $cmd = sprintf(
            '%s %s --action snapshot_create --vmid %d --snapshot-name %s',
            escapeshellcmd($pythonPath),
            escapeshellarg($scriptPath),
            (int)$instance['vmid'],
            escapeshellarg($snapshotName)
        );
        
        exec($cmd, $output, $returnCode);
        $result = json_decode(implode('', $output), true);
        
        if ($result && $result['success'] === true) {
            AuditLog::log($userId, 'instance_snapshot_created', 'instance', $instanceId, [
                'snapshot_name' => $snapshotName
            ]);
            
            return [
                'success' => true,
                'message' => $result['message'] ?? 'Snapshot created successfully'
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to create snapshot'
            ];
        }
    }
}
