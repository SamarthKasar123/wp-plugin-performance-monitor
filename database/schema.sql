-- WordPress Plugin Performance Monitor Database Schema
-- Optimized for enterprise-scale WordPress monitoring

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS performance_metrics;
DROP TABLE IF EXISTS security_scans;
DROP TABLE IF EXISTS plugin_installations;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS wordpress_sites;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS alerts;
SET FOREIGN_KEY_CHECKS = 1;

-- Users table for multi-tenant access
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'developer', 'client') DEFAULT 'developer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- WordPress sites being monitored
CREATE TABLE wordpress_sites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    site_name VARCHAR(100) NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    api_endpoint VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    api_secret VARCHAR(255) NOT NULL,
    wp_version VARCHAR(20),
    php_version VARCHAR(20),
    theme_name VARCHAR(100),
    is_multisite BOOLEAN DEFAULT FALSE,
    last_scan TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_last_scan (last_scan)
);

-- WordPress plugins catalog
CREATE TABLE plugins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    author VARCHAR(100),
    version VARCHAR(20),
    wp_org_plugin BOOLEAN DEFAULT FALSE,
    plugin_uri VARCHAR(255),
    author_uri VARCHAR(255),
    requires_wp VARCHAR(20),
    tested_wp VARCHAR(20),
    requires_php VARCHAR(20),
    rating DECIMAL(3,2),
    active_installs INT,
    last_updated DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_slug (slug),
    INDEX idx_wp_org (wp_org_plugin),
    INDEX idx_rating (rating)
);

-- Plugin installations on specific sites
CREATE TABLE plugin_installations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    site_id INT NOT NULL,
    plugin_id INT NOT NULL,
    installed_version VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    is_auto_update BOOLEAN DEFAULT FALSE,
    installation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deactivation_date TIMESTAMP NULL,
    
    FOREIGN KEY (site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
    FOREIGN KEY (plugin_id) REFERENCES plugins(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_plugin (site_id, plugin_id),
    INDEX idx_site_id (site_id),
    INDEX idx_plugin_id (plugin_id),
    INDEX idx_active (is_active)
);

-- Performance metrics for plugins
CREATE TABLE performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    installation_id INT NOT NULL,
    metric_date DATE NOT NULL,
    page_load_time_ms INT,
    memory_usage_mb DECIMAL(8,2),
    database_queries INT,
    database_time_ms INT,
    php_errors INT DEFAULT 0,
    javascript_errors INT DEFAULT 0,
    performance_score DECIMAL(3,1), -- A-F scale converted to 4.0-0.0
    impact_score ENUM('low', 'medium', 'high', 'critical'),
    measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (installation_id) REFERENCES plugin_installations(id) ON DELETE CASCADE,
    INDEX idx_installation_id (installation_id),
    INDEX idx_metric_date (metric_date),
    INDEX idx_performance_score (performance_score),
    INDEX idx_impact_score (impact_score)
);

-- Security vulnerability scans
CREATE TABLE security_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    installation_id INT NOT NULL,
    vulnerability_id VARCHAR(50), -- CVE or WordPress vulnerability ID
    severity ENUM('low', 'medium', 'high', 'critical'),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    affected_versions TEXT,
    fixed_version VARCHAR(20),
    vuln_type ENUM('sql_injection', 'xss', 'csrf', 'auth_bypass', 'file_inclusion', 'other'),
    scan_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'acknowledged', 'fixed', 'false_positive') DEFAULT 'new',
    remediation_notes TEXT,
    
    FOREIGN KEY (installation_id) REFERENCES plugin_installations(id) ON DELETE CASCADE,
    INDEX idx_installation_id (installation_id),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_scan_date (scan_date)
);

-- Generated reports
CREATE TABLE reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    site_id INT,
    report_type ENUM('performance', 'security', 'comprehensive', 'custom'),
    title VARCHAR(150) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    format ENUM('html', 'pdf', 'json', 'csv') DEFAULT 'html',
    file_path VARCHAR(255),
    status ENUM('generating', 'completed', 'failed') DEFAULT 'generating',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_site_id (site_id),
    INDEX idx_report_type (report_type),
    INDEX idx_status (status)
);

-- Alert system
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    site_id INT NOT NULL,
    alert_type ENUM('performance', 'security', 'compatibility', 'update') NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    plugin_slug VARCHAR(100),
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    is_email_sent BOOLEAN DEFAULT FALSE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES wordpress_sites(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_site_id (site_id),
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_triggered_at (triggered_at),
    INDEX idx_read_at (read_at)
);

-- Insert sample data for demonstration

