<?php
class Database {
    private $host = "localhost";
    private $db_name = "evointra_pro";
    private $username = "evointra_pro_user";
    private $password = "2ftl.mLxaZq.H4K8";
    public $conn;

   public function getConnection() {
    $this->conn = null;
    try {
        $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $exception) {
        echo "Connection error: " . $exception->getMessage();
    }
    return $this->conn;
    }
}