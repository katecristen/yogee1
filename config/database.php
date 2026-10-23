<?php
require_once __DIR__ . '/env.php';
class Database {
    private $conn;
    public function getConnection() {
        if ($this->conn) return $this->conn;
        if (!extension_loaded('pdo_pgsql')) throw new RuntimeException('Enable PHP pdo_pgsql or start with npm start.');
        $url = parse_url(requiredEnv('DATABASE_URL'));
        if (!$url || !isset($url['host'], $url['user'], $url['pass'], $url['path'])) throw new RuntimeException('Invalid DATABASE_URL');
        $dsn = 'pgsql:host=' . $url['host'] . ';port=' . ($url['port'] ?? 5432) . ';dbname=' . rawurldecode(ltrim($url['path'], '/')) . ';sslmode=verify-full';
        $ca = getenv('PGSSLROOTCERT') ?: ini_get('openssl.cafile');
        if ($ca && is_file($ca)) $dsn .= ';sslrootcert=' . str_replace('\\', '/', $ca);
        // XAMPP's older libpq needs explicit Neon routing because it lacks SNI.
        $endpoint = str_replace('-pooler', '', explode('.', $url['host'])[0]);
        $dsn .= ';options=endpoint=' . $endpoint;
        $this->conn = new PDO($dsn, rawurldecode($url['user']), rawurldecode($url['pass']), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        return $this->conn;
    }
    public function closeConnection() { $this->conn = null; }
}
function getDB() { static $database; $database = $database ?? new Database(); return $database->getConnection(); }
function checkDatabaseSetup() { return (bool)getDB()->query("SELECT to_regclass('public.users')")->fetchColumn(); }
