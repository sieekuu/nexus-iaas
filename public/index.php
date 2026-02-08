<?php
/*
 * Nexus-IaaS
 * Copyright (c) 2026 Krzysztof Siek
 * Licensed under the MIT License.
 */

declare(strict_types=1);

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
use NexusIaaS\Core\Billing;

// Load environment
Database::loadEnv(__DIR__ . '/../.env');

// Initialize session
Auth::init();

// Simple router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Public routes
if ($uri === '/' || $uri === '/index.php') {
    if (Auth::isLoggedIn()) {
        header('Location: /dashboard.php');
    } else {
        header('Location: /login.php');
    }
    exit;
}

if ($uri === '/login.php') {
    require __DIR__ . '/../views/login.php';
    exit;
}

if ($uri === '/register.php') {
    require __DIR__ . '/../views/register.php';
    exit;
}

if ($uri === '/logout.php') {
    Auth::logout();
    header('Location: /login.php');
    exit;
}

// Protected routes (require authentication)
Auth::requireAuth();

switch ($uri) {
    case '/dashboard.php':
        $user = Auth::getUser();
        $instances = Instance::getUserInstances($user['id']);
        $balance = Billing::getBalance($user['id']);
        $stats = Instance::getUserStats($user['id']);
        $billingSummary = Billing::getSummary($user['id']);
        require __DIR__ . '/../views/dashboard.php';
        break;

    case '/instances.php':
        $user = Auth::getUser();
        $instances = Instance::getUserInstances($user['id']);
        require __DIR__ . '/../views/instances.php';
        break;

    case '/billing.php':
        $user = Auth::getUser();
        $balance = Billing::getBalance($user['id']);
        $billingSummary = Billing::getSummary($user['id']);
        $transactions = Billing::getTransactionHistory($user['id'], 50);
        require __DIR__ . '/../views/billing.php';
        break;

    case '/settings.php':
        $user = Auth::getUser();
        require __DIR__ . '/../views/settings.php';
        break;

    case '/admin.php':
        Auth::requireAdmin();
        $allInstances = Instance::getAllInstances();
        require __DIR__ . '/../views/admin.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 - Page Not Found</h1>";
        break;
}
