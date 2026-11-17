<?php
namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    /**
     * Get Database instance
     */
    public static function getInstance(): self {
        return new self();
    }

    /**
     * Get Singleton PDO instance
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $config = require ROOT_PATH . '/config/database.php';
            
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            try {
                self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            } catch (PDOException $e) {
                // Try connecting without dbname to create db if it does not exist
                try {
                    $dsnNoDb = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
                    $pdo = new PDO($dsnNoDb, $config['username'], $config['password']);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                    self::$instance = new PDO($dsn, $config['username'], $config['password'], $config['options']);
                } catch (PDOException $ex) {
                    die("Database connection failed: " . $ex->getMessage());
                }
            }
        }
        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __construct() {}
    private function __clone() {}
}
