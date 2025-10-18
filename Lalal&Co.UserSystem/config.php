<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "users_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection but don't output HTML if it's an AJAX request
if ($conn->connect_error) {
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Also check if it's our cart AJAX call
    $isCartAjax = isset($_POST['action']) && in_array($_POST['action'], [
        'add_to_cart', 'toggle_selection', 'remove_from_cart', 
        'update_quantity', 'get_cart', 'get_cart_count'
    ]);
    
    if ($isAjax || $isCartAjax) {
        // For AJAX requests, throw an exception instead of dying with HTML
        throw new Exception("Database connection failed: " . $conn->connect_error);
    } else {
        // For regular page requests, show error page
        die("Connection failed: " . $conn->connect_error);
    }
}
?>