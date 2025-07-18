<?php
/**
 * Dashboard Controller
 * 
 * Handles the main dashboard displaying WordPress plugin performance overview
 */

require_once SRC_DIR . '/models/WordPressSite.php';
require_once SRC_DIR . '/models/Plugin.php';
require_once SRC_DIR . '/models/PerformanceMetric.php';
require_once SRC_DIR . '/services/PerformanceAnalyzer.php';

class DashboardController extends BaseController
{
    private $siteModel;
    private $pluginModel;
    private $performanceModel;
    private $analyzer;
    
    public function __construct(PDO $pdo, array $wpConfig = [])
    {
        parent::__construct($pdo, $wpConfig);
        $this->siteModel = new WordPressSite($pdo);
        $this->pluginModel = new Plugin($pdo);
        $this->performanceModel = new PerformanceMetric($pdo);
        $this->analyzer = new PerformanceAnalyzer($pdo);
    }
    
    /**
     * Display main dashboard
     */
    public function index()
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        // Get dashboard statistics
        $stats = [
            'total_sites' => $this->siteModel->countByUser($user['id']),
            'total_plugins' => $this->pluginModel->countActiveByUser($user['id']),
            'critical_alerts' => $this->getAlertCount('critical'),
            'avg_performance_score' => $this->analyzer->getAveragePerformanceScore($user['id'])
        ];
        
        // Get recent performance data
        $recentMetrics = $this->performanceModel->getRecentByUser($user['id'], 7);
        
        // Get top performing and poorly performing plugins
        $topPlugins = $this->analyzer->getTopPerformingPlugins($user['id'], 5);
        $poorPlugins = $this->analyzer->getPoorPerformingPlugins($user['id'], 5);
        
        // Get recent alerts
        $recentAlerts = $this->getRecentAlerts($user['id'], 10);
        
        // Get site performance summary
        $sitePerformance = $this->analyzer->getSitePerformanceSummary($user['id']);
        
        $this->view('dashboard/index', [
            'user' => $user,
            'stats' => $stats,
            'recentMetrics' => $recentMetrics,
            'topPlugins' => $topPlugins,
            'poorPlugins' => $poorPlugins,
            'recentAlerts' => $recentAlerts,
            'sitePerformance' => $sitePerformance
        ]);
    }
    
    /**
     * Get performance data for charts (AJAX)
     */
    public function getPerformanceData()
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $days = intval($_GET['days'] ?? 7);
        $siteId = intval($_GET['site_id'] ?? 0);
        
        try {
            $data = $this->analyzer->getPerformanceChartData($user['id'], $days, $siteId);
            $this->jsonResponse(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get live alerts (AJAX)
     */
    public function getLiveAlerts()
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $lastCheck = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 minute'));
        
        try {
            $alerts = $this->getAlertsAfter($user['id'], $lastCheck);
            $this->jsonResponse(['success' => true, 'alerts' => $alerts, 'count' => count($alerts)]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Update dashboard widget settings (AJAX)
     */
    public function updateWidgetSettings()
    {
        $this->requireAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'POST method required'], 405);
            return;
        }
        
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->validateCsrfToken($token)) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 403);
            return;
        }
        
        $user = $this->getCurrentUser();
        $settings = $this->sanitize($_POST['settings'] ?? []);
        
        try {
            // Store user dashboard preferences (you could create a user_preferences table)
            $_SESSION['dashboard_settings'] = $settings;
            $this->logActivity('Dashboard settings updated', json_encode($settings));
            
            $this->jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Export dashboard data
     */
    public function exportData()
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        $format = $this->sanitize($_GET['format'] ?? 'csv');
        
        try {
            switch ($format) {
                case 'csv':
                    $this->exportToCsv($user['id']);
                    break;
                case 'json':
                    $this->exportToJson($user['id']);
                    break;
                default:
                    throw new Exception('Unsupported export format');
            }
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get alert count by severity
     */
    private function getAlertCount($severity)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM alerts a
            JOIN wordpress_sites ws ON a.site_id = ws.id
            WHERE ws.user_id = ? AND a.severity = ? AND a.resolved_at IS NULL
        ");
        $stmt->execute([$_SESSION['user_id'], $severity]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get recent alerts
     */
    private function getRecentAlerts($userId, $limit)
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, ws.site_name 
            FROM alerts a
            JOIN wordpress_sites ws ON a.site_id = ws.id
            WHERE ws.user_id = ?
            ORDER BY a.triggered_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get alerts after specific time
     */
    private function getAlertsAfter($userId, $timestamp)
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, ws.site_name 
            FROM alerts a
            JOIN wordpress_sites ws ON a.site_id = ws.id
            WHERE ws.user_id = ? AND a.triggered_at > ?
            ORDER BY a.triggered_at DESC
        ");
        $stmt->execute([$userId, $timestamp]);
        return $stmt->fetchAll();
    }
    
    /**
     * Export data to CSV
     */
    private function exportToCsv($userId)
    {
        $filename = 'wp-plugin-performance-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Site', 'Plugin', 'Performance Score', 'Memory Usage (MB)', 'Load Time (ms)', 'Date']);
        
        $stmt = $this->pdo->prepare("
            SELECT ws.site_name, p.name, pm.performance_score, 
                   pm.memory_usage_mb, pm.page_load_time_ms, pm.metric_date
            FROM performance_metrics pm
            JOIN plugin_installations pi ON pm.installation_id = pi.id
            JOIN plugins p ON pi.plugin_id = p.id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ?
            ORDER BY pm.metric_date DESC
            LIMIT 1000
        ");
        $stmt->execute([$userId]);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
    
    /**
     * Export data to JSON
     */
    private function exportToJson($userId)
    {
        $data = [
            'export_date' => date('c'),
            'user_id' => $userId,
            'sites' => $this->siteModel->getByUser($userId),
            'performance_summary' => $this->analyzer->getSitePerformanceSummary($userId),
            'recent_alerts' => $this->getRecentAlerts($userId, 50)
        ];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="wp-plugin-performance-' . date('Y-m-d') . '.json"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
