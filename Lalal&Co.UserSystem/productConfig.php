<?php
require_once 'config.php';
if (isset($_GET['id'])) {
    $productId = $_GET['id'];

    // Prepare the SQL query to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM product_tbl WHERE product_id = ?");
    $stmt->bind_param("i", $productId); // use $productId, not $product_id

    $stmt->execute();
    $result = $stmt->get_result();

    // Check if a product was found
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();

        // Save to session
        $_SESSION["product_name"] = $product["product_name"];
        $_SESSION["product_description"] = $product["product_description"];
        $_SESSION["product_price"] = $product["product_price"];
        $_SESSION["image"] = $product["image_path"];
    } else {
        // No product found
        $_SESSION["product_name"] = "Unknown Product";
        $_SESSION["product_description"] = "No description available.";
        $_SESSION["product_price"] = "0.00";
        $_SESSION["image"] = "images/default.png";
    }

    $stmt->close();
} else {
    // No ID provided
    $_SESSION["product_name"] = "Unknown Product";
    $_SESSION["product_description"] = "No description available.";
    $_SESSION["product_price"] = "0.00";
    $_SESSION["image"] = "images/default.png";
}

$conn->close();
?>