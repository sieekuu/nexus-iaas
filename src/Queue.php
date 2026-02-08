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
 * Task Queue Management
 * 
 * Manages the task queue for asynchronous infrastructure operations.
 */
class Queue
{
    /**
     * Push a new task to the queue
     * 
     * @param string $action Action type (create, start, stop, delete, rebuild)
     * @param array $payload Task parameters
     * @param int $maxRetries Maximum retry attempts
     * @return int Task ID
     */
    public static function push(string $action, array $payload, int $maxRetries = 3): int
    {
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        Database::execute(
            "INSERT INTO task_queue (action, payload_json, status, max_retries, created_at) 
             VALUES (?, ?, 'pending', ?, NOW())",
            [$action, $payloadJson, $maxRetries]
        );

        $taskId = (int)Database::lastInsertId();

        // Log task creation
        if (isset($payload['user_id'])) {
            AuditLog::log(
                (int)$payload['user_id'],
                "task_queued_{$action}",
                'task_queue',
                $taskId,
                ['action' => $action, 'task_id' => $taskId]
            );
        }

        return $taskId;
    }

    /**
     * Pop next pending task from the queue
     * 
     * @return array|null Task data or null if no pending tasks
     */
    public static function pop(): ?array
    {
        // Use SELECT FOR UPDATE to prevent race conditions
        Database::beginTransaction();

        try {
            $task = Database::queryOne(
                "SELECT * FROM task_queue 
                 WHERE status = 'pending' 
                 ORDER BY created_at ASC 
                 LIMIT 1 
                 FOR UPDATE"
            );

            if (!$task) {
                Database::commit();
                return null;
            }

            // Mark as processing
            Database::execute(
                "UPDATE task_queue 
                 SET status = 'processing', started_at = NOW() 
                 WHERE id = ?",
                [$task['id']]
            );

            Database::commit();

            // Parse JSON payload
            $task['payload'] = json_decode($task['payload_json'], true);

            return $task;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Queue pop error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark task as completed
     * 
     * @param int $taskId Task ID
     * @param array|null $result Result data (optional)
     * @return bool
     */
    public static function complete(int $taskId, ?array $result = null): bool
    {
        $resultJson = $result ? json_encode($result, JSON_UNESCAPED_UNICODE) : null;

        $affected = Database::execute(
            "UPDATE task_queue 
             SET status = 'completed', result_json = ?, finished_at = NOW() 
             WHERE id = ?",
            [$resultJson, $taskId]
        );

        return $affected > 0;
    }

    /**
     * Mark task as failed
     * 
     * @param int $taskId Task ID
     * @param string $errorMessage Error description
     * @return bool
     */
    public static function fail(int $taskId, string $errorMessage): bool
    {
        // Get current task info
        $task = Database::queryOne(
            "SELECT retry_count, max_retries FROM task_queue WHERE id = ?",
            [$taskId]
        );

        if (!$task) {
            return false;
        }

        $retryCount = (int)$task['retry_count'] + 1;
        $maxRetries = (int)$task['max_retries'];

        // Check if should retry
        if ($retryCount < $maxRetries) {
            $affected = Database::execute(
                "UPDATE task_queue 
                 SET status = 'pending', retry_count = ?, error_message = ? 
                 WHERE id = ?",
                [$retryCount, $errorMessage, $taskId]
            );
        } else {
            // Max retries reached, mark as permanently failed
            $affected = Database::execute(
                "UPDATE task_queue 
                 SET status = 'failed', retry_count = ?, error_message = ?, finished_at = NOW() 
                 WHERE id = ?",
                [$retryCount, $errorMessage, $taskId]
            );
        }

        return $affected > 0;
    }

    /**
     * Get task status
     * 
     * @param int $taskId Task ID
     * @return array|null Task data
     */
    public static function getTask(int $taskId): ?array
    {
        $task = Database::queryOne(
            "SELECT * FROM task_queue WHERE id = ?",
            [$taskId]
        );

        if ($task) {
            $task['payload'] = json_decode($task['payload_json'], true);
            $task['result'] = $task['result_json'] ? json_decode($task['result_json'], true) : null;
        }

        return $task ?: null;
    }

    /**
     * Get all tasks for a specific instance
     * 
     * @param int $instanceId Instance ID
     * @return array List of tasks
     */
    public static function getInstanceTasks(int $instanceId): array
    {
        $tasks = Database::query(
            "SELECT * FROM task_queue 
             WHERE JSON_EXTRACT(payload_json, '$.instance_id') = ? 
             ORDER BY created_at DESC",
            [$instanceId]
        );

        foreach ($tasks as &$task) {
            $task['payload'] = json_decode($task['payload_json'], true);
            $task['result'] = $task['result_json'] ? json_decode($task['result_json'], true) : null;
        }

        return $tasks;
    }

    /**
     * Get pending task count
     * 
     * @return int Number of pending tasks
     */
    public static function getPendingCount(): int
    {
        $result = Database::queryOne("SELECT COUNT(*) as count FROM task_queue WHERE status = 'pending'");
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get processing task count
     * 
     * @return int Number of processing tasks
     */
    public static function getProcessingCount(): int
    {
        $result = Database::queryOne("SELECT COUNT(*) as count FROM task_queue WHERE status = 'processing'");
        return (int)($result['count'] ?? 0);
    }

    /**
     * Clean old completed/failed tasks
     * 
     * @param int $daysToKeep Number of days to keep old tasks
     * @return int Number of deleted tasks
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $affected = Database::execute(
            "DELETE FROM task_queue 
             WHERE status IN ('completed', 'failed') 
             AND finished_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysToKeep]
        );

        return $affected;
    }
}