-- Sample users
INSERT INTO users (username, email, password_hash, role) VALUES
('admin', 'admin@rtcamp-demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('dev_manager', 'dev@rtcamp-demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager'),
('client_user', 'client@rtcamp-demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');

-- Sample WordPress sites
INSERT INTO wordpress_sites (user_id, site_name, site_url, api_endpoint, api_key, api_secret, wp_version, php_version, theme_name) VALUES
(1, 'Enterprise News Site', 'https://news.example.com', 'https://news.example.com/wp-json/wp/v2/', 'demo_key_123', 'demo_secret_456', '6.4.2', '8.1', 'Custom News Theme'),
(1, 'E-commerce Platform', 'https://shop.example.com', 'https://shop.example.com/wp-json/wp/v2/', 'demo_key_789', 'demo_secret_012', '6.4.1', '8.2', 'WooCommerce Storefront'),
(2, 'Corporate Website', 'https://corporate.example.com', 'https://corporate.example.com/wp-json/wp/v2/', 'demo_key_345', 'demo_secret_678', '6.4.2', '8.1', 'Business Pro Theme');

-- Sample popular plugins
INSERT INTO plugins (slug, name, description, author, version, wp_org_plugin, requires_wp, requires_php, rating, active_installs) VALUES
('yoast-seo', 'Yoast SEO', 'The #1 WordPress SEO plugin', 'Team Yoast', '21.8', TRUE, '6.2', '7.4', 4.6, 5000000),
('woocommerce', 'WooCommerce', 'An eCommerce toolkit', 'Automattic', '8.4.0', TRUE, '6.2', '7.4', 4.4, 5000000),
('elementor', 'Elementor', 'Page Builder', 'Elementor.com', '3.18.0', TRUE, '6.0', '7.4', 4.7, 5000000),
('wp-rocket', 'WP Rocket', 'Caching Plugin', 'WP Media', '3.15.0', FALSE, '5.3', '7.3', 4.8, 1000000),
('gravity-forms', 'Gravity Forms', 'Advanced Forms', 'Gravity Forms', '2.7.18', FALSE, '5.0', '7.4', 4.9, 1000000);

-- Sample plugin installations
INSERT INTO plugin_installations (site_id, plugin_id, installed_version, is_active) VALUES
(1, 1, '21.8', TRUE),
(1, 4, '3.15.0', TRUE),
(1, 5, '2.7.18', TRUE),
(2, 1, '21.7', TRUE),
(2, 2, '8.4.0', TRUE),
(2, 3, '3.18.0', TRUE),
(3, 1, '21.8', TRUE),
(3, 3, '3.17.0', TRUE);

-- Sample performance metrics
INSERT INTO performance_metrics (installation_id, metric_date, page_load_time_ms, memory_usage_mb, database_queries, database_time_ms, performance_score, impact_score) VALUES
(1, CURDATE() - INTERVAL 1 DAY, 1200, 45.2, 12, 150, 3.8, 'low'),
(1, CURDATE(), 1180, 44.8, 11, 140, 4.0, 'low'),
(2, CURDATE() - INTERVAL 1 DAY, 2100, 78.5, 45, 680, 2.1, 'high'),
(2, CURDATE(), 1950, 72.3, 42, 620, 2.4, 'medium'),
(4, CURDATE() - INTERVAL 1 DAY, 1150, 42.1, 8, 120, 4.2, 'low'),
(4, CURDATE(), 1100, 41.5, 8, 115, 4.3, 'low');

-- Sample security scans
INSERT INTO security_scans (installation_id, vulnerability_id, severity, title, description, affected_versions, fixed_version, vuln_type, status) VALUES
(3, 'CVE-2023-12345', 'medium', 'SQL Injection in Contact Form', 'Potential SQL injection vulnerability in contact form processing', '< 2.7.18', '2.7.18', 'sql_injection', 'fixed'),
(6, 'WP-2024-001', 'high', 'XSS Vulnerability in Admin Panel', 'Cross-site scripting vulnerability in admin dashboard', '< 3.18.0', '3.18.0', 'xss', 'new');

-- Sample alerts
INSERT INTO alerts (user_id, site_id, alert_type, severity, title, message, plugin_slug) VALUES
(1, 1, 'performance', 'warning', 'WP Rocket Cache Miss Rate High', 'Cache miss rate has increased to 35% over the last 24 hours', 'wp-rocket'),
(1, 2, 'security', 'critical', 'Elementor Security Update Available', 'Critical security update available for Elementor. Please update immediately.', 'elementor'),
(2, 3, 'performance', 'info', 'Yoast SEO Performance Optimized', 'Recent optimization reduced database queries by 15%', 'yoast-seo');
