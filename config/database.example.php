<?php
/**
 * Database Configuration
 * 
 * This file contains database connection settings for the Task Management System.
 * Copy this file to database.php and update with your actual database credentials.
 */

return [
    'host' => 'localhost',
    'dbname' => 'rtcamp_tasks',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
