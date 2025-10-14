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
</head>
<body>
     <div class="header">
        <div class="left-section">
            <div class="lc-logo">
                <a href="adminIndex.php">
                    <img src="images/lcLogo.png" alt="LC Logo">
                </a>
            </div>
            <div class="dashboard-info">
                <h3>Dashboard</h3>
                <p>Home</p>
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
        
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>