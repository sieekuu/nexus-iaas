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
 * Authentication and Session Management
 * 
 * Handles user registration, login, session management, and authorization.
 */
class Auth
{
    private const SESSION_KEY = 'nexus_user';
    private const CSRF_TOKEN_KEY = 'nexus_csrf_token';
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_TIME = 900; // 15 minutes

    /**
     * Initialize session if not already started
     * 
     * @return void
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', '1'); // HTTPS only
            ini_set('session.cookie_samesite', 'Strict');
            
            session_name('NEXUS_SESSION');
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created_at'])) {
                $_SESSION['created_at'] = time();
            } else if (time() - $_SESSION['created_at'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created_at'] = time();
            }
        }
    }

    /**
     * Register a new user
     * 
     * @param string $email User email
     * @param string $password Plain text password
     * @return array ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public static function register(string $email, string $password): array
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address', 'user_id' => null];
        }

        // Validate password strength
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters', 'user_id' => null];
        }

        // Check if user already exists
        $existing = Database::queryOne(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if ($existing) {
            return ['success' => false, 'message' => 'Email already registered', 'user_id' => null];
        }

        // Hash password using Argon2id
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 1
        ]);

        try {
            Database::execute(
                "INSERT INTO users (email, password_hash, balance, created_at) VALUES (?, ?, 0.00, NOW())",
                [$email, $passwordHash]
            );

            $userId = (int)Database::lastInsertId();

            // Log registration
            AuditLog::log($userId, 'user_registered', 'user', $userId, ['email' => $email]);

            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $userId];
        } catch (\Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed', 'user_id' => null];
        }
    }

    /**
     * Authenticate user and create session
     * 
     * @param string $email User email
     * @param string $password Plain text password
     * @return array ['success' => bool, 'message' => string]
     */
    public static function login(string $email, string $password): array
    {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Fetch user from database
        $user = Database::queryOne(
            "SELECT id, email, password_hash, is_admin, is_banned FROM users WHERE email = ? LIMIT 1",
            [$email]
        );

        if (!$user) {
            // Timing attack prevention: still verify a dummy hash
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$ZHVtbXk$dummy');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Check if user is banned
        if ($user['is_banned']) {
            AuditLog::log((int)$user['id'], 'login_attempt_banned', 'user', (int)$user['id']);
            return ['success' => false, 'message' => 'Account suspended. Contact administrator.'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            AuditLog::log((int)$user['id'], 'login_failed', 'user', (int)$user['id']);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Check if password needs rehashing (algorithm update)
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1
            ]);
            Database::execute(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [$newHash, $user['id']]
            );
        }

        // Update last login
        Database::execute(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );

        // Create session
        self::init();
        $_SESSION[self::SESSION_KEY] = [
            'id' => (int)$user['id'],
            'email' => $user['email'],
            'is_admin' => (bool)$user['is_admin'],
            'login_time' => time()
        ];

        // Generate CSRF token
        self::generateCsrfToken();

        // Log successful login
        AuditLog::log((int)$user['id'], 'login_success', 'user', (int)$user['id']);

        return ['success' => true, 'message' => 'Login successful'];
    }

    /**
     * Logout user and destroy session
     * 
     * @return void
     */
    public static function logout(): void
    {
        self::init();
        
        if (self::isLoggedIn()) {
            $userId = self::getUserId();
            AuditLog::log($userId, 'logout', 'user', $userId);
        }

        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }

    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        self::init();
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * Get current user ID
     * 
     * @return int|null
     */
    public static function getUserId(): ?int
    {
        self::init();
        return $_SESSION[self::SESSION_KEY]['id'] ?? null;
    }

    /**
     * Get current user data
     * 
     * @return array|null
     */
    public static function getUser(): ?array
    {
        self::init();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Check if current user is admin
     * 
     * @return bool
     */
    public static function isAdmin(): bool
    {
        self::init();
        return $_SESSION[self::SESSION_KEY]['is_admin'] ?? false;
    }

    /**
     * Require authentication (redirect if not logged in)
     * 
     * @return void
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Require admin privileges
     * 
     * @return void
     */
    public static function requireAdmin(): void
    {
        self::requireAuth();
        
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Access denied. Administrator privileges required.');
        }
    }

    /**
     * Generate CSRF token
     * 
     * @return string
     */
    public static function generateCsrfToken(): string
    {
        self::init();
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::CSRF_TOKEN_KEY] = $token;
        $_SESSION[self::CSRF_TOKEN_KEY . '_time'] = time();
        
        return $token;
    }

    /**
     * Get CSRF token (generate if not exists)
     * 
     * @return string
     */
    public static function getCsrfToken(): string
    {
        self::init();
        
        if (!isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            return self::generateCsrfToken();
        }
        
        // Check if token is expired (1 hour)
        $tokenTime = $_SESSION[self::CSRF_TOKEN_KEY . '_time'] ?? 0;
        if (time() - $tokenTime > 3600) {
            return self::generateCsrfToken();
        }
        
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @return bool
     */
    public static function verifyCsrfToken(string $token): bool
    {
        self::init();
        
        $sessionToken = $_SESSION[self::CSRF_TOKEN_KEY] ?? '';
        
        if (empty($sessionToken) || empty($token)) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }

    /**
     * Ban a user
     * 
     * @param int $userId User ID to ban
     * @return bool
     */
    public static function banUser(int $userId): bool
    {
        $result = Database::execute(
            "UPDATE users SET is_banned = 1 WHERE id = ?",
            [$userId]
        );

        if ($result > 0) {
            AuditLog::log(self::getUserId(), 'user_banned', 'user', $userId);
            return true;
        }

        return false;
    }

    /**
     * Unban a user
     * 
     * @param int $userId User ID to unban
     * @return bool
     */
    public static function unbanUser(int $userId): bool
    {
        $result = Database::execute(
            "UPDATE users SET is_banned = 0 WHERE id = ?",
            [$userId]
        );

        if ($result > 0) {
            AuditLog::log(self::getUserId(), 'user_unbanned', 'user', $userId);
            return true;
        }

        return false;
    }
}
