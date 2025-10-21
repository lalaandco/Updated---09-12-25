<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$cancellation_id = intval($_POST['cancellation_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$admin_notes = trim($_POST['admin_notes'] ?? '');

if ($cancellation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cancellation ID']);
    exit();
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Get cancellation and order details
    $query = "
        SELECT oc.*, o.order_id, o.payment_method
        FROM order_cancellations oc
        JOIN orders o ON oc.order_id = o.order_id
        WHERE oc.cancellation_id = ? AND oc.status = 'pending'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cancellation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Cancellation request not found or already processed');
    }
    
    $cancellation = $result->fetch_assoc();
    $order_id = $cancellation['order_id'];
    $stmt->close();
    
    if ($action === 'approve') {
        // Update cancellation status
        $update_cancel = $conn->prepare("
            UPDATE order_cancellations 
            SET status = 'approved', admin_notes = ?, processed_at = NOW() 
            WHERE cancellation_id = ?
        ");
        $update_cancel->bind_param("si", $admin_notes, $cancellation_id);
        $update_cancel->execute();
        $update_cancel->close();
        
        // Update order status to cancelled
        $update_order = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
        $update_order->bind_param("i", $order_id);
        $update_order->execute();
        $update_order->close();
        
        // If payment was already verified, mark it for refund
        $update_payment = $conn->prepare("
            UPDATE payment_verifications 
            SET verification_status = 'refund_pending' 
            WHERE order_id = ? AND verification_status = 'verified'
        ");
        $update_payment->bind_param("i", $order_id);
        $update_payment->execute();
        $update_payment->close();
        
        $message = 'Cancellation approved successfully. Customer will be refunded.';
        
    } else { // reject
        // Update cancellation status
        $update_cancel = $conn->prepare("
            UPDATE order_cancellations 
            SET status = 'rejected', admin_notes = ?, processed_at = NOW() 
            WHERE cancellation_id = ?
        ");
        $update_cancel->bind_param("si", $admin_notes, $cancellation_id);
        $update_cancel->execute();
        $update_cancel->close();
        
        // Revert order status back to original (pending or confirmed)
        $update_order = $conn->prepare("UPDATE orders SET status = 'pending' WHERE order_id = ?");
        $update_order->bind_param("i", $order_id);
        $update_order->execute();
        $update_order->close();
        
        $message = 'Cancellation request rejected.';
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
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