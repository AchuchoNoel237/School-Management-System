<?php
$host = 'localhost';
$dbname = 'ngahtech_institute';
$username = 'root'; // default for XAMPP/WAMP
$password = '';     // default for XAMPP/WAMP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>