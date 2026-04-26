<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        $hostSegment = 'host=' . DB_HOST;
        $portSegment = DB_PORT > 0 ? ';port=' . DB_PORT : '';
        $dsn = sprintf('mysql:%s%s;dbname=%s;charset=%s', $hostSegment, $portSegment, DB_NAME, DB_CHARSET);

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
