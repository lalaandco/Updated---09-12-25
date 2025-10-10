<?php
session_start();

// Check if user is logged in using your existing system
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

$user_email = $_SESSION['email'];

require_once 'config.php';

// Fetch user's orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_email = ? ORDER BY order_date DESC");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - La Gal & Co.</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
            margin-top: 100px; 
        }
        
        .page-header {
            background: linear-gradient(135deg, #dbddd3ff 0%, #c2bf2dff 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: -20px auto 40px;
            padding: 0 20px;
        }
        
        .orders-grid {
            display: grid;
            gap: 20px;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .order-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
        }
        
        .order-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .order-id {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .order-date {
            color: #666;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-processing {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-shipped {
            background: #d4edda;
            color: #155724;
        }
        
        .status-delivered {
            background: #d1e7dd;
            color: #0a3622;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-body {
            padding: 25px;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-group {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #333;
            font-size: 1rem;
        }
        
        .order-total {
            text-align: right;
            font-size: 1.3rem;
            font-weight: bold;
            color: #28a745;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e9ecef;
        }
        
        .tracking-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .tracking-label {
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 5px;
        }
        
        .tracking-number {
            font-family: monospace;
            font-size: 1.1rem;
            color: #333;
        }
        
        .view-details-btn {
            background: linear-gradient(135deg, #b9c73dff 0%, #3c3d0dff 100%);
            color: black;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(158, 196, 55, 0.81);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-subtitle {
            color: #999;
            margin-bottom: 30px;
        }
        
        .shop-now-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .shop-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .back-btn i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 30px 15px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .order-header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-info {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .order-header,
            .order-body {
                padding: 15px;
            }
        }
    </style>
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
                                View Order Details
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
        
        function viewOrderDetails(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Order Information</h4>
                        <p><strong>Date:</strong> ${new Date(order.order_date).toLocaleDateString()}</p>
                        <p><strong>Status:</strong> <span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span></p>
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
                
                <div id="order-items-${order.order_id}">
                    <h4>Loading order items...</h4>
                </div>
            `;
            
            // Load order items
            loadOrderItems(orderId);
            
            document.getElementById('orderModal').style.display = 'block';
        }
        
        async function loadOrderItems(orderId) {
            try {
                const response = await fetch(`get_user_order_items.php?order_id=${orderId}`);
                const items = await response.json();
                
                let itemsHtml = '<h4>Order Items</h4>';
                
                if (items.length > 0) {
                    items.forEach(item => {
                        itemsHtml += `
                            <div style="display: flex; justify-content: space-between; padding: 10px; border: 1px solid #eee; border-radius: 4px; margin-bottom: 10px;">
                                <div>
                                    <strong>${item.product_name}</strong><br>
                                    <small>Quantity: ${item.quantity} × ₱${parseFloat(item.product_price).toFixed(2)}</small>
                                </div>
                                <div style="font-weight: bold;">₱${parseFloat(item.subtotal).toFixed(2)}</div>
                            </div>
                        `;
                    });
                } else {
                    itemsHtml += '<p>No items found for this order.</p>';
                }
                
                document.getElementById(`order-items-${orderId}`).innerHTML = itemsHtml;
            } catch (error) {
                document.getElementById(`order-items-${orderId}`).innerHTML = '<p>Error loading order items.</p>';
            }
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