<?php

class Database
{
    // private $host = "srv1752.hstgr.io";
    // private $db_name = "u229215627_goldenDreamSQL";
    // private $username = "u229215627_GoldenDreamSQL";
    // private $password = "Azl@n2002";
    // public $conn;

    public $host = "localhost";
    public $db_name = "u232955123_mp_goldenDream";
    public $username = "u232955123_mp_goldenDream";
    public $password = "Brandweave@25";
    public $conn;

    // Base URL configuration
    public static $baseUrl = "https://mp.goldendream.in/";

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            $baseUrl = "https://mp.goldendream.in/";

           header("Location: " . $baseUrl . "noInternet/");
        }

        return $this->conn;
    }
}

?>
<link rel="icon" type="image/png" href="https://goldendream.in/landing/landing_assets/images/gdLogo.png">
