<?php
$servername = "localhost";
$username = "example";
$password = "example";
$dbname = "saw";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Erro na conexão: " . $e->getMessage();
    die();
}



// CREATE DATABASE IF NOT EXISTS saw;
//USE saw;
//
//CREATE TABLE IF NOT EXISTS utilizadores (
//    numero INT AUTO_INCREMENT PRIMARY KEY,
//    nome VARCHAR(100) NOT NULL,
//    login VARCHAR(50) NOT NULL UNIQUE,
//    password VARCHAR(255) NOT NULL,
//    nivel INT NOT NULL,
//    email VARCHAR(100) NOT NULL
//);

?>


