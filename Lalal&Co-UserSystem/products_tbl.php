<?php
session_start();
    require_once 'config.php';

    $cnald_tnom = $conn->query("SELECT * FROM product_tbl WHERE product_id = '1'");
    $product_tbl = $cnald_tnom  ->fetch_assoc();
    $_SESSION['product_name'] = $product_tbl["product_name"];  
    $_SESSION['product_description'] = $product_tbl["product_description"];  
    $_SESSION['product_price'] = $product_tbl["product_price"];  
    $_SESSION['product_quantity'] = $product_tbl["product_quantity"];  
    $_SESSION['product_name'] = $product_tbl[""];       
    $_SESSION["image_path"] = $product_tbl["image_path"];

        
    ?>