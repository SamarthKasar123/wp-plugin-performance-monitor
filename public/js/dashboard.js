/**
 * Dashboard JavaScript
 * Handles real-time updates, interactions, and AJAX calls
 */

class WPPluginDashboard {
    constructor() {
        this.lastAlertCheck = new Date();
        this.alertPollingInterval = 30000; // 30 seconds
        this.performanceChart = null;
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        // Initialize dashboard components
        this.initPerformanceChart();
        this.startAlertPolling();
        this.bindEventListeners();
        
        // Show welcome message
        this.showNotification('Dashboard loaded successfully', 'success');
    }
    
    bindEventListeners() {
        // Refresh button
        const refreshBtn = document.querySelector('[onclick="refreshDashboard()"]');
        if (refreshBtn) {
            refreshBtn.onclick = () => this.refreshDashboard();
        }
        
        // Export button
        const exportBtn = document.querySelector('[onclick="exportData()"]');
        if (exportBtn) {
            exportBtn.onclick = () => this.exportData();
        }
        
        // Chart timeframe selector
        const chartSelect = document.getElementById('chartTimeframe');
        if (chartSelect) {
            chartSelect.onchange = () => this.updatePerformanceChart();
        }
        
        // Alert actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('[onclick^="resolveAlert"]')) {
                const alertId = e.target.onclick.toString().match(/\d+/)[0];
                this.resolveAlert(alertId);
                e.preventDefault();
            }
        });
        
        // Mark all alerts as read
        const markAllReadBtn = document.querySelector('[onclick="markAllRead()"]');
        if (markAllReadBtn) {
            markAllReadBtn.onclick = () => this.markAllAlertsRead();
        }
    }
    
    async refreshDashboard() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading(true);
        
        try {
            // Refresh stats
            await this.updateStats();
            
            // Refresh charts
            await this.updatePerformanceChart();
            
            // Refresh alerts
            await this.checkForNewAlerts();
            
            this.showNotification('Dashboard refreshed successfully', 'success');
        } catch (error) {
            console.error('Dashboard refresh failed:', error);
            this.showNotification('Failed to refresh dashboard', 'error');
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }
    
    async updateStats() {
        try {
            const response = await fetch('?page=dashboard&action=getStats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${window.csrfToken}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update stat cards
                this.updateStatCards(data.stats);
            }
        } catch (error) {
            console.error('Failed to update stats:', error);
        }
    }
    
    updateStatCards(stats) {
        const statCards = document.querySelectorAll('.stat-card .stat-content h3');
        const statValues = [
            stats.total_sites,
            stats.total_plugins,
            stats.critical_alerts,
            `${stats.avg_performance_score}/4.0`
        ];
        
        statCards.forEach((card, index) => {
            if (statValues[index] !== undefined) {
                // Animate counter
                this.animateCounter(card, statValues[index]);
            }
        });
        
        // Update alert indicator
        const alertCount = document.querySelector('.alert-count');
        if (alertCount) {
            alertCount.textContent = stats.critical_alerts;
            
            // Add pulse animation if there are new critical alerts
            if (stats.critical_alerts > 0) {
                alertCount.parentElement.classList.add('pulse');
            }
        }
    }
    
    animateCounter(element, targetValue) {
        const isNumeric = !isNaN(targetValue);
        if (!isNumeric) {
            element.textContent = targetValue;
            return;
        }
        
        const currentValue = parseInt(element.textContent) || 0;
        const increment = (targetValue - currentValue) / 20;
        let current = currentValue;
        
        const timer = setInterval(() => {
            current += increment;
            if ((increment > 0 && current >= targetValue) || 
                (increment < 0 && current <= targetValue)) {
                element.textContent = targetValue;
                clearInterval(timer);
            } else {
                element.textContent = Math.round(current);
            }
        }, 50);
    }
    
    async updatePerformanceChart() {
        const timeframe = document.getElementById('chartTimeframe')?.value || 7;
        const siteId = document.getElementById('siteSelector')?.value || 0;
        
        try {
            const response = await fetch(
                `?page=dashboard&action=getPerformanceData&days=${timeframe}&site_id=${siteId}`
            );
            const data = await response.json();
            
            if (data.success) {
                this.renderPerformanceChart(data.data);
            }
        } catch (error) {
            console.error('Failed to update performance chart:', error);
        }
    }
    
    initPerformanceChart() {
        const canvas = document.getElementById('performanceChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        this.performanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Performance Score',
                    data: [],
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Memory Usage (MB)',
                    data: [],
                    borderColor: '#d63638',
                    backgroundColor: 'rgba(214, 54, 56, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 4,
                        title: {
                            display: true,
                            text: 'Performance Score'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Memory Usage (MB)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Load initial data
        this.updatePerformanceChart();
    }
    
    renderPerformanceChart(data) {
        if (!this.performanceChart || !data) return;
        
        const labels = data.map(item => {
            const date = new Date(item.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        
        const performanceData = data.map(item => parseFloat(item.avg_performance) || 0);
        const memoryData = data.map(item => parseFloat(item.avg_memory) || 0);
        
        this.performanceChart.data.labels = labels;
        this.performanceChart.data.datasets[0].data = performanceData;
        this.performanceChart.data.datasets[1].data = memoryData;
        
        this.performanceChart.update('active');
    }
    
    async checkForNewAlerts() {
        try {
            const response = await fetch(
                `?page=dashboard&action=getLiveAlerts&last_check=${this.lastAlertCheck.toISOString()}`
            );
            const data = await response.json();
            
            if (data.success && data.alerts.length > 0) {
                this.displayNewAlerts(data.alerts);
                this.lastAlertCheck = new Date();
            }
        } catch (error) {
            console.error('Failed to check for new alerts:', error);
        }
    }
    
    displayNewAlerts(alerts) {
        const alertsList = document.getElementById('alertsList');
        if (!alertsList) return;
        
        alerts.forEach(alert => {
            const alertElement = this.createAlertElement(alert);
            alertsList.insertBefore(alertElement, alertsList.firstChild);
            
            // Add animation
            alertElement.classList.add('fade-in');
            
            // Show notification for critical alerts
            if (alert.severity === 'critical') {
                this.showNotification(alert.title, 'error');
            }
        });
        
        // Update alert count
        const alertCount = document.querySelector('.alert-count');
        if (alertCount) {
            const currentCount = parseInt(alertCount.textContent) || 0;
            alertCount.textContent = currentCount + alerts.length;
        }
    }
    
    createAlertElement(alert) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-item alert-${alert.alert_type} alert-${alert.severity} unread`;
        
        const iconClass = alert.alert_type === 'security' ? 'shield-alt' : 
                         alert.alert_type === 'performance' ? 'gauge-high' : 'info-circle';
        
        alertDiv.innerHTML = `
            <div class="alert-icon">
                <i class="fas fa-${iconClass}"></i>
            </div>
            <div class="alert-content">
                <h4>${this.escapeHtml(alert.title)}</h4>
                <p>${this.escapeHtml(alert.message)}</p>
                <div class="alert-meta">
                    <span class="alert-site">${this.escapeHtml(alert.site_name)}</span>
                    <span class="alert-time">${this.formatDate(alert.triggered_at)}</span>
                </div>
            </div>
            <div class="alert-actions">
                <button class="btn btn-sm btn-primary" onclick="resolveAlert(${alert.id})">
                    Resolve
                </button>
            </div>
        `;
        
        return alertDiv;
    }
    
    async resolveAlert(alertId) {
        try {
            const response = await fetch('?page=dashboard&action=resolveAlert', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `alert_id=${alertId}&csrf_token=${window.csrfToken}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove alert from DOM
                const alertElement = document.querySelector(`[onclick="resolveAlert(${alertId})"]`).closest('.alert-item');
                if (alertElement) {
                    alertElement.style.opacity = '0';
                    setTimeout(() => alertElement.remove(), 300);
                }
                
                this.showNotification('Alert resolved successfully', 'success');
            } else {
                this.showNotification('Failed to resolve alert', 'error');
            }
        } catch (error) {
            console.error('Failed to resolve alert:', error);
            this.showNotification('Failed to resolve alert', 'error');
        }
    }
    
    async markAllAlertsRead() {
        try {
            const response = await fetch('?page=dashboard&action=markAllRead', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `csrf_token=${window.csrfToken}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Mark all alerts as read in DOM
                document.querySelectorAll('.alert-item.unread').forEach(alert => {
                    alert.classList.remove('unread');
                });
                
                this.showNotification('All alerts marked as read', 'success');
            }
        } catch (error) {
            console.error('Failed to mark alerts as read:', error);
        }
    }
    
    async exportData() {
        const format = 'csv'; // Could be made configurable
        
        try {
            const response = await fetch(`?page=dashboard&action=exportData&format=${format}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `wp-plugin-performance-${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showNotification('Export completed successfully', 'success');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Export failed:', error);
            this.showNotification('Export failed', 'error');
        }
    }
    
    startAlertPolling() {
        setInterval(() => {
            this.checkForNewAlerts();
        }, this.alertPollingInterval);
    }
    
    showLoading(show) {
        const mainContent = document.querySelector('.main-content');
        if (show) {
            mainContent.classList.add('loading');
        } else {
            mainContent.classList.remove('loading');
        }
    }
    
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
            <span>${this.escapeHtml(message)}</span>
            <button class="notification-close">&times;</button>
        `;
        
        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            max-width: 400px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;
        
        // Set background color based on type
        const colors = {
            success: '#00a32a',
            error: '#d63638',
            warning: '#dba617',
            info: '#2271b1'
        };
        notification.style.backgroundColor = colors[type] || colors.info;
        
        // Add to DOM
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.onclick = () => this.removeNotification(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            this.removeNotification(notification);
        }, 5000);
    }
    
    removeNotification(notification) {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

// Global functions for inline event handlers (for backward compatibility)
window.refreshDashboard = () => dashboard.refreshDashboard();
window.exportData = () => dashboard.exportData();
window.updatePerformanceChart = () => dashboard.updatePerformanceChart();
window.resolveAlert = (id) => dashboard.resolveAlert(id);
window.markAllRead = () => dashboard.markAllAlertsRead();

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.dashboard = new WPPluginDashboard();
});
