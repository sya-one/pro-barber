<?php
class Database {
    private $host = "localhost";
    private $db_name = "professional_barbershop";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        
        // XAMPP/cPanel compatible connection attempts
        $connections = [
            "mysql:host=localhost;dbname={$this->db_name};charset=utf8mb4",
            "mysql:host=127.0.0.1;dbname={$this->db_name};charset=utf8mb4",
            "mysql:host=localhost;dbname={$this->db_name};charset=utf8mb4;unix_socket=/tmp/mysql.sock",
            "mysql:host=/var/run/mysqld/mysqld.sock;dbname={$this->db_name};charset=utf8mb4",
        ];
        
        foreach ($connections as $dsn) {
            try {
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                return $this->conn;
            } catch(PDOException $exception) {
                // Try next connection method
                continue;
            }
        }
        
        // Fallback: try with port 3306
        try {
            $this->conn = new PDO("mysql:host=localhost;port=3306;dbname={$this->db_name};charset=utf8mb4", 
                $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            return $this->conn;
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
            return null;
        }
    }
}