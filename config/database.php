<?php
require_once __DIR__ . '/config.php';

class Database {
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
                throw new RuntimeException('Database configuration is incomplete.');
            }

            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch(Throwable $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Check the database settings and server error log.');
        }
        return $this->conn;
    }
}
