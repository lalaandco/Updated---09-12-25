<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

try {
    // Fetch complete order details with user information
    $order_query = "
        SELECT 
            o.*,
            u.name as user_name,
            u.contact_number
        FROM orders o
        LEFT JOIN users u ON o.user_email = u.email
        WHERE o.order_id = ?
    ";
    
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        $stmt->close();
        $conn->close();
        exit();
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Fetch order items with product details
    $items_query = "
        SELECT 
            oi.*,
            p.image_path,
            p.product_description
        FROM order_items oi
        LEFT JOIN product_tbl p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
        ORDER BY oi.item_id
    ";
    
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>