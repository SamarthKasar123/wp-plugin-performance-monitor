<?php
/**
 * WordPress Sites Configuration
 * 
 * Configuration for WordPress REST API connections
 * Copy this file to wordpress-sites.php and update with your actual WordPress site details
 */

return [
    'sites' => [
        [
            'name' => 'Demo News Site',
            'url' => 'https://demo-news.local',
            'api_endpoint' => 'https://demo-news.local/wp-json/wp/v2/',
            'api_key' => 'your_api_key_here',
            'api_secret' => 'your_api_secret_here',
            'enabled' => true
        ],
        [
            'name' => 'Demo E-commerce',
            'url' => 'https://demo-shop.local',
            'api_endpoint' => 'https://demo-shop.local/wp-json/wp/v2/',
            'api_key' => 'your_api_key_here',
            'api_secret' => 'your_api_secret_here',
            'enabled' => true
        ]
    ],
    
    'api_settings' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'rate_limit' => 100, // requests per minute
        'user_agent' => 'WP-Plugin-Performance-Monitor/1.0'
    ],
    
    'security' => [
        'verify_ssl' => true,
        'allowed_hosts' => [
            'localhost',
            '*.local',
            '*.dev'
        ]
    ]
];
