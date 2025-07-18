<?php
/**
 * Base Controller
 * 
 * Provides common functionality for all controllers
 */

abstract class BaseController
{
    protected $pdo;
    protected $wpConfig;
    
    public function __construct(PDO $pdo, array $wpConfig = [])
    {
        $this->pdo = $pdo;
        $this->wpConfig = $wpConfig;
    }
    
    /**
     * Check if user is authenticated
     */
    protected function requireAuth()
    {
        if (!isset($_SESSION['user_id'])) {
            if ($this->isAjaxRequest()) {
                $this->jsonResponse(['success' => false, 'error' => 'Authentication required']);
                exit;
            } else {
                header('Location: ?page=auth&action=login');
                exit;
            }
        }
    }
    
    /**
     * Check if request is AJAX
     */
    protected function isAjaxRequest()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * Send JSON response
     */
    protected function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
    
    /**
     * Sanitize input data
     */
    protected function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    protected function generateCsrfToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Load view with data
     */
    protected function view($viewName, $data = [])
    {
        extract($data);
        $csrfToken = $this->generateCsrfToken();
        require_once SRC_DIR . "/views/{$viewName}.php";
    }
    
    /**
     * Get current user data
     */
    protected function getCurrentUser()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    /**
     * Log activity
     */
    protected function logActivity($action, $details = '')
    {
        $user = $this->getCurrentUser();
        if ($user) {
            error_log("User {$user['username']} - {$action}: {$details}");
        }
    }
}
