<?php
/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

declare(strict_types=1);

namespace NexusIaaS\Config;

use PDO;
use PDOException;

/**
 * Database Connection Singleton
 * 
 * Provides a single, reusable PDO connection instance throughout the application.
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Load environment configuration from .env file
     * 
     * @param string $envPath Path to .env file
     * @return void
     */
    public static function loadEnv(string $envPath = __DIR__ . '/../.env'): void
    {
        if (!file_exists($envPath)) {
            throw new \RuntimeException("Environment file not found: {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                self::$config[$key] = $value;
                
                // Also set as environment variable
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                }
            }
        }
    }

    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function getConfig(string $key, $default = null)
    {
        return self::$config[$key] ?? getenv($key) ?: $default;
    }

    /**
     * Get PDO instance (Singleton pattern)
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Load environment if not already loaded
            if (empty(self::$config)) {
                self::loadEnv();
            }

            $host = self::getConfig('DB_HOST', 'localhost');
            $port = self::getConfig('DB_PORT', '3306');
            $dbname = self::getConfig('DB_NAME', 'nexus_iaas');
            $user = self::getConfig('DB_USER', 'root');
            $pass = self::getConfig('DB_PASS', '');
            $charset = 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Log error but don't expose database details
                error_log("Database Connection Error: " . $e->getMessage());
                throw new PDOException("Unable to connect to the database. Please check configuration.");
            }
        }

        return self::$instance;
    }

    /**
     * Execute a query and return all results
     * 
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return array
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single row
     * 
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return array|false
     */
    public static function queryOne(string $sql, array $params = [])
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query
     * 
     * @param string $sql SQL query
     * @param array $params Parameters for prepared statement
     * @return int Number of affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get last inserted ID
     * 
     * @return string
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    /**
     * Begin a transaction
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit a transaction
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback a transaction
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }
}
