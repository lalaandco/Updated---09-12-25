<?php
session_start();

// Simple admin authentication - replace with your admin system
$admin_email = "admin@lalal.com"; // Change this to your admin email
$admin_password = "admin123"; // Change this to a secure password

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['email'] === $admin_email && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Invalid credentials";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin_orders.php");
    exit();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - La Gal & Co.</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 400px;
            }
            
            .login-title {
                text-align: center;
                margin-bottom: 30px;
                color: #333;
                font-size: 24px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                margin-bottom: 5px;
                color: #555;
                font-weight: 600;
            }
            
            input[type="email"], input[type="password"] {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            
            .login-btn {
                width: 100%;
                background: #4CAF50;
                color: white;
                padding: 12px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
            }
            
            .login-btn:hover {
                background: #45a049;
            }
            
            .error-message {
                color: #d32f2f;
                text-align: center;
                margin-bottom: 20px;
                padding: 10px;
                background: #ffebee;
                border-radius: 4px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2 class="login-title">🔐 Admin Login</h2>
            
            <?php if (isset($login_error)): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Admin Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="login-btn">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Include config ONCE at the top after login check
require_once 'config.php';

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
    // DON'T close connection here - we need it later
}

// Fetch orders with user information
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
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

// Join with users table to get complete user information
$query = "SELECT o.*, u.name as user_full_name, u.address as user_registered_address, u.contact_number 
          FROM orders o 
          LEFT JOIN users u ON o.user_email = u.email 
          {$where_clause} 
          ORDER BY o.order_date DESC";
          
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
// Close connection at the very end
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .admin-header h1 {
            margin-bottom: 10px;
        }
        
        .admin-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        input, select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .search-btn:hover {
            background: #45a049;
        }
        
        .orders-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .orders-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .orders-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }
        
        .detail-group h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .items-list {
            margin-top: 20px;
        }
        
        .item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .update-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .orders-table {
                font-size: 12px;
            }
            
            .orders-table th,
            .orders-table td {
                padding: 8px 4px;
            }
            
            .filters-row {
                grid-template-columns: 1fr;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="admin-nav">
            <div>
                <h1>🛒 Orders Management</h1>
                <p>Manage customer orders and track deliveries</p>
            </div>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <div class="admin-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Filters Section -->
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
                        <label for="status">Status Filter</label>
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
        
        <!-- Orders Section -->
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
                            <th>Payment</th>
                            <th>Status</th>
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
                            <td><?php echo ucfirst($order['payment_method']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
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
    
    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Order Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>
    
    <script>
        // Store orders data for JavaScript
        const orders = <?php echo json_encode($orders); ?>;
        
        function viewOrder(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
            
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
                            <span class="detail-label">Status:</span>
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
            
            // Load order items
            loadOrderItems(orderId);
            
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function updateOrder(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Update Order #${String(order.order_id).padStart(8, '0')}`;
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="order-details">
                    <div class="detail-group">
                        <h4>Current Status: <span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span></h4>
                    </div>
                </div>
                
                <form method="POST" class="update-form">
                    <input type="hidden" name="order_id" value="${order.order_id}">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">New Status</label>
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