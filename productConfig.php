<?php
session_start();
require_once 'config.php';

// Query to get one product from the database
$result = $conn->query("SELECT product_name FROM product_tbl");

// Fetch the product name from the result
if ($result && $row = $result->fetch_assoc()) {
    $product = [
        "product_name" => $row["product_name"],
        "image" => "images/1.png" // Change to your actual image path
    ];

    // Save to session
    $_SESSION["product_name"] = $product["product_name"];
    $_SESSION["image"] = $product["image"];
} else {
    $_SESSION["product_name"] = "Unknown Product";
    $_SESSION["image"] = "images/default.png"; // fallback image
}
?>
