<?php
// Database connection
$host = 'localhost';
$user = 'root';
$password = 'admin1234';
$database = 'hotel_magnament';

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>