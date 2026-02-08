<?php
/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

declare(strict_types=1);

header('Content-Type: application/json');

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'NexusIaaS\\';
    $baseDir = __DIR__ . '/../';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use NexusIaaS\Config\Database;
use NexusIaaS\Core\Auth;
use NexusIaaS\Core\Instance;
use NexusIaaS\Core\Queue;
use NexusIaaS\Core\Billing;
use NexusIaaS\Core\AuditLog;

// Load environment
Database::loadEnv(__DIR__ . '/../.env');

// Initialize session
Auth::init();

// Parse JSON input for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && 
    stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $jsonInput = file_get_contents('php://input');
    $jsonData = json_decode($jsonInput, true);
    if ($jsonData) {
        $_POST = array_merge($_POST, $jsonData);
    }
}

// Helper function for JSON response
function jsonResponse(bool $success, string $message, $data = null, int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Require authentication for API
if (!Auth::isLoggedIn()) {
    jsonResponse(false, 'Authentication required', null, 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$userId = Auth::getUserId();

// CSRF protection for POST requests
if ($method === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::verifyCsrfToken($csrfToken)) {
        jsonResponse(false, 'Invalid CSRF token', null, 403);
    }
}

// API Routes
try {
    switch ($action) {
        // ==================== INSTANCE ACTIONS ====================
        case 'create_instance':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $config = [
                'name' => $_POST['name'] ?? '',
                'vcpu' => (int)($_POST['vcpu'] ?? 1),
                'ram' => (int)($_POST['ram'] ?? 1024),
                'disk' => (int)($_POST['disk'] ?? 20),
                'os_template' => $_POST['os_template'] ?? 'ubuntu-22.04',
                'node' => $_POST['node'] ?? null
            ];

            $result = Instance::create($userId, $config);
            jsonResponse(
                $result['success'],
                $result['message'],
                [
                    'instance_id' => $result['instance_id'],
                    'task_id' => $result['task_id']
                ],
                $result['success'] ? 201 : 400
            );
            break;

        case 'start_instance':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::start($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], ['task_id' => $result['task_id']]);
            break;

        case 'stop_instance':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::stop($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], ['task_id' => $result['task_id']]);
            break;
        
        case 'reboot_instance':
        case 'reboot':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::reboot($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], ['task_id' => $result['task_id'] ?? null]);
            break;
        
        case 'kill_instance':
        case 'kill':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::kill($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], ['task_id' => $result['task_id'] ?? null]);
            break;
        
        case 'console':
        case 'get_console':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::getConsole($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], [
                'console_url' => $result['console_url'] ?? null,
                'ticket' => $result['ticket'] ?? null
            ]);
            break;
        
        case 'snapshot_list':
        case 'list_snapshots':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::listSnapshots($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], [
                'snapshots' => $result['snapshots'] ?? []
            ]);
            break;
        
        case 'snapshot_create':
        case 'create_snapshot':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $snapshotName = $_POST['snapshot_name'] ?? '';
            
            if (empty($snapshotName)) {
                jsonResponse(false, 'Snapshot name is required', null, 400);
            }
            
            $result = Instance::createSnapshot($instanceId, $userId, $snapshotName);
            jsonResponse($result['success'], $result['message'], [
                'snapshot_name' => $snapshotName
            ]);
            break;

        case 'delete_instance':
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $instanceId = (int)($_POST['instance_id'] ?? 0);
            $result = Instance::delete($instanceId, $userId);
            jsonResponse($result['success'], $result['message'], ['task_id' => $result['task_id']]);
            break;

        case 'get_instance':
            $instanceId = (int)($_GET['instance_id'] ?? 0);
            $instance = Instance::get($instanceId, $userId);
            
            if ($instance) {
                jsonResponse(true, 'Instance retrieved', $instance);
            } else {
                jsonResponse(false, 'Instance not found', null, 404);
            }
            break;

        case 'get_instances':
            $instances = Instance::getUserInstances($userId);
            jsonResponse(true, 'Instances retrieved', $instances);
            break;

        // ==================== TASK STATUS ====================
        case 'get_task_status':
            $taskId = (int)($_GET['task_id'] ?? 0);
            $task = Queue::getTask($taskId);
            
            if ($task) {
                // Verify ownership
                if (isset($task['payload']['user_id']) && (int)$task['payload']['user_id'] !== $userId) {
                    jsonResponse(false, 'Access denied', null, 403);
                }
                jsonResponse(true, 'Task status retrieved', $task);
            } else {
                jsonResponse(false, 'Task not found', null, 404);
            }
            break;

        case 'get_queue_stats':
            if (!Auth::isAdmin()) {
                jsonResponse(false, 'Admin access required', null, 403);
            }
            
            $stats = [
                'pending' => Queue::getPendingCount(),
                'processing' => Queue::getProcessingCount()
            ];
            jsonResponse(true, 'Queue statistics', $stats);
            break;

        // ==================== BILLING ====================
        case 'get_balance':
            $balance = Billing::getBalance($userId);
            $summary = Billing::getSummary($userId);
            jsonResponse(true, 'Balance retrieved', $summary);
            break;

        case 'add_balance':
            if (!Auth::isAdmin()) {
                jsonResponse(false, 'Admin access required', null, 403);
            }
            
            if ($method !== 'POST') {
                jsonResponse(false, 'Method not allowed', null, 405);
            }

            $targetUserId = (int)($_POST['user_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $description = $_POST['description'] ?? 'Balance added by admin';

            $result = Billing::addBalance($targetUserId, $amount, $description);
            jsonResponse($result, $result ? 'Balance added' : 'Failed to add balance');
            break;

        // ==================== STATISTICS ====================
        case 'get_stats':
            $stats = Instance::getUserStats($userId);
            $billingSummary = Billing::getSummary($userId);
            
            jsonResponse(true, 'Statistics retrieved', [
                'instances' => $stats,
                'billing' => $billingSummary
            ]);
            break;

        // ==================== AUDIT LOGS ====================
        case 'get_audit_logs':
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $logs = AuditLog::getUserLogs($userId, $limit);
            jsonResponse(true, 'Audit logs retrieved', $logs);
            break;

        // ==================== KEEP-ALIVE (for polling) ====================
        case 'ping':
            jsonResponse(true, 'pong', [
                'user_id' => $userId,
                'is_admin' => Auth::isAdmin(),
                'server_time' => date('Y-m-d H:i:s')
            ]);
            break;

        default:
            jsonResponse(false, 'Unknown action', null, 400);
            break;
    }
} catch (\Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse(false, 'Internal server error', null, 500);
}
