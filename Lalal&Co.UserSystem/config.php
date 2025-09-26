<?php
$servername = "localhost";  // or "localhost"
$username = "root";         // your DB username
$password = "";             // your DB password
$dbname = "users_db";       // your database name

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error); // This will stop execution if DB connection fails
}
?>