<?php
$host = "localhost";
$username = "root";
$password = "";  // default XAMPP password
$dbname = "budget_tracker";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
