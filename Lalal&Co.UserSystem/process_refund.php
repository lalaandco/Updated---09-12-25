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
        
        // ❌ REMOVED: DO NOT restore inventory (items are damaged/used)
        // Instead, log the refund as a loss in inventory transactions for tracking
        
        // Get all items from this order
        $items_query = "SELECT product_id, quantity, product_name FROM order_items WHERE order_id = ?";
        $stmt = $conn->prepare($items_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Log refund as inventory adjustment (for tracking only - no quantity change)
        foreach ($items as $item) {
            // Get current inventory levels (no changes, just for logging)
            $log_stmt = $conn->prepare("SELECT inventory_quantity, display_quantity FROM product_tbl WHERE product_id = ?");
            $log_stmt->bind_param("i", $item['product_id']);
            $log_stmt->execute();
            $log_result = $log_stmt->get_result();
            $log_data = $log_result->fetch_assoc();
            $log_stmt->close();
            
            $inv_before = $log_data['inventory_quantity'];
            $inv_after = $inv_before; // No change
            $display_before = $log_data['display_quantity'];
            $display_after = $display_before; // No change
            
            // Log transaction for tracking (negative quantity shows it's a loss)
            $notes = "Order #{$order_id} refunded - Item damaged/used, not returned to inventory. Loss recorded.";
            $negative_qty = -$item['quantity']; // Negative to show it's a loss
            
            $transaction_stmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (product_id, transaction_type, quantity, inventory_before, inventory_after, 
                 display_before, display_after, notes, admin_email) 
                VALUES (?, 'adjustment', ?, ?, ?, ?, ?, ?, ?)
            ");
            $admin_email = $_SESSION['admin_email'];
            $transaction_stmt->bind_param("iiiiisss", 
                $item['product_id'], 
                $negative_qty, 
                $inv_before, 
                $inv_after, 
                $display_before, 
                $display_after, 
                $notes,
                $admin_email
            );
            $transaction_stmt->execute();
            $transaction_stmt->close();
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
        
        $message = 'Refund approved successfully. Items NOT returned to inventory (damaged/used). Please process customer refund.';
        
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