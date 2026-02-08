<?php
/*
 * Nexus-IaaS Worker Daemon
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 * 
 * This daemon continuously monitors the task queue and executes
 * infrastructure operations via the Python Proxmox bridge.
 */

declare(strict_types=1);

// Set time limit to unlimited for daemon mode
set_time_limit(0);

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
use NexusIaaS\Core\Queue;
use NexusIaaS\Core\Instance;
use NexusIaaS\Core\AuditLog;

// Load environment
Database::loadEnv(__DIR__ . '/../.env');

// Configuration
$workerSleepInterval = (int)Database::getConfig('WORKER_SLEEP_INTERVAL', 5);
$maxTasksPerCycle = (int)Database::getConfig('WORKER_MAX_TASKS_PER_CYCLE', 10);
$pythonBridgePath = __DIR__ . '/proxmox_bridge.py';
$logFile = Database::getConfig('WORKER_LOG_FILE', __DIR__ . '/../logs/worker.log');

// Ensure log directory exists
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Log message to file and console
 */
function workerLog(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    echo $logMessage;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Execute Python bridge script
 */
function executePythonBridge(array $args): array
{
    global $pythonBridgePath;
    
    // Detect Python executable
    $pythonExe = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    
    // Construct command
    $command = escapeshellcmd($pythonExe) . ' ' . escapeshellarg($pythonBridgePath);
    
    foreach ($args as $key => $value) {
        if ($value === true) {
            $command .= ' --' . $key;
        } else {
            $command .= ' --' . $key . ' ' . escapeshellarg((string)$value);
        }
    }
    
    workerLog("Executing: {$command}");
    
    // Execute command
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    
    $outputString = implode("\n", $output);
    
    // Try to parse JSON output
    $result = json_decode($outputString, true);
    
    if ($result === null) {
        // Failed to parse JSON
        return [
            'success' => false,
            'message' => 'Failed to parse bridge output',
            'error' => $outputString,
            'exit_code' => $exitCode
        ];
    }
    
    $result['exit_code'] = $exitCode;
    return $result;
}

/**
 * Process a single task
 */
function processTask(array $task): bool
{
    workerLog("Processing task #{$task['id']}: {$task['action']}", 'INFO');
    
    $payload = $task['payload'];
    $action = $task['action'];
    
    try {
        // Prepare Python bridge arguments
        $args = [
            'action' => $action,
            'vmid' => $payload['vmid']
        ];
        
        // Add action-specific arguments
        switch ($action) {
            case 'create':
                $args['name'] = $payload['name'];
                $args['vcpu'] = $payload['vcpu'];
                $args['ram'] = $payload['ram'];
                $args['disk'] = $payload['disk'];
                $args['os-template'] = $payload['os_template'];
                $args['ip-address'] = $payload['ip_address'];
                $args['gateway'] = $payload['gateway'];
                $args['node'] = $payload['node'];
                break;
            
            case 'start':
            case 'stop':
            case 'reboot':
            case 'delete':
                $args['node'] = $payload['node'];
                
                // Add force flag for stop if needed
                if ($action === 'stop' && isset($payload['force']) && $payload['force']) {
                    $args['force'] = true;
                }
                break;
        }
        
        // Execute Python bridge
        $result = executePythonBridge($args);
        
        if ($result['success']) {
            workerLog("Task #{$task['id']} completed successfully", 'SUCCESS');
            
            // Update instance status based on action
            if (isset($payload['instance_id'])) {
                $newStatus = match($action) {
                    'create' => 'running',
                    'start' => 'running',
                    'stop' => 'stopped',
                    'reboot' => 'running',
                    'delete' => 'deleted',
                    default => 'running'
                };
                
                Instance::updateStatus((int)$payload['instance_id'], $newStatus);
                
                // Release IP if deleting
                if ($action === 'delete' && isset($payload['ip_address'])) {
                    Instance::releaseIp($payload['ip_address']);
                }
            }
            
            // Mark task as completed
            Queue::complete((int)$task['id'], $result);
            
            // Audit log
            if (isset($payload['user_id'])) {
                AuditLog::log(
                    (int)$payload['user_id'],
                    "task_completed_{$action}",
                    'task_queue',
                    (int)$task['id'],
                    ['result' => $result]
                );
            }
            
            return true;
        } else {
            workerLog("Task #{$task['id']} failed: {$result['message']}", 'ERROR');
            
            // Update instance status to error
            if (isset($payload['instance_id'])) {
                Instance::updateStatus((int)$payload['instance_id'], 'error');
            }
            
            // Mark task as failed
            Queue::fail((int)$task['id'], $result['message']);
            
            return false;
        }
        
    } catch (\Exception $e) {
        workerLog("Exception processing task #{$task['id']}: {$e->getMessage()}", 'ERROR');
        
        // Update instance status to error
        if (isset($payload['instance_id'])) {
            Instance::updateStatus((int)$payload['instance_id'], 'error');
        }
        
        // Mark task as failed
        Queue::fail((int)$task['id'], $e->getMessage());
        
        return false;
    }
}

/**
 * Main worker loop
 */
function workerLoop(): void
{
    global $workerSleepInterval, $maxTasksPerCycle;
    
    workerLog("Worker daemon started", 'INFO');
    workerLog("Sleep interval: {$workerSleepInterval}s, Max tasks per cycle: {$maxTasksPerCycle}", 'INFO');
    
    $cycleCount = 0;
    
    while (true) {
        $cycleCount++;
        
        try {
            // Get pending tasks count
            $pendingCount = Queue::getPendingCount();
            
            if ($pendingCount > 0) {
                workerLog("Cycle #{$cycleCount}: {$pendingCount} pending task(s)", 'INFO');
                
                // Process tasks
                $processed = 0;
                while ($processed < $maxTasksPerCycle) {
                    $task = Queue::pop();
                    
                    if (!$task) {
                        break;
                    }
                    
                    processTask($task);
                    $processed++;
                }
                
                workerLog("Processed {$processed} task(s) in cycle #{$cycleCount}", 'INFO');
            } else {
                // No tasks - log every 100 cycles to reduce log spam
                if ($cycleCount % 100 === 0) {
                    workerLog("Cycle #{$cycleCount}: No pending tasks", 'DEBUG');
                }
            }
            
            // Sleep before next cycle
            sleep($workerSleepInterval);
            
        } catch (\Exception $e) {
            workerLog("Worker error in cycle #{$cycleCount}: {$e->getMessage()}", 'ERROR');
            sleep($workerSleepInterval);
        }
    }
}

// Handle signals for graceful shutdown
if (function_exists('pcntl_signal')) {
    declare(ticks = 1);
    
    pcntl_signal(SIGTERM, function() {
        workerLog("Received SIGTERM, shutting down gracefully...", 'INFO');
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() {
        workerLog("Received SIGINT, shutting down gracefully...", 'INFO');
        exit(0);
    });
}

// Check if Python bridge exists
if (!file_exists(__DIR__ . '/proxmox_bridge.py')) {
    workerLog("Python bridge script not found! Please ensure proxmox_bridge.py exists.", 'ERROR');
    exit(1);
}

// Start worker
try {
    workerLoop();
} catch (\Exception $e) {
    workerLog("Fatal error: {$e->getMessage()}", 'CRITICAL');
    exit(1);
}
