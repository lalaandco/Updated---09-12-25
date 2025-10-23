<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$cancellation_id = intval($_GET['cancellation_id'] ?? 0);

if ($cancellation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cancellation ID']);
    exit();
}

try {
    $query = "
        SELECT 
            oc.*,
            o.order_id,
            o.full_name,
            o.phone,
            o.total_amount,
            o.payment_method,
            o.order_date,
            o.status as order_status,
            u.name as user_name,
            u.contact_number as user_contact
        FROM order_cancellations oc
        JOIN orders o ON oc.order_id = o.order_id
        LEFT JOIN users u ON oc.user_email = u.email
        WHERE oc.cancellation_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cancellation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Cancellation request not found']);
        exit();
    }
    
    $cancellation = $result->fetch_assoc();
    $stmt->close();
    
    // Get order items
    $items_query = "SELECT * FROM order_items WHERE order_id = ?";
    $stmt = $conn->prepare($items_query);
    $order_id = $cancellation['order_id'];
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'cancellation' => $cancellation,
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