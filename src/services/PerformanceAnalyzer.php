<?php
/**
 * Performance Analyzer Service
 * 
 * Analyzes WordPress plugin performance data and provides insights
 */

class PerformanceAnalyzer
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get average performance score for user's sites
     */
    public function getAveragePerformanceScore($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT AVG(pm.performance_score) as avg_score
            FROM performance_metrics pm
            JOIN plugin_installations pi ON pm.installation_id = pi.id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ? 
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return round($result['avg_score'] ?? 0, 1);
    }
    
    /**
     * Get top performing plugins
     */
    public function getTopPerformingPlugins($userId, $limit = 5)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.name,
                p.slug,
                AVG(pm.performance_score) as avg_score,
                AVG(pm.memory_usage_mb) as avg_memory,
                AVG(pm.page_load_time_ms) as avg_load_time,
                COUNT(DISTINCT pi.site_id) as site_count
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            JOIN performance_metrics pm ON pi.id = pm.installation_id
            WHERE ws.user_id = ? 
            AND pi.is_active = 1
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY p.id
            HAVING COUNT(pm.id) >= 3
            ORDER BY avg_score DESC, avg_memory ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get poorly performing plugins
     */
    public function getPoorPerformingPlugins($userId, $limit = 5)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.name,
                p.slug,
                AVG(pm.performance_score) as avg_score,
                AVG(pm.memory_usage_mb) as avg_memory,
                AVG(pm.page_load_time_ms) as avg_load_time,
                COUNT(DISTINCT pi.site_id) as site_count,
                pm.impact_score
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            JOIN performance_metrics pm ON pi.id = pm.installation_id
            WHERE ws.user_id = ? 
            AND pi.is_active = 1
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY p.id
            HAVING COUNT(pm.id) >= 3
            ORDER BY avg_score ASC, avg_memory DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get site performance summary
     */
    public function getSitePerformanceSummary($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ws.id,
                ws.site_name,
                ws.site_url,
                COUNT(DISTINCT pi.id) as plugin_count,
                AVG(pm.performance_score) as avg_performance,
                AVG(pm.memory_usage_mb) as avg_memory,
                AVG(pm.page_load_time_ms) as avg_load_time,
                MAX(pm.metric_date) as last_measurement,
                CASE 
                    WHEN AVG(pm.performance_score) >= 3.5 THEN 'excellent'
                    WHEN AVG(pm.performance_score) >= 2.5 THEN 'good'
                    WHEN AVG(pm.performance_score) >= 1.5 THEN 'fair'
                    ELSE 'poor'
                END as performance_grade
            FROM wordpress_sites ws
            LEFT JOIN plugin_installations pi ON ws.id = pi.site_id AND pi.is_active = 1
            LEFT JOIN performance_metrics pm ON pi.id = pm.installation_id 
                AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE ws.user_id = ?
            GROUP BY ws.id
            ORDER BY avg_performance DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get performance chart data
     */
    public function getPerformanceChartData($userId, $days = 7, $siteId = 0)
    {
        $siteCondition = $siteId > 0 ? "AND ws.id = ?" : "";
        $params = [$userId];
        if ($siteId > 0) {
            $params[] = $siteId;
        }
        $params[] = $days;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(pm.metric_date) as date,
                AVG(pm.performance_score) as avg_performance,
                AVG(pm.memory_usage_mb) as avg_memory,
                AVG(pm.page_load_time_ms) as avg_load_time,
                COUNT(pm.id) as measurement_count
            FROM performance_metrics pm
            JOIN plugin_installations pi ON pm.installation_id = pi.id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ? {$siteCondition}
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(pm.metric_date)
            ORDER BY date ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Analyze plugin impact on site performance
     */
    public function analyzePluginImpact($siteId, $pluginId)
    {
        // Get performance data before and after plugin activation
        $stmt = $this->pdo->prepare("
            SELECT 
                pi.installation_date,
                pi.deactivation_date,
                AVG(CASE WHEN pm.metric_date < pi.installation_date THEN pm.page_load_time_ms END) as before_load_time,
                AVG(CASE WHEN pm.metric_date >= pi.installation_date AND pi.deactivation_date IS NULL THEN pm.page_load_time_ms END) as after_load_time,
                AVG(CASE WHEN pm.metric_date < pi.installation_date THEN pm.memory_usage_mb END) as before_memory,
                AVG(CASE WHEN pm.metric_date >= pi.installation_date AND pi.deactivation_date IS NULL THEN pm.memory_usage_mb END) as after_memory
            FROM plugin_installations pi
            LEFT JOIN performance_metrics pm ON pi.id = pm.installation_id
            WHERE pi.site_id = ? AND pi.plugin_id = ?
            GROUP BY pi.id
        ");
        $stmt->execute([$siteId, $pluginId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return null;
        }
        
        $impact = [
            'load_time_impact' => $result['after_load_time'] - $result['before_load_time'],
            'memory_impact' => $result['after_memory'] - $result['before_memory'],
            'impact_percentage' => 0
        ];
        
        if ($result['before_load_time'] > 0) {
            $impact['impact_percentage'] = (($result['after_load_time'] - $result['before_load_time']) / $result['before_load_time']) * 100;
        }
        
        return $impact;
    }
    
    /**
     * Generate performance recommendations
     */
    public function generateRecommendations($userId)
    {
        $recommendations = [];
        
        // Check for slow-loading plugins
        $slowPlugins = $this->getSlowPlugins($userId);
        if (!empty($slowPlugins)) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'title' => 'Optimize Slow-Loading Plugins',
                'description' => 'Some plugins are significantly impacting page load times.',
                'plugins' => $slowPlugins,
                'action' => 'Consider replacing or optimizing these plugins'
            ];
        }
        
        // Check for memory-heavy plugins
        $memoryHeavyPlugins = $this->getMemoryHeavyPlugins($userId);
        if (!empty($memoryHeavyPlugins)) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'title' => 'High Memory Usage Detected',
                'description' => 'Some plugins are using excessive memory.',
                'plugins' => $memoryHeavyPlugins,
                'action' => 'Monitor memory usage and consider alternatives'
            ];
        }
        
        // Check for outdated plugins
        $outdatedPlugins = $this->getOutdatedPlugins($userId);
        if (!empty($outdatedPlugins)) {
            $recommendations[] = [
                'type' => 'updates',
                'priority' => 'medium',
                'title' => 'Plugin Updates Available',
                'description' => 'Several plugins have updates available that may improve performance.',
                'plugins' => $outdatedPlugins,
                'action' => 'Update plugins to their latest versions'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get slow-loading plugins
     */
    private function getSlowPlugins($userId, $threshold = 2000) // 2 seconds
    {
        $stmt = $this->pdo->prepare("
            SELECT p.name, p.slug, AVG(pm.page_load_time_ms) as avg_load_time
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            JOIN performance_metrics pm ON pi.id = pm.installation_id
            WHERE ws.user_id = ? 
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY p.id
            HAVING avg_load_time > ?
            ORDER BY avg_load_time DESC
        ");
        $stmt->execute([$userId, $threshold]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get memory-heavy plugins
     */
    private function getMemoryHeavyPlugins($userId, $threshold = 50) // 50MB
    {
        $stmt = $this->pdo->prepare("
            SELECT p.name, p.slug, AVG(pm.memory_usage_mb) as avg_memory
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            JOIN performance_metrics pm ON pi.id = pm.installation_id
            WHERE ws.user_id = ? 
            AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY p.id
            HAVING avg_memory > ?
            ORDER BY avg_memory DESC
        ");
        $stmt->execute([$userId, $threshold]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get outdated plugins
     */
    private function getOutdatedPlugins($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT p.name, p.slug, p.version as latest_version, pi.installed_version
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ? 
            AND pi.is_active = 1
            AND pi.installed_version < p.version
            ORDER BY p.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate performance score based on metrics
     */
    public function calculatePerformanceScore($loadTime, $memoryUsage, $dbQueries, $errors = 0)
    {
        $score = 4.0; // Start with perfect score
        
        // Deduct points for load time (penalty starts at 1 second)
        if ($loadTime > 1000) {
            $score -= min(2.0, ($loadTime - 1000) / 2000); // Max 2 points penalty
        }
        
        // Deduct points for memory usage (penalty starts at 32MB)
        if ($memoryUsage > 32) {
            $score -= min(1.0, ($memoryUsage - 32) / 64); // Max 1 point penalty
        }
        
        // Deduct points for database queries (penalty starts at 10 queries)
        if ($dbQueries > 10) {
            $score -= min(0.5, ($dbQueries - 10) / 20); // Max 0.5 points penalty
        }
        
        // Deduct points for errors
        $score -= min(0.5, $errors * 0.1); // Max 0.5 points penalty
        
        return max(0, round($score, 1));
    }
}
