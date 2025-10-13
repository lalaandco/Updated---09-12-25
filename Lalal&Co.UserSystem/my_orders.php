<?php
session_start();

// Check if user is logged in using your existing system
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

$user_email = $_SESSION['email'];

require_once 'config.php';

// Fetch user's orders with their items
$stmt = $conn->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as purchased_items,
           COUNT(oi.item_id) as item_count
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id 
    WHERE o.user_email = ? 
    GROUP BY o.order_id 
    ORDER BY o.order_date DESC
");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch order items for all orders to avoid separate AJAX calls
$order_items = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'order_id');
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    $items_stmt = $conn->prepare("
        SELECT order_id, product_name, quantity, product_price, subtotal 
        FROM order_items 
        WHERE order_id IN ($placeholders) 
        ORDER BY order_id, item_id
    ");
    $items_stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $order_items[$item['order_id']][] = $item;
    }
    $items_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="my_orders.css">
    <title>My Orders - Lalal & Co.</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-header">
        <a href="index.php" class="back-btn">
            <i>←</i> Back to Home
        </a>
        <h1>My Orders</h1>
        <p>Track your purchases and order history</p>
    </div>
    
    <div class="container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h2 class="empty-title">No orders yet</h2>
                <p class="empty-subtitle">You haven't placed any orders. Start shopping to see your order history here.</p>
                <a href="index.php" class="shop-now-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-header-top">
                                <div>
                                    <div class="order-id">Order #<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date"><?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></div>
                                </div>
                                <div class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-body">
                            <!-- Purchased Items Section -->
                            <div class="purchased-items">
                                <div class="items-label">
                                    Purchased Items 
                                    <span class="item-count-badge"><?php echo $order['item_count']; ?> item(s)</span>
                                </div>
                                <div class="items-list">
                                    <?php if (isset($order_items[$order['order_id']])): ?>
                                        <div class="quick-items-preview">
                                            <?php foreach ($order_items[$order['order_id']] as $item): ?>
                                                <div class="item-preview" title="₱<?php echo number_format($item['product_price'], 2); ?> each">
                                                    <?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #999; font-style: italic;">
                                            No items found for this order.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="order-info">
                                <div class="info-group">
                                    <div class="info-label">Delivery Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['address'] . ', ' . $order['city']); ?></div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($order['tracking_number']): ?>
                                <div class="tracking-info">
                                    <div class="tracking-label">Tracking Number:</div>
                                    <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="order-total">
                                Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            
                            <button class="view-details-btn" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                View Full Order Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <h3 id="modalTitle">Order Details</h3>
                <span onclick="closeModal()" style="font-size: 24px; cursor: pointer; color: #999;">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        const orders = <?php echo json_encode($orders); ?>;
        const orderItems = <?php echo json_encode($order_items); ?>;
        
        function viewOrderDetails(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
            
            const items = orderItems[orderId] || [];
            let itemsHtml = '<h4>Order Items</h4>';
            
            if (items.length > 0) {
                items.forEach(item => {
                    itemsHtml += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px; background: #fafafa;">
                            <div style="flex: 1;">
                                <strong style="font-size: 1rem;">${item.product_name}</strong><br>
                                <small style="color: #666;">Quantity: ${item.quantity} × ₱${parseFloat(item.product_price).toFixed(2)}</small>
                            </div>
                            <div style="font-weight: bold; font-size: 1.1rem; color: #28a745;">₱${parseFloat(item.subtotal).toFixed(2)}</div>
                        </div>
                    `;
                });
                
                // Add items total
                const itemsTotal = items.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
                itemsHtml += `
                    <div style="text-align: right; margin-top: 15px; padding-top: 15px; border-top: 2px solid #eee;">
                        <strong style="font-size: 1.1rem;">Items Total: ₱${itemsTotal.toFixed(2)}</strong>
                    </div>
                `;
            } else {
                itemsHtml += '<p style="color: #999; font-style: italic;">No items found for this order.</p>';
            }
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Order Information</h4>
                        <p><strong>Date:</strong> ${new Date(order.order_date).toLocaleDateString()}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${order.status}" style="padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">${order.status.toUpperCase()}</span></p>
                        <p><strong>Payment:</strong> ${order.payment_method.toUpperCase()}</p>
                        <p><strong>Total:</strong> ₱${parseFloat(order.total_amount).toFixed(2)}</p>
                        ${order.tracking_number ? `<p><strong>Tracking:</strong> ${order.tracking_number}</p>` : ''}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Delivery Address</h4>
                        <p><strong>Name:</strong> ${order.full_name}</p>
                        <p><strong>Phone:</strong> ${order.phone}</p>
                        <p><strong>Address:</strong> ${order.address}</p>
                        <p><strong>City:</strong> ${order.city} ${order.postal_code}</p>
                    </div>
                </div>
                
                ${order.order_notes ? `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4>Order Notes</h4>
                    <p>${order.order_notes}</p>
                </div>
                ` : ''}
                
                <div>
                    ${itemsHtml}
                </div>
            `;
            
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>