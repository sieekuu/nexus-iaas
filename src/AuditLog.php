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
 * Audit Logging
 * 
 * Tracks all user actions and system events for compliance and security.
 */
class AuditLog
{
    /**
     * Log an action to the audit table
     * 
     * @param int|null $userId User ID performing the action (null for system actions)
     * @param string $action Action description
     * @param string|null $resourceType Type of resource (instance, user, ip_pool, etc.)
     * @param int|null $resourceId Resource ID
     * @param array|null $details Additional details (stored as JSON)
     * @return bool
     */
    public static function log(
        ?int $userId,
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $details = null
    ): bool {
        try {
            $ipAddress = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

            Database::execute(
                "INSERT INTO audit_logs (user_id, action, resource_type, resource_id, ip_address, user_agent, details, timestamp) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [$userId, $action, $resourceType, $resourceId, $ipAddress, $userAgent, $detailsJson]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit logs for a specific user
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array List of audit logs
     */
    public static function getUserLogs(int $userId, int $limit = 50, int $offset = 0): array
    {
        return Database::query(
            "SELECT * FROM audit_logs 
             WHERE user_id = ? 
             ORDER BY timestamp DESC 
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );
    }

    /**
     * Get audit logs for a specific resource
     * 
     * @param string $resourceType Resource type
     * @param int $resourceId Resource ID
     * @param int $limit Maximum number of records
     * @return array List of audit logs
     */
    public static function getResourceLogs(string $resourceType, int $resourceId, int $limit = 50): array
    {
        return Database::query(
            "SELECT al.*, u.email as user_email 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             WHERE al.resource_type = ? AND al.resource_id = ? 
             ORDER BY al.timestamp DESC 
             LIMIT ?",
            [$resourceType, $resourceId, $limit]
        );
    }

    /**
     * Get all recent audit logs (admin only)
     * 
     * @param int $limit Maximum number of records
     * @param int $offset Offset for pagination
     * @return array List of audit logs
     */
    public static function getAllLogs(int $limit = 100, int $offset = 0): array
    {
        return Database::query(
            "SELECT al.*, u.email as user_email 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             ORDER BY al.timestamp DESC 
             LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
    }

    /**
     * Search audit logs
     * 
     * @param array $filters Filter criteria
     * @param int $limit Maximum number of records
     * @return array List of audit logs
     */
    public static function search(array $filters, int $limit = 100): array
    {
        $where = [];
        $params = [];

        if (isset($filters['user_id'])) {
            $where[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (isset($filters['action'])) {
            $where[] = "al.action LIKE ?";
            $params[] = "%{$filters['action']}%";
        }

        if (isset($filters['resource_type'])) {
            $where[] = "al.resource_type = ?";
            $params[] = $filters['resource_type'];
        }

        if (isset($filters['date_from'])) {
            $where[] = "al.timestamp >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $where[] = "al.timestamp <= ?";
            $params[] = $filters['date_to'];
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        $params[] = $limit;

        return Database::query(
            "SELECT al.*, u.email as user_email 
             FROM audit_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             {$whereClause} 
             ORDER BY al.timestamp DESC 
             LIMIT ?",
            $params
        );
    }

    /**
     * Get client IP address (handles proxies)
     * 
     * @return string|null
     */
    private static function getClientIp(): ?string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Clean old audit logs
     * 
     * @param int $daysToKeep Number of days to keep logs
     * @return int Number of deleted records
     */
    public static function cleanup(int $daysToKeep = 90): int
    {
        return Database::execute(
            "DELETE FROM audit_logs 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$daysToKeep]
        );
    }
}
