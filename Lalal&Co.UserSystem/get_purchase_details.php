<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get purchase ID from request
$purchase_id = $_GET['purchase_id'] ?? 0;

if (!$purchase_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid purchase ID']);
    exit();
}

try {
    // Get purchase order details
    $purchase_query = "SELECT * FROM purchase_orders WHERE purchase_id = ?";
    $stmt = $conn->prepare($purchase_query);
    $stmt->bind_param("i", $purchase_id);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();
    
    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Purchase order not found']);
        exit();
    }
    
    // Get purchase items with product names
    $items_query = "
        SELECT 
            poi.*,
            p.product_name
        FROM purchase_order_items poi
        JOIN product_tbl p ON poi.product_id = p.product_id
        WHERE poi.purchase_id = ?
    ";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $purchase_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'purchase' => $purchase,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>