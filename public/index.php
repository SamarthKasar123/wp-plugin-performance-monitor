<?php
/**
 * WordPress Plugin Performance Monitor
 * Main Entry Point
 * 
 * A comprehensive analytics dashboard for monitoring WordPress plugin performance
 * across multiple enterprise WordPress installations.
 * 
 * @author rtCamp Demo Project
 * @version 1.0.0
 */

session_start();

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define application constants
define('APP_ROOT', dirname(__DIR__));
define('CONFIG_DIR', APP_ROOT . '/config');
define('SRC_DIR', APP_ROOT . '/src');
define('PUBLIC_DIR', __DIR__);
define('APP_VERSION', '1.0.0');

// Autoloader for our classes
spl_autoload_register(function ($class) {
    $file = SRC_DIR . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load configuration
try {
    $dbConfig = require CONFIG_DIR . '/database.php';
    $wpConfig = require CONFIG_DIR . '/wordpress-sites.php';
} catch (Exception $e) {
    die('Configuration files not found. Please copy example files and configure them.');
}

// Database connection
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Simple routing
$request = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Include base controller
require_once SRC_DIR . '/controllers/BaseController.php';

// Route to appropriate controller
switch ($request) {
    case 'dashboard':
        require_once SRC_DIR . '/controllers/DashboardController.php';
        $controller = new DashboardController($pdo, $wpConfig);
        break;
        
    case 'sites':
        require_once SRC_DIR . '/controllers/SiteController.php';
        $controller = new SiteController($pdo, $wpConfig);
        break;
        
    case 'plugins':
        require_once SRC_DIR . '/controllers/PluginController.php';
        $controller = new PluginController($pdo, $wpConfig);
        break;
        
    case 'performance':
        require_once SRC_DIR . '/controllers/PerformanceController.php';
        $controller = new PerformanceController($pdo, $wpConfig);
        break;
        
    case 'security':
        require_once SRC_DIR . '/controllers/SecurityController.php';
        $controller = new SecurityController($pdo, $wpConfig);
        break;
        
    case 'reports':
        require_once SRC_DIR . '/controllers/ReportController.php';
        $controller = new ReportController($pdo, $wpConfig);
        break;
        
    case 'api':
        require_once SRC_DIR . '/controllers/ApiController.php';
        $controller = new ApiController($pdo, $wpConfig);
        break;
        
    case 'auth':
        require_once SRC_DIR . '/controllers/AuthController.php';
        $controller = new AuthController($pdo);
        break;
        
    default:
        require_once SRC_DIR . '/controllers/DashboardController.php';
        $controller = new DashboardController($pdo, $wpConfig);
        break;
}

// Execute controller action
try {
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        $controller->index();
    }
} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log('Application Error: ' . $e->getMessage());
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred while processing your request.'
        ]);
    } else {
        include SRC_DIR . '/views/error.php';
    }
}
