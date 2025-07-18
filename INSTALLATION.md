# Installation Guide - WordPress Plugin Performance Monitor

This guide will help you set up the WordPress Plugin Performance Monitor on your local development environment.

## Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.0 or higher** with the following extensions:
  - PDO MySQL
  - cURL
  - JSON
  - OpenSSL
- **MySQL 8.0 or higher**
- **Web server** (Apache, Nginx, or PHP built-in server)
- **Composer** (optional, for advanced dependency management)

## Quick Start

### 1. Download and Setup

```bash
# Clone or download the project
git clone https://github.com/your-username/wp-plugin-performance-monitor.git
cd wp-plugin-performance-monitor

# Or if downloaded as ZIP
unzip wp-plugin-performance-monitor.zip
cd wp-plugin-performance-monitor
```

### 2. Database Setup

Create a new MySQL database:

```sql
CREATE DATABASE wp_plugin_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the database schema:

```bash
mysql -u your_username -p wp_plugin_monitor < database/schema.sql
```

### 3. Configuration

Copy the example configuration files:

```bash
# Database configuration
cp config/database.example.php config/database.php

# WordPress sites configuration  
cp config/wordpress-sites.example.php config/wordpress-sites.php
```

Edit `config/database.php` with your database credentials:

```php
return [
    'host' => 'localhost',
    'dbname' => 'wp_plugin_monitor',
    'username' => 'your_db_user',
    'password' => 'your_db_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
```

### 4. Start the Application

Using PHP built-in server (recommended for development):

```bash
cd public
php -S localhost:8000
```

Using Apache/Nginx: Point your virtual host document root to the `public` directory.

### 5. Access the Application

Open your browser and navigate to:
- **Local server**: http://localhost:8000
- **Apache/Nginx**: http://your-domain.com

Default login credentials (change immediately):
- **Username**: admin
- **Password**: password

## Detailed Configuration

### WordPress Site Integration

To monitor WordPress sites, you need to configure API access:

1. **On each WordPress site you want to monitor**:
   - Install the companion plugin (found in `/wordpress-plugin/` directory)
   - Generate API credentials in WordPress admin
   - Note down the REST API endpoint

2. **In the monitoring dashboard**:
   - Go to Sites section
   - Add new site with API credentials
   - Test the connection

### Email Notifications (Optional)

For email alerts, configure SMTP settings in `config/email.php`:

```php
return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'from_email' => 'alerts@your-domain.com',
    'from_name' => 'WP Performance Monitor'
];
```

### Cron Jobs for Automated Monitoring

Set up cron jobs for continuous monitoring:

```bash
# Add to crontab (crontab -e)

# Run performance scans every 15 minutes
*/15 * * * * /usr/bin/php /path/to/project/scripts/scan-performance.php

# Run security scans daily at 2 AM
0 2 * * * /usr/bin/php /path/to/project/scripts/scan-security.php

# Generate daily reports at 6 AM
0 6 * * * /usr/bin/php /path/to/project/scripts/generate-reports.php
```

## Production Deployment

### Security Checklist

- [ ] Change default admin password
- [ ] Update database credentials
- [ ] Set `display_errors = Off` in PHP configuration
- [ ] Use HTTPS for all communications
- [ ] Configure proper file permissions (644 for files, 755 for directories)
- [ ] Set up SSL certificates
- [ ] Configure firewall rules

### Performance Optimizations

1. **Database Optimization**:
   - Enable MySQL query cache
   - Add appropriate indexes
   - Regular database cleanup

2. **Caching**:
   - Enable OpCache for PHP
   - Implement Redis/Memcached for session storage
   - Use CDN for static assets

3. **Web Server Configuration**:
   - Enable Gzip compression
   - Set appropriate cache headers
   - Configure rate limiting

### Backup Strategy

Set up automated backups:

```bash
#!/bin/bash
# backup-script.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/wp-monitor"

# Database backup
mysqldump -u username -p wp_plugin_monitor > $BACKUP_DIR/db_$DATE.sql

# Files backup
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /path/to/project

# Cleanup old backups (keep last 30 days)
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

## Troubleshooting

### Common Issues

**Issue: "Database connection failed"**
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check firewall settings

**Issue: "WordPress API connection failed"**
- Verify WordPress site is accessible
- Check API endpoint URL format
- Ensure REST API is enabled in WordPress
- Verify API credentials

**Issue: "Permission denied errors"**
- Set correct file permissions:
  ```bash
  find . -type f -exec chmod 644 {} \;
  find . -type d -exec chmod 755 {} \;
  ```

**Issue: "Charts not loading"**
- Ensure Chart.js is loaded
- Check browser console for JavaScript errors
- Verify database contains performance data

### Debug Mode

Enable debug mode by adding to your configuration:

```php
// In config/database.php
define('DEBUG_MODE', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Log Files

Check application logs:
- PHP error log: Usually in `/var/log/php/error.log`
- Web server logs: Apache (`/var/log/apache2/`) or Nginx (`/var/log/nginx/`)
- Application logs: `logs/application.log` (if logging is configured)

## Support

For support and questions:

1. Check the troubleshooting section above
2. Review the project documentation
3. Check existing GitHub issues
4. Create a new issue with detailed information

## Next Steps

After successful installation:

1. **Add WordPress Sites**: Configure your WordPress sites for monitoring
2. **Set Up Alerts**: Configure notification preferences
3. **Customize Dashboard**: Adjust widgets and time ranges
4. **Schedule Reports**: Set up automated performance reports
5. **Monitor Security**: Enable security scanning for plugins

---

**Note**: This project is designed to demonstrate advanced PHP development skills for the rtCamp application. It showcases enterprise-level WordPress monitoring capabilities that would be valuable for managing high-traffic WordPress installations.
