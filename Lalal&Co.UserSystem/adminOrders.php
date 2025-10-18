<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.php");
    exit();
}

// Include config
require_once 'config.php';

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: adminLogin.php");
    exit();
}

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $tracking_number = $_POST['tracking_number'] ?? '';
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, tracking_number = ? WHERE order_id = ?");
    $stmt->bind_param("ssi", $new_status, $tracking_number, $order_id);
    
    if ($stmt->execute()) {
        $success_message = "Order status updated successfully";
    } else {
        $error_message = "Failed to update order status";
    }
    
    $stmt->close();
}

// Fetch orders with user information and payment verification details
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(o.full_name LIKE ? OR o.user_email LIKE ? OR o.order_id LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($status_filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($payment_status_filter)) {
    $where_conditions[] = "o.payment_status = ?";
    $params[] = $payment_status_filter;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "SELECT o.*, u.name as user_full_name, u.address as user_registered_address, u.contact_number,
                 pv.verification_id, pv.gcash_transaction_id, pv.verification_status, pv.submitted_at
          FROM orders o 
          LEFT JOIN users u ON o.user_email = u.email 
          LEFT JOIN payment_verifications pv ON o.order_id = pv.order_id
          {$where_clause} 
          ORDER BY o.order_date DESC";
          
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminOrdersStyle.css">
    <title>Orders Management - Admin Panel</title>
    <style>
        .payment-status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .payment-column {
            min-width: 150px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="left-section">
            <div class="lc-logo">
                <a href="adminIndex.php">
                    <img src="images/lcLogo.png" alt="LC Logo">
                </a>
            </div>
        </div>
        
        <div class="center-section">
            <div class="welcome-text">WELCOME OWNER</div>
        </div>
        
        <div class="right-section">
            <div class="icon-tags">
                <a href="#"><i class='bx bx-bell'></i></a>
                <a href="#"><i class='bx bx-cog'></i></a>
            </div>
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Admin Name</span>
            </div>

            <a href="adminIndex.php" style="color: #666; text-decoration: none;">
                <i class='bx bx-arrow-back'></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="filters-section">
            <form method="GET">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="search">Search Orders</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Order ID, Customer name, Email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Order Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_status">Payment Status</label>
                        <select id="payment_status" name="payment_status">
                            <option value="">All Payments</option>
                            <option value="pending_verification" <?php echo $payment_status_filter === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                            <option value="verified" <?php echo $payment_status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="failed" <?php echo $payment_status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="cancelled" <?php echo $payment_status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="search-btn">🔍 Search</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="orders-section">
            <div class="orders-header">
                <h3>Orders List (<?php echo count($orders); ?> orders)</h3>
            </div>
            
            <?php if (empty($orders)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <h4>No orders found</h4>
                    <p>No orders match your current filters.</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Order Status</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($order['full_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($order['user_email']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td class="payment-column">
                                <span class="payment-status-badge payment-<?php 
                                    echo $order['payment_status'] === 'verified' ? 'verified' : 
                                        ($order['payment_status'] === 'pending_verification' ? 'pending' : 'failed'); 
                                ?>">
                                    <?php 
                                    echo $order['payment_status'] === 'verified' ? '✓ Verified' : 
                                        ($order['payment_status'] === 'pending_verification' ? '⏳ Awaiting' : 
                                        ($order['payment_status'] === 'failed' ? '✗ Failed' : 'Cancelled'));
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                        👁️ View
                                    </button>
                                    <button class="btn btn-primary" onclick="updateOrder(<?php echo $order['order_id']; ?>)">
                                        ✏️ Update
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Order Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>
    
    <script>
        const orders = <?php echo json_encode($orders); ?>;
        
        function viewOrder(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
            
            // Payment status badge
            let paymentStatusBadge = '';
            if (order.payment_status === 'verified') {
                paymentStatusBadge = '<span style="background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 600;">✓ Verified</span>';
            } else if (order.payment_status === 'pending_verification') {
                paymentStatusBadge = '<span style="background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 600;">⏳ Awaiting Verification</span>';
            } else if (order.payment_status === 'failed') {
                paymentStatusBadge = '<span style="background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 600;">✗ Failed</span>';
            } else {
                paymentStatusBadge = '<span style="background: #e2e3e5; color: #383d41; padding: 6px 12px; border-radius: 15px; font-size: 0.9rem; font-weight: 600;">Cancelled</span>';
            }
            
            // Transaction info
            let transactionInfo = '';
            if (order.gcash_transaction_id) {
                transactionInfo = `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <h4 style="margin-top: 0;">Payment Verification Details</h4>
                        <p><strong>GCash Transaction ID:</strong><br>
                        <code style="background: white; padding: 8px 12px; border-radius: 4px; font-family: monospace; display: inline-block; margin-top: 5px;">
                            ${order.gcash_transaction_id}
                        </code></p>
                        <p><strong>Status:</strong> ${paymentStatusBadge}</p>
                        ${order.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(order.submitted_at).toLocaleString()}</p>` : ''}
                    </div>
                `;
            } else {
                transactionInfo = `
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px;">
                        <p><strong>Payment Status:</strong> ${paymentStatusBadge}</p>
                        <p style="color: #856404; font-size: 0.9rem;">No payment proof submitted yet. Customer needs to submit GCash transaction details.</p>
                    </div>
                `;
            }
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="order-details">
                    <div class="detail-group">
                        <h4>Customer Information</h4>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span>${order.full_name}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span>${order.user_email}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone:</span>
                            <span>${order.phone}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Contact Number:</span>
                            <span>${order.contact_number || 'N/A'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Registered Address:</span>
                            <span>${order.user_registered_address || 'N/A'}</span>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <h4>Order Information</h4>
                        <div class="detail-row">
                            <span class="detail-label">Order Date:</span>
                            <span>${new Date(order.order_date).toLocaleDateString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Order Status:</span>
                            <span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span>${order.payment_method.toUpperCase()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Amount:</span>
                            <span>₱${parseFloat(order.total_amount).toFixed(2)}</span>
                        </div>
                        ${order.tracking_number ? `
                        <div class="detail-row">
                            <span class="detail-label">Tracking:</span>
                            <span>${order.tracking_number}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${transactionInfo}
                </div>
                
                <div class="detail-group">
                    <h4>Delivery Address</h4>
                    <p>${order.address}, ${order.city} ${order.postal_code}</p>
                </div>
                
                ${order.order_notes ? `
                <div class="detail-group">
                    <h4>Order Notes</h4>
                    <p>${order.order_notes}</p>
                </div>
                ` : ''}
                
                <div id="order-items-${order.order_id}">
                    <h4>Loading order items...</h4>
                </div>
            `;
            
            loadOrderItems(orderId);
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function updateOrder(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Update Order #${String(order.order_id).padStart(8, '0')}`;
            
            // Payment status info
            let paymentStatusText = '';
            if (order.payment_status === 'verified') {
                paymentStatusText = '<span style="color: #155724; font-weight: 600;">✓ Payment Verified</span>';
            } else if (order.payment_status === 'pending_verification') {
                paymentStatusText = '<span style="color: #856404; font-weight: 600;">⏳ Payment Awaiting Verification</span>';
            } else if (order.payment_status === 'failed') {
                paymentStatusText = '<span style="color: #721c24; font-weight: 600;">✗ Payment Failed</span>';
            } else {
                paymentStatusText = '<span style="color: #666; font-weight: 600;">Cancelled</span>';
            }
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="order-details">
                    <div class="detail-group">
                        <h4>Current Status</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <span class="detail-label" style="color: #666;">Order Status:</span>
                                <span class="status-badge status-${order.status}" style="display: inline-block; margin-top: 5px;">${order.status.toUpperCase()}</span>
                            </div>
                            <div>
                                <span class="detail-label" style="color: #666;">Payment Status:</span>
                                <div style="margin-top: 5px;">${paymentStatusText}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="update-form">
                    <input type="hidden" name="order_id" value="${order.order_id}">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">New Order Status</label>
                            <select name="status" required>
                                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                                <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tracking_number">Tracking Number (Optional)</label>
                            <input type="text" name="tracking_number" value="${order.tracking_number || ''}" placeholder="Enter tracking number">
                        </div>
                    </div>
                    
                    <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <p style="margin: 0; color: #666; font-size: 0.9rem;">
                            <strong>Note:</strong> To manage payment verification, go to <strong>Payment Verification</strong> page.
                        </p>
                    </div>
                    
                    <button type="submit" name="update_status" class="search-btn">Update Order Status</button>
                </form>
            `;
            
            document.getElementById('orderModal').style.display = 'block';
        }
        
        async function loadOrderItems(orderId) {
            try {
                const response = await fetch(`get_order_items.php?order_id=${orderId}`);
                const items = await response.json();
                
                let itemsHtml = '<div class="items-list"><h4>Order Items</h4>';
                
                if (items.length > 0) {
                    items.forEach(item => {
                        itemsHtml += `
                            <div class="item">
                                <div>
                                    <strong>${item.product_name}</strong><br>
                                    <small>Quantity: ${item.quantity}</small>
                                </div>
                                <div>₱${parseFloat(item.subtotal).toFixed(2)}</div>
                            </div>
                        `;
                    });
                } else {
                    itemsHtml += '<p>No items found for this order.</p>';
                }
                
                itemsHtml += '</div>';
                document.getElementById(`order-items-${orderId}`).innerHTML = itemsHtml;
            } catch (error) {
                document.getElementById(`order-items-${orderId}`).innerHTML = '<p>Error loading order items.</p>';
            }
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>