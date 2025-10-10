<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    $productId = $_GET['id'];
    
    // Try forhim_tbl first
    $stmt = $conn->prepare("SELECT * FROM forhim_tbl WHERE product_id = ?");
    $stmt->bind_param("s", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Check if product was found in forhim_tbl
    if ($result && $result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Save to session
        $_SESSION["product_name"] = $product["product_name"];
        $_SESSION["product_description"] = $product["product_description"];
        $_SESSION["product_price"] = $product["product_price"];
        $_SESSION["image"] = $product["image_path"];
    } else {
        // Not found in forhim_tbl, try forher_tbl
        $stmt->close();
    // DON'T close $conn here - it's needed by buyProduct.php
        $stmt = $conn->prepare("SELECT * FROM forher_tbl WHERE product_id = ?");
        $stmt->bind_param("s", $productId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $product = $result->fetch_assoc();
            
            // Save to session
            $_SESSION["product_name"] = $product["product_name"];
            $_SESSION["product_description"] = $product["product_description"];
            $_SESSION["product_price"] = $product["product_price"];
            $_SESSION["image"] = $product["image_path"];
        } else {
            // No product found in either table
            $_SESSION["product_name"] = "Unknown Product";
            $_SESSION["product_description"] = "No description available.";
            $_SESSION["product_price"] = "0.00";
            $_SESSION["image"] = "images/default.png";
        }
    }
    
    $stmt->close();
} else {
    // No ID provided
    $_SESSION["product_name"] = "Unknown Product";
    $_SESSION["product_description"] = "No description available.";
    $_SESSION["product_price"] = "0.00";
    $_SESSION["image"] = "images/default.png";
}
?>