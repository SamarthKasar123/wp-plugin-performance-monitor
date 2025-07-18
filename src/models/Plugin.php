<?php
/**
 * Plugin Model
 * 
 * Handles database operations for WordPress plugins
 */

class Plugin
{
    private $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * Get plugin by slug
     */
    public function getBySlug($slug)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM plugins WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
    /**
     * Get plugin by ID
     */
    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM plugins WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Count active plugins for user
     */
    public function countActiveByUser($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT p.id)
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ? AND pi.is_active = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get plugins for a site
     */
    public function getBySite($siteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pi.installed_version, pi.is_active, pi.installation_date
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            WHERE pi.site_id = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get active plugins for a site
     */
    public function getActiveBySite($siteId)
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pi.installed_version, pi.installation_date
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            WHERE pi.site_id = ? AND pi.is_active = 1
            ORDER BY p.name ASC
        ");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create or update plugin
     */
    public function createOrUpdate($pluginData)
    {
        $existing = $this->getBySlug($pluginData['slug']);
        
        if ($existing) {
            return $this->update($existing['id'], $pluginData);
        } else {
            return $this->create($pluginData);
        }
    }
    
    /**
     * Create new plugin
     */
    public function create($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO plugins 
            (slug, name, description, author, version, wp_org_plugin, plugin_uri, author_uri, 
             requires_wp, tested_wp, requires_php, rating, active_installs) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $data['slug'],
            $data['name'],
            $data['description'] ?? null,
            $data['author'] ?? null,
            $data['version'] ?? null,
            $data['wp_org_plugin'] ?? false,
            $data['plugin_uri'] ?? null,
            $data['author_uri'] ?? null,
            $data['requires_wp'] ?? null,
            $data['tested_wp'] ?? null,
            $data['requires_php'] ?? null,
            $data['rating'] ?? null,
            $data['active_installs'] ?? null
        ]);
        
        return $success ? $this->pdo->lastInsertId() : false;
    }
    
    /**
     * Update plugin
     */
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        $allowedFields = ['name', 'description', 'author', 'version', 'wp_org_plugin', 
                         'plugin_uri', 'author_uri', 'requires_wp', 'tested_wp', 
                         'requires_php', 'rating', 'active_installs'];
        
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
        $sql = "UPDATE plugins SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Get popular plugins
     */
    public function getPopular($limit = 10)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM plugins 
            WHERE wp_org_plugin = 1 
            ORDER BY active_installs DESC, rating DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Search plugins
     */
    public function search($query, $limit = 20)
    {
        $searchTerm = "%{$query}%";
        $stmt = $this->pdo->prepare("
            SELECT * FROM plugins 
            WHERE name LIKE ? OR description LIKE ? OR author LIKE ?
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 1
                    WHEN description LIKE ? THEN 2
                    ELSE 3
                END,
                rating DESC
            LIMIT ?
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get plugin performance summary
     */
    public function getPerformanceSummary($pluginId, $days = 30)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_installations,
                AVG(pm.performance_score) as avg_performance_score,
                AVG(pm.memory_usage_mb) as avg_memory_usage,
                AVG(pm.page_load_time_ms) as avg_load_time,
                AVG(pm.database_queries) as avg_db_queries
            FROM plugin_installations pi
            LEFT JOIN performance_metrics pm ON pi.id = pm.installation_id 
                AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE pi.plugin_id = ? AND pi.is_active = 1
        ");
        $stmt->execute([$days, $pluginId]);
        return $stmt->fetch();
    }
    
    /**
     * Get plugins with updates available
     */
    public function getWithUpdates($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, pi.installed_version, pi.site_id, ws.site_name,
                   CASE 
                       WHEN pi.installed_version < p.version THEN 1 
                       ELSE 0 
                   END as has_update
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            WHERE ws.user_id = ? AND pi.is_active = 1
            HAVING has_update = 1
            ORDER BY ws.site_name, p.name
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get plugin installation statistics
     */
    public function getInstallationStats($userId)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.name,
                p.slug,
                COUNT(DISTINCT pi.site_id) as site_count,
                AVG(pm.performance_score) as avg_performance,
                COUNT(DISTINCT CASE WHEN a.severity IN ('high', 'critical') THEN a.id END) as security_issues
            FROM plugins p
            JOIN plugin_installations pi ON p.id = pi.plugin_id
            JOIN wordpress_sites ws ON pi.site_id = ws.id
            LEFT JOIN performance_metrics pm ON pi.id = pm.installation_id 
                AND pm.metric_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN alerts a ON ws.id = a.site_id AND a.plugin_slug = p.slug AND a.resolved_at IS NULL
            WHERE ws.user_id = ? AND pi.is_active = 1
            GROUP BY p.id
            ORDER BY site_count DESC, avg_performance DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
