<?php
// =======================================================
// Database Connection Configuration (PDO)
// =======================================================
// InfinityFree users: copy database.local.example.php to database.local.php
// and enter the MySQL credentials shown in the InfinityFree Control Panel.
// Environment variables (when available) take precedence over the local file.

$local_config_file = __DIR__ . '/database.local.php';
$local_config = is_file($local_config_file) ? require $local_config_file : [];
if (!is_array($local_config)) {
    $local_config = [];
}

$db_config = array_merge([
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'enrollment_db',
    'user' => 'root',
    'pass' => '',
], $local_config);

$db_config['host'] = getenv('DB_HOST') !== false ? getenv('DB_HOST') : $db_config['host'];
$db_config['port'] = getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : (int) $db_config['port'];
$db_config['name'] = getenv('DB_NAME') !== false ? getenv('DB_NAME') : $db_config['name'];
$db_config['user'] = getenv('DB_USER') !== false ? getenv('DB_USER') : $db_config['user'];
$db_config['pass'] = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $db_config['pass'];

if (!defined('DB_HOST')) define('DB_HOST', (string) $db_config['host']);
if (!defined('DB_PORT')) define('DB_PORT', (int) $db_config['port']);
if (!defined('DB_NAME')) define('DB_NAME', (string) $db_config['name']);
if (!defined('DB_USER')) define('DB_USER', (string) $db_config['user']);
if (!defined('DB_PASS')) define('DB_PASS', (string) $db_config['pass']);

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Native prepares are supported by InfinityFree MySQL servers.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            $message = 'Database connection failed. Check config/database.local.php or your DB_* environment variables.';
            if (PHP_SAPI === 'cli' || (defined('APP_DEBUG') && APP_DEBUG)) {
                $message .= ' ' . $e->getMessage();
            }
            die('<strong>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</strong>');
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
