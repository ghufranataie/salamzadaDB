<?php


class Database {
    private $host = "ztechdb.cyb0o8u2oqp9.us-east-1.rds.amazonaws.com";
    private $user = "ghufranataie";
    private $pass = "DefaultGTRPassDBac1";
    private $dbName = "zaitoon";
    public $conn;

    public function getConnection(){
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->dbName,
                $this->user,
                $this->pass,
                [
                    PDO::ATTR_PERSISTENT => true,   // Enables persistent connection (connection pooling)
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch (\Throwable $th) {
            echo "Connection error: " . $th->getMessage();
        }

        return $this->conn;
    }
}

?>
