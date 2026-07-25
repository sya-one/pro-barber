<?php
/**
 * Database Initialization Script
 * Run this script to create the database from scratch
 * For cPanel: Access via web or CLI
 */

// Database configuration - update for your environment
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'professional_barbershop';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create database if not exists
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "Database created: $db_name\n";
} catch (PDOException $e) {
    die("Database creation failed: " . $e->getMessage());
}

// Select database
$pdo->exec("USE `$db_name`");

// Read and execute schema file
$schema_file = __DIR__ . '/sql/professional_barbershop.sql';
if (!file_exists($schema_file)) {
    die("Schema file not found: $schema_file");
}

$sql = file_get_contents($schema_file);
$queries = explode(";", $sql);

foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query) && !preg_match('/^\/\*/', $query)) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }
}

echo "Database schema created successfully!\n\n";

// Run migrations
echo "Running migrations...\n";
include __DIR__ . '/migrate.php';