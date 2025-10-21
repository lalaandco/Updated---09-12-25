<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

$message = '';
$error = '';

// Handle payment verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $verification_id = intval($_POST['verification_id']);
    $action = $_POST['action']; // 'verify' or 'reject'
    $admin_notes = $_POST['admin_notes'] ?? '';
    $order_id = intval($_POST['order_id']);
    
    try {
        $conn->begin_transaction();
        
        if ($action === 'verify') {
            // Get order items for stock deduction
            $items_stmt = $conn->prepare("SELECT product_id, quantity, product_name FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
            $items_stmt->close();
            
            // Check DISPLAY stock availability (not warehouse)
            $stock_errors = [];
            foreach ($order_items as $item) {
                $check_stmt = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ? FOR UPDATE");
                $check_stmt->bind_param("i", $item['product_id']);
                $check_stmt->execute();
                $stock_result = $check_stmt->get_result();
                
                if ($stock_row = $stock_result->fetch_assoc()) {
                    if ($stock_row['display_quantity'] < $item['quantity']) {
                        $stock_errors[] = "Product '{$item['product_name']}' only has {$stock_row['display_quantity']} available in display, but order requires {$item['quantity']}";
                    }
                } else {
                    $stock_errors[] = "Product '{$item['product_name']}' not found";
                }
                $check_stmt->close();
            }
            
            if (!empty($stock_errors)) {
                throw new Exception("Cannot verify payment - Stock issues: " . implode("; ", $stock_errors));
            }
            
            // ✅ STEP 1: Deduct from DISPLAY ONLY when payment is verified
            $stock_update_stmt = $conn->prepare("UPDATE product_tbl SET display_quantity = display_quantity - ? WHERE product_id = ?");
            $transaction_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes) VALUES (?, 'customer_purchase', ?, ?, ?, ?, ?, ?)");
            
            foreach ($order_items as $item) {
                $product_id = $item['product_id'];
                $quantity = $item['quantity'];
                
                // Get current inventory levels
                $log_stmt = $conn->prepare("SELECT inventory_quantity, display_quantity FROM product_tbl WHERE product_id = ?");
                $log_stmt->bind_param("i", $product_id);
                $log_stmt->execute();
                $log_result = $log_stmt->get_result();
                $log_data = $log_result->fetch_assoc();
                $log_stmt->close();
                
                $inv_before = $log_data['inventory_quantity'];
                $inv_after = $inv_before; // Warehouse unchanged at verification
                $display_before = $log_data['display_quantity'];
                $display_after = $display_before - $quantity; // Only display decreases
                
                // Update DISPLAY stock only
                $stock_update_stmt->bind_param("ii", $quantity, $product_id);
                if (!$stock_update_stmt->execute()) {
                    throw new Exception("Failed to update display stock for product ID: {$product_id}");
                }
                
                // Log transaction - warehouse unchanged, display decreased
                $notes = "Payment verified - Order #{$order_id} - Display stock deducted. Warehouse will be deducted when shipped.";
                $negative_qty = -$quantity;
                $transaction_stmt->bind_param("iiiiiss", $product_id, $negative_qty, $inv_before, $inv_after, $display_before, $display_after, $notes);
                if (!$transaction_stmt->execute()) {
                    throw new Exception("Failed to log transaction for product ID: {$product_id}");
                }
            }
            
            $stock_update_stmt->close();
            $transaction_stmt->close();
            
            // Get user email to clear their cart
            $user_stmt = $conn->prepare("SELECT user_email FROM orders WHERE order_id = ?");
            $user_stmt->bind_param("i", $order_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_email = $user_data['user_email'];
            $user_stmt->close();
            
            // Remove purchased items from user's cart
            if ($user_email) {
                foreach ($order_items as $item) {
                    $cart_delete = $conn->prepare("DELETE FROM shopping_cart WHERE user_email = ? AND product_id = ?");
                    $cart_delete->bind_param("si", $user_email, $item['product_id']);
                    $cart_delete->execute();
                    $cart_delete->close();
                }
            }
            
            // Update payment verification status
            $verify_stmt = $conn->prepare("
                UPDATE payment_verifications 
                SET verification_status = 'verified', 
                    verified_at = NOW(), 
                    verified_by = ?,
                    admin_notes = ?
                WHERE verification_id = ?
            ");
            $verify_stmt->bind_param("ssi", $_SESSION['admin_email'], $admin_notes, $verification_id);
            $verify_stmt->execute();
            $verify_stmt->close();
            
            // Update order payment status
            $order_stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'verified', 
                    payment_verified_at = NOW(),
                    status = 'confirmed'
                WHERE order_id = ?
            ");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_stmt->close();
            
            $message = "✅ Payment verified! Display stock deducted. Order status: 'Confirmed'. Warehouse stock will be deducted when order is marked as 'Shipped'.";
            
        } else if ($action === 'reject') {
            // Update payment verification status
            $reject_stmt = $conn->prepare("
                UPDATE payment_verifications 
                SET verification_status = 'failed', 
                    verified_at = NOW(), 
                    verified_by = ?,
                    admin_notes = ?
                WHERE verification_id = ?
            ");
            $reject_stmt->bind_param("ssi", $_SESSION['admin_email'], $admin_notes, $verification_id);
            $reject_stmt->execute();
            $reject_stmt->close();
            
            // Update order payment status to failed
            $order_stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'failed',
                    status = 'cancelled'
                WHERE order_id = ?
            ");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_stmt->close();
            
            $message = "Payment rejected. Order has been cancelled. Admin notes saved.";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Get pending payment verifications
$pending_stmt = $conn->prepare("
    SELECT 
        pv.*,
        o.order_id,
        o.full_name,
        o.user_email,
        o.total_amount,
        o.order_date,
        o.status as order_status
    FROM payment_verifications pv
    JOIN orders o ON pv.order_id = o.order_id
    WHERE pv.verification_status = 'pending'
    ORDER BY pv.submitted_at DESC
");
$pending_stmt->execute();
$pending_verifications = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

// Get verified/rejected verifications for history
$history_stmt = $conn->prepare("
    SELECT 
        pv.*,
        o.order_id,
        o.full_name,
        o.total_amount,
        o.order_date
    FROM payment_verifications pv
    JOIN orders o ON pv.order_id = o.order_id
    WHERE pv.verification_status IN ('verified', 'failed')
    ORDER BY pv.verified_at DESC
    LIMIT 20
");
$history_stmt->execute();
$verification_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$conn->close();

renderAdminHeader()
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <link rel="stylesheet" href="adminPaymentVerification.css">
    <title>Payment Verification - Admin Panel</title>
</head>
<body>
    <div class="verification-container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('pending')">
                <i class='bx bx-time'></i> Pending Verification 
                <?php if (!empty($pending_verifications)): ?>
                    <span class="pending-count"><?php echo count($pending_verifications); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab('history')">
                <i class='bx bx-history'></i> Verification History
            </button>
        </div>
        
        <!-- Pending Verifications Tab -->
        <div id="pending" class="tab-content active">
            <?php if (empty($pending_verifications)): ?>
                <div class="verification-card">
                    <div class="verification-body" style="text-align: center; padding: 60px 20px;">
                        <i class='bx bx-check-circle' style="font-size: 48px; color: #4CAF50;"></i>
                        <h3 style="margin-top: 15px; color: #333;">All Payments Verified!</h3>
                        <p style="color: #666;">There are no pending payment verifications at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pending_verifications as $verification): ?>
                    <div class="verification-card">
                        <div class="verification-header">
                            <div>
                                <h3>Order #<?php echo str_pad($verification['order_id'], 8, '0', STR_PAD_LEFT); ?></h3>
                                <p style="margin: 8px 0 0 0; color: #666; font-size: 13px;">
                                    Customer: <?php echo htmlspecialchars($verification['full_name']); ?> 
                                    (<?php echo htmlspecialchars($verification['user_email']); ?>)
                                </p>
                            </div>
                            <span class="status-badge status-pending">⏳ Pending</span>
                        </div>
                        
                        <div class="verification-body">
                            <div class="verification-details">
                                <div class="detail-item">
                                    <div class="detail-label">Order Date</div>
                                    <div class="detail-value"><?php echo date('M d, Y - h:i A', strtotime($verification['order_date'])); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Order Amount</div>
                                    <div class="detail-value" style="color: #4CAF50; font-weight: 600;">₱<?php echo number_format($verification['total_amount'], 2); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">GCash Transaction ID</div>
                                    <div class="detail-value" style="font-family: monospace; background: #f5f5f5; padding: 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($verification['gcash_transaction_id']); ?>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Submitted On</div>
                                    <div class="detail-value"><?php echo date('M d, Y - h:i A', strtotime($verification['submitted_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="screenshot-section">
                                <div class="detail-label">Payment Screenshot</div>
                                <img src="<?php echo htmlspecialchars($verification['screenshot_path']); ?>" alt="Payment Screenshot">
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="verification_id" value="<?php echo $verification['verification_id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $verification['order_id']; ?>">
                                <input type="hidden" name="verify_payment" value="1">
                                
                                <div class="detail-item">
                                    <div class="detail-label">Admin Notes (Optional)</div>
                                    <textarea name="admin_notes" class="admin-notes-field" rows="3" placeholder="Add any verification notes..."></textarea>
                                </div>
                                
                                <div class="verification-actions">
                                    <button type="submit" name="action" value="verify" class="btn-verify">
                                        <i class='bx bx-check'></i> Verify Payment & Deduct Display Stock
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Are you sure you want to reject this payment? The order will be cancelled and customer will need to resubmit.');">
                                        <i class='bx bx-x'></i> Reject Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Verification History Tab -->
        <div id="history" class="tab-content">
            <div class="verification-card">
                <div class="verification-header">
                    <h3>Recent Verification History</h3>
                </div>
                
                <?php if (empty($verification_history)): ?>
                    <div class="verification-body" style="text-align: center; padding: 40px 20px;">
                        <p style="color: #666;">No verification history yet.</p>
                    </div>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verification_history as $history): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($history['order_id'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($history['full_name']); ?></td>
                                    <td>₱<?php echo number_format($history['total_amount'], 2); ?></td>
                                    <td>
                                        <code style="background: #f5f5f5; padding: 4px 6px; border-radius: 3px; font-size: 12px;">
                                            <?php echo htmlspecialchars($history['gcash_transaction_id']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $history['verification_status'] === 'verified' ? 'status-verified' : 'status-rejected'; ?>">
                                            <?php echo $history['verification_status'] === 'verified' ? '✓ Verified' : '✗ Rejected'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($history['verified_by'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($history['verified_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>