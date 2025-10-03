<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID required']);
    exit();
}

$order_id = intval($_GET['order_id']);
$user_email = $_SESSION['email'];

require_once 'config.php';

try {
    // First verify that this order belongs to the logged-in user
    $verify_stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_email = ?");
    $verify_stmt->bind_param("is", $order_id, $user_email);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this order']);
        exit();
    }
    
    $verify_stmt->close();
    
    // Get order items
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY item_id");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($items);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>