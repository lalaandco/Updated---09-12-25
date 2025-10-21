<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to cancel orders']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$order_id = intval($_POST['order_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$user_email = $_SESSION['email'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

if (empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Please select a reason for cancellation']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Verify the order belongs to the user and check payment status
    $check_query = "
        SELECT o.order_id, o.status, o.payment_status, pv.verification_status
        FROM orders o
        LEFT JOIN payment_verifications pv ON o.order_id = pv.order_id
        WHERE o.order_id = ? AND o.user_email = ?
    ";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $order_id, $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Order not found or access denied');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Check if payment is already verified
    if ($order['verification_status'] === 'verified') {
        throw new Exception('Cannot cancel - Payment has already been verified by admin');
    }
    
    // Check if order is already delivered
    if ($order['status'] === 'delivered') {
        throw new Exception('Cannot cancel - Order has been delivered. Please use Refund option instead');
    }
    
    // Check if order is already cancelled
    if ($order['status'] === 'cancelled') {
        throw new Exception('This order has already been cancelled');
    }
    
    // Check if there's already a pending cancellation request
    $check_cancel = $conn->prepare("SELECT cancellation_id FROM order_cancellations WHERE order_id = ? AND status = 'pending'");
    $check_cancel->bind_param("i", $order_id);
    $check_cancel->execute();
    if ($check_cancel->get_result()->num_rows > 0) {
        throw new Exception('You already have a pending cancellation request for this order');
    }
    $check_cancel->close();
    
    // Insert cancellation request
    $insert_stmt = $conn->prepare("
        INSERT INTO order_cancellations (order_id, user_email, reason, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $insert_stmt->bind_param("iss", $order_id, $user_email, $reason);
    
    if (!$insert_stmt->execute()) {
        throw new Exception('Failed to submit cancellation request');
    }
    
    $insert_stmt->close();
    
    // Update order status to indicate cancellation pending
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'cancellation_pending' WHERE order_id = ?");
    $update_stmt->bind_param("i", $order_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cancellation request submitted successfully. Admin will review your request.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>