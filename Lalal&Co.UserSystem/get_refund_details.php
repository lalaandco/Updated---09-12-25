<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$refund_id = intval($_GET['refund_id'] ?? 0);

if ($refund_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid refund ID']);
    exit();
}

try {
    $query = "
        SELECT 
            rf.*,
            o.order_id,
            o.full_name,
            o.phone,
            o.address,
            o.city,
            o.total_amount,
            o.payment_method,
            o.order_date,
            o.status as order_status,
            u.name as user_name,
            u.contact_number as user_contact,
            u.email as user_email
        FROM order_refunds rf
        JOIN orders o ON rf.order_id = o.order_id
        LEFT JOIN users u ON rf.user_email = u.email
        WHERE rf.refund_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $refund_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Refund request not found']);
        exit();
    }
    
    $refund = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $items_query = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt = $conn->prepare($items_query);
    $order_id = $refund['order_id'];
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'refund' => $refund,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>