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

$refund_id = intval($_POST['refund_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$admin_notes = trim($_POST['admin_notes'] ?? '');

if ($refund_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid refund ID']);
    exit();
}

if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Get refund and order details
    $query = "
        SELECT rf.*, o.order_id, o.payment_method
        FROM order_refunds rf
        JOIN orders o ON rf.order_id = o.order_id
        WHERE rf.refund_id = ? AND rf.status = 'pending'
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $refund_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Refund request not found or already processed');
    }
    
    $refund = $result->fetch_assoc();
    $order_id = $refund['order_id'];
    $stmt->close();
    
    if ($action === 'approve') {
        // Update refund status
        $update_refund = $conn->prepare("
            UPDATE order_refunds 
            SET status = 'approved', admin_notes = ?, processed_at = NOW() 
            WHERE refund_id = ?
        ");
        $update_refund->bind_param("si", $admin_notes, $refund_id);
        $update_refund->execute();
        $update_refund->close();
        
        // Update order status to refunded
        $update_order = $conn->prepare("UPDATE orders SET status = 'refunded' WHERE order_id = ?");
        $update_order->bind_param("i", $order_id);
        $update_order->execute();
        $update_order->close();
        
        // ✅ RESTORE INVENTORY - Add quantities back to storage (inventory_quantity)
        // Get all items from this order
        $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
        $stmt = $conn->prepare($items_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Restore each item's quantity to storage
        foreach ($items as $item) {
            $restore_stmt = $conn->prepare("
                UPDATE product_tbl 
                SET inventory_quantity = inventory_quantity + ? 
                WHERE product_id = ?
            ");
            $restore_stmt->bind_param("ii", $item['quantity'], $item['product_id']);
            $restore_stmt->execute();
            $restore_stmt->close();
        }
        
        // Mark payment for refund
        $update_payment = $conn->prepare("
            UPDATE payment_verifications 
            SET verification_status = 'refund_approved' 
            WHERE order_id = ?
        ");
        $update_payment->bind_param("i", $order_id);
        $update_payment->execute();
        $update_payment->close();
        
        $message = 'Refund approved successfully. Inventory has been restored. Please process customer refund.';
        
    } else { // reject
        // Update refund status
        $update_refund = $conn->prepare("
            UPDATE order_refunds 
            SET status = 'rejected', admin_notes = ?, processed_at = NOW() 
            WHERE refund_id = ?
        ");
        $update_refund->bind_param("si", $admin_notes, $refund_id);
        $update_refund->execute();
        $update_refund->close();
        
        // Revert order status back to delivered
        $update_order = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE order_id = ?");
        $update_order->bind_param("i", $order_id);
        $update_order->execute();
        $update_order->close();
        
        $message = 'Refund request rejected.';
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