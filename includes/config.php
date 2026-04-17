<?php
// Database connection settings
$servername = "localhost";   // keep as localhost for local testing
$username   = "root";        // default XAMPP/WAMP username
$password   = "";            // default XAMPP/WAMP password is empty
$dbname     = "pweb_db";     // name of the database you’ll create

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>