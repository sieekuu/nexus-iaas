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
 * Billing and Balance Management
 * 
 * Handles user balance, transactions, and billing operations.
 */
class Billing
{
    /**
     * Get user balance
     * 
     * @param int $userId User ID
     * @return float Balance amount
     */
    public static function getBalance(int $userId): float
    {
        $user = Database::queryOne("SELECT balance FROM users WHERE id = ?", [$userId]);
        return $user ? (float)$user['balance'] : 0.0;
    }

    /**
     * Add balance to user account
     * 
     * @param int $userId User ID
     * @param float $amount Amount to add
     * @param string $description Transaction description
     * @return bool
     */
    public static function addBalance(int $userId, float $amount, string $description = 'Balance added'): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            Database::beginTransaction();

            // Update balance
            Database::execute(
                "UPDATE users SET balance = balance + ? WHERE id = ?",
                [$amount, $userId]
            );

            // Log transaction
            AuditLog::log(
                $userId,
                'balance_added',
                'user',
                $userId,
                [
                    'amount' => $amount,
                    'description' => $description,
                    'new_balance' => self::getBalance($userId)
                ]
            );

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Add balance error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deduct balance from user account
     * 
     * @param int $userId User ID
     * @param float $amount Amount to deduct
     * @param string $description Transaction description
     * @return bool
     */
    public static function deductBalance(int $userId, float $amount, string $description = 'Balance deducted'): bool
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            Database::beginTransaction();

            // Check sufficient balance
            $currentBalance = self::getBalance($userId);
            if ($currentBalance < $amount) {
                Database::rollback();
                return false;
            }

            // Update balance
            Database::execute(
                "UPDATE users SET balance = balance - ? WHERE id = ?",
                [$amount, $userId]
            );

            // Log transaction
            AuditLog::log(
                $userId,
                'balance_deducted',
                'user',
                $userId,
                [
                    'amount' => $amount,
                    'description' => $description,
                    'new_balance' => self::getBalance($userId)
                ]
            );

            Database::commit();
            return true;
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Deduct balance error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate hourly cost for running VMs (for future billing automation)
     * 
     * @param int $userId User ID
     * @return float Total hourly cost
     */
    public static function calculateHourlyCost(int $userId): float
    {
        $result = Database::queryOne(
            "SELECT SUM(price_per_hour) as total_cost 
             FROM instances 
             WHERE user_id = ? AND status = 'running'",
            [$userId]
        );

        return $result ? (float)($result['total_cost'] ?? 0.0) : 0.0;
    }

    /**
     * Get transaction history (from audit logs)
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of records
     * @return array List of transactions
     */
    public static function getTransactionHistory(int $userId, int $limit = 50): array
    {
        return Database::query(
            "SELECT * FROM audit_logs 
             WHERE user_id = ? AND action IN ('balance_added', 'balance_deducted') 
             ORDER BY timestamp DESC 
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Get billing summary for user
     * 
     * @param int $userId User ID
     * @return array Billing summary
     */
    public static function getSummary(int $userId): array
    {
        $balance = self::getBalance($userId);
        $hourlyCost = self::calculateHourlyCost($userId);
        $hoursRemaining = $hourlyCost > 0 ? ($balance / $hourlyCost) : 0;

        // Get total spent this month
        $thisMonth = Database::queryOne(
            "SELECT SUM(CAST(JSON_EXTRACT(details, '$.amount') AS DECIMAL(10,2))) as spent 
             FROM audit_logs 
             WHERE user_id = ? 
             AND action = 'balance_deducted' 
             AND MONTH(timestamp) = MONTH(CURRENT_DATE()) 
             AND YEAR(timestamp) = YEAR(CURRENT_DATE())",
            [$userId]
        );

        $totalSpentThisMonth = $thisMonth ? (float)($thisMonth['spent'] ?? 0.0) : 0.0;

        return [
            'current_balance' => $balance,
            'hourly_cost' => $hourlyCost,
            'hours_remaining' => round($hoursRemaining, 2),
            'total_spent_this_month' => $totalSpentThisMonth
        ];
    }

    /**
     * Process hourly billing (to be called by a cron job)
     * 
     * @return array ['processed' => int, 'failed' => int]
     */
    public static function processHourlyBilling(): array
    {
        $processed = 0;
        $failed = 0;

        // Get all running instances
        $instances = Database::query(
            "SELECT i.*, u.balance 
             FROM instances i 
             JOIN users u ON i.user_id = u.id 
             WHERE i.status = 'running'"
        );

        foreach ($instances as $instance) {
            $cost = (float)$instance['price_per_hour'];
            
            if ($instance['balance'] >= $cost) {
                $success = self::deductBalance(
                    (int)$instance['user_id'],
                    $cost,
                    "Hourly billing for VM: {$instance['name']}"
                );

                if ($success) {
                    $processed++;
                } else {
                    $failed++;
                }
            } else {
                // Insufficient balance - could implement auto-suspend here
                $failed++;
                AuditLog::log(
                    (int)$instance['user_id'],
                    'billing_failed_insufficient_balance',
                    'instance',
                    (int)$instance['id'],
                    ['cost' => $cost, 'balance' => $instance['balance']]
                );
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }
}
