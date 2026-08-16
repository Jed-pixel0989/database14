<?php
// =======================================================
// Database Connection Configuration (PDO)
// =======================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'enrollment_db');
define('DB_PORT', 3306);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if database does not exist
            if ($e->getCode() == 1049) {
                header("Location: " . (defined('BASE_URL') ? BASE_URL : '/database14/') . "setup.php?auto=1");
                exit;
            }
            die("<strong>Database Connection Failed:</strong> " . htmlspecialchars($e->getMessage()) . "<br><a href='/database14/setup.php'>Click here to run the Auto-Installer Setup</a>");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}

function getDB() {
    return Database::getInstance()->getConnection();
}
