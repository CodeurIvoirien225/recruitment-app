<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // Charger les identifiants depuis mot_de_pass.php
        $config = include(__DIR__ . '/mot_de_pass.php');
        $this->host = $config['DB_HOST'];
        $this->db_name = $config['DB_NAME'];
        $this->username = $config['DB_USER'];
        $this->password = $config['DB_PASS'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            jsonResponse(false, "Erreur de connexion à la base de données");
        }
        return $this->conn;
    }
}
?>
