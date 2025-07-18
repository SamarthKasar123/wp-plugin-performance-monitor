<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Plugin Performance Monitor - Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-tachometer-alt"></i> WP Monitor</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item active">
                    <a href="?page=dashboard" class="nav-link">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=sites" class="nav-link">
                        <i class="fas fa-globe"></i> Sites
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=plugins" class="nav-link">
                        <i class="fas fa-puzzle-piece"></i> Plugins
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=performance" class="nav-link">
                        <i class="fas fa-gauge-high"></i> Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=security" class="nav-link">
                        <i class="fas fa-shield-alt"></i> Security
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?page=reports" class="nav-link">
                        <i class="fas fa-file-alt"></i> Reports
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($user['username']) ?></span>
                </div>
                <a href="?page=auth&action=logout" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="main-header">
                <h1>Performance Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-secondary" onclick="exportData()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="alert-indicator" id="alertIndicator">
                        <i class="fas fa-bell"></i>
                        <span class="alert-count"><?= $stats['critical_alerts'] ?></span>
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon sites">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($stats['total_sites']) ?></h3>
                        <p>Active Sites</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon plugins">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($stats['total_plugins']) ?></h3>
                        <p>Active Plugins</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon alerts">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($stats['critical_alerts']) ?></h3>
                        <p>Critical Alerts</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon performance">
                        <i class="fas fa-gauge-high"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $stats['avg_performance_score'] ?>/4.0</h3>
                        <p>Avg Performance</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="dashboard-grid">
                <!-- Performance Chart -->
                <div class="widget">
                    <div class="widget-header">
                        <h3>Performance Trends</h3>
                        <div class="widget-controls">
                            <select id="chartTimeframe" onchange="updatePerformanceChart()">
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="widget-content">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <!-- Site Performance Summary -->
                <div class="widget">
                    <div class="widget-header">
                        <h3>Site Performance Overview</h3>
                    </div>
                    <div class="widget-content">
                        <div class="site-performance-list">
                            <?php foreach ($sitePerformance as $site): ?>
                            <div class="site-performance-item">
                                <div class="site-info">
                                    <h4><?= htmlspecialchars($site['site_name']) ?></h4>
                                    <span class="site-url"><?= htmlspecialchars($site['site_url']) ?></span>
                                </div>
                                <div class="performance-metrics">
                                    <div class="metric">
                                        <span class="metric-label">Score</span>
                                        <span class="metric-value performance-score-<?= $site['performance_grade'] ?>">
                                            <?= number_format($site['avg_performance'] ?? 0, 1) ?>/4.0
                                        </span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-label">Plugins</span>
                                        <span class="metric-value"><?= $site['plugin_count'] ?></span>
                                    </div>
                                    <div class="metric">
                                        <span class="metric-label">Load Time</span>
                                        <span class="metric-value"><?= number_format($site['avg_load_time'] ?? 0) ?>ms</span>
                                    </div>
                                </div>
                                <div class="performance-grade grade-<?= $site['performance_grade'] ?>">
                                    <?= strtoupper($site['performance_grade']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plugin Performance Section -->
            <div class="dashboard-grid">
                <!-- Top Performing Plugins -->
                <div class="widget">
                    <div class="widget-header">
                        <h3>Top Performing Plugins</h3>
                        <i class="fas fa-trophy widget-icon"></i>
                    </div>
                    <div class="widget-content">
                        <div class="plugin-list">
                            <?php foreach ($topPlugins as $plugin): ?>
                            <div class="plugin-item top-plugin">
                                <div class="plugin-info">
                                    <h4><?= htmlspecialchars($plugin['name']) ?></h4>
                                    <span class="plugin-sites"><?= $plugin['site_count'] ?> sites</span>
                                </div>
                                <div class="plugin-metrics">
                                    <span class="performance-badge excellent">
                                        <?= number_format($plugin['avg_score'], 1) ?>/4.0
                                    </span>
                                    <span class="memory-usage"><?= number_format($plugin['avg_memory'], 1) ?>MB</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Poor Performing Plugins -->
                <div class="widget">
                    <div class="widget-header">
                        <h3>Needs Attention</h3>
                        <i class="fas fa-exclamation-triangle widget-icon warning"></i>
                    </div>
                    <div class="widget-content">
                        <div class="plugin-list">
                            <?php foreach ($poorPlugins as $plugin): ?>
                            <div class="plugin-item poor-plugin">
                                <div class="plugin-info">
                                    <h4><?= htmlspecialchars($plugin['name']) ?></h4>
                                    <span class="plugin-sites"><?= $plugin['site_count'] ?> sites</span>
                                </div>
                                <div class="plugin-metrics">
                                    <span class="performance-badge poor">
                                        <?= number_format($plugin['avg_score'], 1) ?>/4.0
                                    </span>
                                    <span class="memory-usage high"><?= number_format($plugin['avg_memory'], 1) ?>MB</span>
                                </div>
                                <div class="impact-indicator impact-<?= $plugin['impact_score'] ?>">
                                    <?= ucfirst($plugin['impact_score']) ?> Impact
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="widget full-width">
                <div class="widget-header">
                    <h3>Recent Alerts</h3>
                    <button class="btn btn-sm btn-secondary" onclick="markAllRead()">
                        Mark All Read
                    </button>
                </div>
                <div class="widget-content">
                    <div class="alerts-list" id="alertsList">
                        <?php foreach ($recentAlerts as $alert): ?>
                        <div class="alert-item alert-<?= $alert['severity'] ?> <?= $alert['read_at'] ? 'read' : 'unread' ?>">
                            <div class="alert-icon">
                                <i class="fas fa-<?= $alert['alert_type'] === 'security' ? 'shield-alt' : ($alert['alert_type'] === 'performance' ? 'gauge-high' : 'info-circle') ?>"></i>
                            </div>
                            <div class="alert-content">
                                <h4><?= htmlspecialchars($alert['title']) ?></h4>
                                <p><?= htmlspecialchars($alert['message']) ?></p>
                                <div class="alert-meta">
                                    <span class="alert-site"><?= htmlspecialchars($alert['site_name']) ?></span>
                                    <span class="alert-time"><?= date('M j, Y H:i', strtotime($alert['triggered_at'])) ?></span>
                                </div>
                            </div>
                            <div class="alert-actions">
                                <button class="btn btn-sm btn-primary" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                    Resolve
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- CSRF Token for AJAX requests -->
    <script>
        window.csrfToken = '<?= $csrfToken ?>';
        window.dashboardData = {
            userId: <?= $user['id'] ?>,
            lastUpdate: '<?= date('Y-m-d H:i:s') ?>'
        };
    </script>
    
    <script src="js/dashboard.js"></script>
    <script src="js/charts.js"></script>
    <script src="js/alerts.js"></script>
</body>
</html>
