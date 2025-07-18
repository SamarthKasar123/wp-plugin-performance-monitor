<?php
/**
 * WordPress Site Model
 * 
 * Handles database operations for WordPress sites
 */

class WordPressSite
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all sites for a user
     */
    public function getByUser($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_sites 
            WHERE user_id = ? 
            ORDER BY site_name ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get site by ID
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wordpress_sites WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Count sites for a user
     */
    public function countByUser($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM wordpress_sites 
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Create new site
     */
    public function create($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wordpress_sites 
            (user_id, site_name, site_url, api_endpoint, api_key, api_secret, wp_version, php_version, theme_name, is_multisite) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['user_id'],
            $data['site_name'],
            $data['site_url'],
            $data['api_endpoint'],
            $data['api_key'],
            $data['api_secret'],
            $data['wp_version'] ?? null,
            $data['php_version'] ?? null,
            $data['theme_name'] ?? null,
            $data['is_multisite'] ?? false
        ]);
    }
    
    /**
     * Update site
     */
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        $allowedFields = ['site_name', 'site_url', 'api_endpoint', 'api_key', 'api_secret', 
                         'wp_version', 'php_version', 'theme_name', 'is_multisite', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE wordpress_sites SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete site
     */
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM wordpress_sites WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Update last scan time
     */
    public function updateLastScan($id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE wordpress_sites 
            SET last_scan = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get sites needing scan
     */
    public function getSitesNeedingScan($interval = '1 HOUR')
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wordpress_sites 
            WHERE status = 'active' 
            AND (last_scan IS NULL OR last_scan < DATE_SUB(NOW(), INTERVAL {$interval}))
            ORDER BY last_scan ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get site health summary
     */
    public function getHealthSummary($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                ws.id,
                ws.site_name,
                ws.site_url,
                ws.status,
                ws.last_scan,
                COUNT(DISTINCT pi.id) as plugin_count,
                AVG(pm.performance_score) as avg_performance,
                COUNT(DISTINCT CASE WHEN a.severity IN ('high', 'critical') THEN a.id END) as critical_alerts
            FROM wordpress_sites ws
            LEFT JOIN plugin_installations pi ON ws.id = pi.site_id AND pi.is_active = 1
            LEFT JOIN performance_metrics pm ON pi.id = pm.installation_id AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN alerts a ON ws.id = a.site_id AND a.resolved_at IS NULL
            WHERE ws.user_id = ?
            GROUP BY ws.id
            ORDER BY ws.site_name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Validate site credentials
     */
    public function validateCredentials($siteData)
    {
        $apiEndpoint = $siteData['api_endpoint'];
        $apiKey = $siteData['api_key'];
        
        // Basic URL validation
        if (!filter_var($siteData['site_url'], FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Invalid site URL'];
        }
        
        // Test API connection
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Authorization: Bearer ' . $apiKey,
                        'User-Agent: WP-Plugin-Performance-Monitor/1.0'
                    ],
                    'timeout' => 10
                ]
            ]);
            
            $response = file_get_contents($apiEndpoint, false, $context);
            
            if ($response === false) {
                return ['valid' => false, 'error' => 'Could not connect to WordPress API'];
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['valid' => false, 'error' => 'Invalid API response format'];
            }
            
            return ['valid' => true, 'wp_version' => $data['gmt_offset'] ?? 'Unknown'];
            
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'API connection failed: ' . $e->getMessage()];
        }
    }
}
