<?php 
session_start(); 
include 'admin_header.php'; 

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sale_type = $_GET['sale_type'] ?? 'online';

// Handle order status updates (online orders only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    $tracking_number = $_POST['tracking_number'] ?? '';
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, tracking_number = ? WHERE order_id = ?");
    $stmt->bind_param("ssi", $new_status, $tracking_number, $order_id);
    
    if ($stmt->execute()) {
        $success_message = "Order status updated successfully";
        header("Location: adminOrders.php?status=" . urlencode($status_filter) . "&sale_type=" . urlencode($sale_type));
        exit();
    } else {
        $error_message = "Failed to update order status";
    }
    
    $stmt->close();
}


$all_transactions = [];

// Fetch ONLINE ORDERS
if ($sale_type === 'online' || $sale_type === 'all') {
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

    $query = "SELECT 
                o.order_id as id,
                'online' as transaction_type,
                o.full_name as customer_name,
                o.user_email,
                o.phone as contact,
                o.address,
                o.city,
                o.postal_code,
                o.total_amount,
                o.payment_method,
                COALESCE(o.payment_status, 'pending_verification') as payment_status,
                o.status,
                o.order_date as transaction_date,
                o.tracking_number,
                o.order_notes,
                'N/A' as created_by
              FROM orders o 
              {$where_clause} 
              ORDER BY o.order_date DESC";
              
    $stmt = $conn->prepare($query);

    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $online_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // ✅ DEBUG CODE - MOVED TO CORRECT POSITION (after query executes)
    if (!empty($online_orders)) {
        echo "<!-- DEBUG First Order Payment Status: '" . 
            htmlspecialchars($online_orders[0]['payment_status'] ?? 'NULL') . "' -->";
        echo "<!-- DEBUG First Order ID: " . $online_orders[0]['id'] . " -->";
        echo "<!-- DEBUG Total Orders Fetched: " . count($online_orders) . " -->";
    }
    
    $all_transactions = array_merge($all_transactions, $online_orders);
    $stmt->close();
}

// Fetch WALK-IN SALES
if ($sale_type === 'walkin' || $sale_type === 'all') {
    $walkin_where = [];
    
    if (!empty($search)) {
        $walkin_where[] = "(ws.customer_name LIKE '%{$search}%' OR ws.sale_id LIKE '%{$search}%')";
    }
    
    if (!empty($date_from)) {
        $walkin_where[] = "DATE(ws.sale_date) >= '{$date_from}'";
    }
    
    if (!empty($date_to)) {
        $walkin_where[] = "DATE(ws.sale_date) <= '{$date_to}'";
    }
    
    $walkin_where_clause = '';
    if (!empty($walkin_where)) {
        $walkin_where_clause = 'WHERE ' . implode(' AND ', $walkin_where);
    }
    
    $walkin_query = "SELECT 
                        ws.sale_id as id,
                        'walkin' as transaction_type,
                        ws.customer_name,
                        'Walk-in Customer' as user_email,
                        'N/A' as contact,
                        ws.total_amount,
                        ws.payment_method,
                        'verified' as payment_status,
                        'completed' as status,
                        ws.sale_date as transaction_date,
                        'N/A' as tracking_number,
                        ws.created_by,
                        ws.notes as order_notes
                     FROM walkin_sales ws
                     {$walkin_where_clause}
                     ORDER BY ws.sale_date DESC";
    
    $walkin_sales = $conn->query($walkin_query)->fetch_all(MYSQLI_ASSOC);
    $all_transactions = array_merge($all_transactions, $walkin_sales);
}

// Sort all transactions by date
usort($all_transactions, function($a, $b) {
    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
});

renderAdminHeader();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminOrderStyle.css">
    <title>Sales Management - Admin Panel</title>
</head>
<body>
    <div class="admin-container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <!-- Sale Type Tabs -->
        <div class="sale-type-tabs">
            <button class="tab-btn <?php echo $sale_type === 'online' ? 'active' : ''; ?>" 
                    onclick="switchTab('online')">
                <i class='bx bx-globe'></i> Online Orders
            </button>
            <button class="tab-btn <?php echo $sale_type === 'walkin' ? 'active' : ''; ?>" 
                    onclick="switchTab('walkin')">
                <i class='bx bx-store'></i> Walk-in Sales
            </button>
            <button class="tab-btn <?php echo $sale_type === 'all' ? 'active' : ''; ?>" 
                    onclick="switchTab('all')">
                <i class='bx bx-list-ul'></i> All Transactions
            </button>
        </div>
        
        <div class="filters-section">
            <form method="GET">
                <input type="hidden" name="sale_type" value="<?php echo $sale_type; ?>">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" 
                               placeholder="Customer name, ID..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <?php if ($sale_type === 'online' || $sale_type === 'all'): ?>
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
                        </select>
                    </div>
                    <?php endif; ?>
                    
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
                <h3>
                    <?php 
                    if ($sale_type === 'online') echo 'Online Orders';
                    elseif ($sale_type === 'walkin') echo 'Walk-in Sales';
                    else echo 'All Transactions';
                    ?> 
                    (<?php echo count($all_transactions); ?>)
                </h3>
            </div>
            
            <?php if (empty($all_transactions)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <h4>No transactions found</h4>
                    <p>No transactions match your current filters.</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_transactions as $transaction): ?>
                        <tr>
                            <td>
                                <span class="transaction-type-badge badge-<?php echo $transaction['transaction_type']; ?>">
                                    <?php echo $transaction['transaction_type'] === 'online' ? '🌐 ONLINE' : '🏪 WALK-IN'; ?>
                                </span>
                            </td>
                            <td>
                                <strong>
                                    <?php 
                                    echo $transaction['transaction_type'] === 'online' 
                                        ? '#' . str_pad($transaction['id'], 8, '0', STR_PAD_LEFT)
                                        : 'WI-' . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT);
                                    ?>
                                </strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($transaction['customer_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($transaction['user_email']); ?></small>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?></td>
                            <td><strong>₱<?php echo number_format($transaction['total_amount'], 2); ?></strong></td>
                            <td>
                                <div class="payment-info">
                                    <span class="payment-method-display">
                                        <?php 
                                        $payment_display = strtoupper($transaction['payment_method']);
                                        if ($transaction['transaction_type'] === 'walkin' && $payment_display === 'COD') {
                                            $payment_display = 'CASH';
                                        }
                                        echo $payment_display;
                                        ?>
                                    </span>
                                    <?php if ($transaction['transaction_type'] === 'online'): ?>
                                        <?php
                                        // Get payment status with proper handling
                                        $payment_status = $transaction['payment_status'] ?? 'pending_verification';
                                        $payment_status = strtolower(trim($payment_status));
                                        
                                        // Map status to display badge
                                        switch($payment_status) {
                                            case 'verified':
                                                $status_class = 'verified';
                                                $status_icon = 'Verified';
                                                break;
                                            case 'pending_verification':
                                            case 'pending':
                                            case '':
                                                $status_class = 'pending';
                                                $status_icon = 'Pending';
                                                break;
                                            case 'failed':
                                                $status_class = 'failed';
                                                $status_icon = 'Failed';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'cancelled';
                                                $status_icon = 'Cancelled';
                                                break;
                                            default:
                                                $status_class = 'pending';
                                                $status_icon = '⏳ ' . ucfirst($payment_status);
                                        }
                                        ?>
                                        <span class="payment-status-badge payment-<?php echo $status_class; ?>">
                                            <?php echo $status_icon; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="payment-status-badge payment-verified">✓ Paid</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($transaction['transaction_type'] === 'online'): ?>
                                    <span class="status-badge status-<?php echo $transaction['status']; ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-delivered">Completed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info" onclick="viewTransaction('<?php echo $transaction['transaction_type']; ?>', <?php echo $transaction['id']; ?>)">
                                        View
                                    </button>
                                    <?php if ($transaction['transaction_type'] === 'online'): ?>
                                        <button class="btn btn-primary" onclick="updateOrder(<?php echo $transaction['id']; ?>)">
                                            Update
                                        </button>
                                    <?php endif; ?>
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
                <h3 id="modalTitle">Transaction Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>
    
    <script>
        const allTransactions = <?php echo json_encode($all_transactions); ?>;
        
        function switchTab(type) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sale_type', type);
            if (type === 'walkin') {
                urlParams.delete('status');
                urlParams.delete('payment_status');
            }
            window.location.href = '?' + urlParams.toString();
        }
        
        function viewTransaction(type, id) {
            if (type === 'online') {
                viewOrder(id);
            } else {
                viewWalkInSale(id);
            }
        }
        
        async function viewWalkInSale(saleId) {
            try {
                const response = await fetch(`get_walkin_sale_details.php?sale_id=${saleId}`);
                const data = await response.json();
                
                if (data.success) {
                    const sale = data.sale;
                    const items = data.items;
                    
                    let paymentMethodDisplay = sale.payment_method.toUpperCase();
                    if (paymentMethodDisplay === 'COD') {
                        paymentMethodDisplay = 'CASH';
                    }
                    
                    document.getElementById('modalTitle').textContent = `Walk-in Sale WI-${String(sale.sale_id).padStart(6, '0')}`;
                    
                    let itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;"><thead><tr style="background: #f8f9fa;"><th style="padding: 10px; text-align: left;">Product</th><th style="padding: 10px;">Qty</th><th style="padding: 10px;">Price</th><th style="padding: 10px;">Subtotal</th></tr></thead><tbody>';
                    
                    items.forEach(item => {
                        itemsHtml += `
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;">${item.product_name}</td>
                                <td style="padding: 10px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    itemsHtml += '</tbody></table>';
                    
                    document.getElementById('modalBody').innerHTML = `
                        <div class="order-details">
                            <div class="detail-group">
                                <h4>Sale Information</h4>
                                <div class="detail-row">
                                    <span class="detail-label">Customer:</span>
                                    <span>${sale.customer_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date:</span>
                                    <span>${new Date(sale.sale_date).toLocaleString()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Payment Method:</span>
                                    <span>${paymentMethodDisplay}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Created By:</span>
                                    <span>${sale.created_by}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Total Amount:</span>
                                    <span style="font-size: 20px; font-weight: bold; color: #4CAF50;">₱${parseFloat(sale.total_amount).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Items Sold</h4>
                        ${itemsHtml}
                        
                        ${sale.notes ? `
                        <div class="detail-group">
                            <h4>Notes</h4>
                            <p>${sale.notes}</p>
                        </div>
                        ` : ''}
                    `;
                    
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    alert('Error loading sale details');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function viewOrder(orderId) {
            try {
                const response = await fetch(`get_order_details.php?order_id=${orderId}`);
                const data = await response.json();
                
                if (data.success) {
                    const order = data.order;
                    const items = data.items;
                    
                    document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
                    
                    let itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;"><thead><tr style="background: #f8f9fa;"><th style="padding: 10px; text-align: left;">Product</th><th style="padding: 10px;">Qty</th><th style="padding: 10px;">Price</th><th style="padding: 10px;">Subtotal</th></tr></thead><tbody>';
                    
                    items.forEach(item => {
                        itemsHtml += `
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;">${item.product_name}</td>
                                <td style="padding: 10px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.product_price).toFixed(2)}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    itemsHtml += '</tbody></table>';
                    
                    let paymentStatusBadge = '';
                    const paymentStatus = order.payment_status || 'pending_verification';
                    
                    if (paymentStatus === 'verified') {
                        paymentStatusBadge = '<span class="payment-status-badge payment-verified">✓ VERIFIED</span>';
                    } else if (paymentStatus === 'pending_verification') {
                        paymentStatusBadge = '<span class="payment-status-badge payment-pending">⏳ PENDING VERIFICATION</span>';
                    } else if (paymentStatus === 'failed') {
                        paymentStatusBadge = '<span class="payment-status-badge payment-failed">✗ FAILED</span>';
                    } else if (paymentStatus === 'cancelled') {
                        paymentStatusBadge = '<span class="payment-status-badge payment-cancelled">⊘ CANCELLED</span>';
                    }
                    
                    let trackingDisplay = '';
                    if (order.status === 'shipped' || order.status === 'delivered') {
                        if (order.tracking_number) {
                            trackingDisplay = `
                                <div class="jnt-tracking-section">
                                    <div style="margin-bottom: 8px;">
                                        <span class="detail-label">📦 J&T Express Tracking Number:</span>
                                    </div>
                                    <div style="font-family: monospace; font-size: 16px; font-weight: bold; color: #e74c3c; background: white; padding: 10px; border-radius: 4px;">
                                        ${order.tracking_number}
                                    </div>
                                    <a href="https://www.jtexpress.ph/trajectoryQuery?waybillNo=${encodeURIComponent(order.tracking_number)}" 
                                    target="_blank" 
                                    class="jnt-track-link">
                                        <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        Track on J&T Express
                                    </a>
                                    <div style="margin-top: 10px; font-size: 11px; color: #666;">
                                        Customer can track their package using this number on J&T Express website
                                    </div>
                                </div>
                            `;
                        } else {
                            trackingDisplay = `
                                <div class="jnt-instruction">
                                    <strong>⚠️ Tracking Number Required</strong>
                                    <p style="margin: 5px 0;">Please go to your nearest J&T Express branch to get the waybill and tracking number for this order.</p>
                                    <p style="margin: 5px 0 0 0; font-size: 11px;">Once you have the tracking number, use the "Update" button to add it to this order.</p>
                                </div>
                            `;
                        }
                    } else {
                        trackingDisplay = `
                            <div class="detail-row">
                                <span class="detail-label">Tracking Number:</span>
                                <span style="color: #999; font-style: italic;">Will be assigned when order status is changed to "Shipped"</span>
                            </div>
                            <div class="jnt-instruction">
                                <strong>📋 Next Steps:</strong>
                                <p style="margin: 5px 0 0 0;">When ready to ship, go to J&T Express branch to get the waybill, then update order status to "Shipped" and add the tracking number.</p>
                            </div>
                        `;
                    }
                    
                    document.getElementById('modalBody').innerHTML = `
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
                                    <span class="detail-label">Address:</span>
                                    <span>${order.address}, ${order.city} ${order.postal_code}</span>
                                </div>
                            </div>
                            
                            <div class="detail-group">
                                <h4>Order Information</h4>
                                <div class="detail-row">
                                    <span class="detail-label">Order Date:</span>
                                    <span>${new Date(order.order_date).toLocaleString()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Payment Status:</span>
                                    ${paymentStatusBadge}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Payment Method:</span>
                                    <span>${order.payment_method.toUpperCase()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Courier Service:</span>
                                    <span style="font-weight: 600; color: #e74c3c;">📦 J&T Express</span>
                                </div>
                                ${trackingDisplay}
                                <div class="detail-row" style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #f0f0f0;">
                                    <span class="detail-label">Total Amount:</span>
                                    <span style="font-size: 20px; font-weight: bold; color: #4CAF50;">₱${parseFloat(order.total_amount).toFixed(2)}</span>
                                </div>
                            </div>
                        </div>
                        
                        <h4>Order Items</h4>
                        ${itemsHtml}
                        
                        ${order.order_notes ? `
                        <div class="detail-group">
                            <h4>Order Notes</h4>
                            <p>${order.order_notes}</p>
                        </div>
                        ` : ''}
                    `;
                    
                    document.getElementById('orderModal').style.display = 'block';
                } else {
                    alert('Error loading order details');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        function updateOrder(orderId) {
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const order = data.order;
                        
                        document.getElementById('modalTitle').textContent = `Update Order #${String(order.order_id).padStart(8, '0')}`;
                        
                        const showTrackingHelp = order.status === 'processing' || order.status === 'shipped' || order.status === 'delivered';
                        
                        let paymentStatusDisplay = '';
                        const paymentStatus = order.payment_status || 'pending_verification';
                        
                        if (paymentStatus === 'verified') {
                            paymentStatusDisplay = '<span class="payment-status-badge payment-verified">✓ VERIFIED</span>';
                        } else if (paymentStatus === 'pending_verification') {
                            paymentStatusDisplay = '<span class="payment-status-badge payment-pending">⏳ PENDING</span>';
                        } else if (paymentStatus === 'failed') {
                            paymentStatusDisplay = '<span class="payment-status-badge payment-failed">✗ FAILED</span>';
                        }
                        
                        document.getElementById('modalBody').innerHTML = `
                            <div class="order-details">
                                <div class="detail-group">
                                    <h4>Current Order Status</h4>
                                    <div class="detail-row">
                                        <span class="detail-label">Customer:</span>
                                        <span>${order.full_name}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Payment Status:</span>
                                        ${paymentStatusDisplay}
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Current Status:</span>
                                        <span class="status-badge status-${order.status}">${order.status.toUpperCase()}</span>
                                    </div>
                                    <div class="detail-row">
                                        <span class="detail-label">Tracking:</span>
                                        <span>${order.tracking_number || 'Not assigned'}</span>
                                    </div>
                                </div>
                                
                                ${showTrackingHelp ? `
                                <div class="jnt-instruction" style="margin: 15px 0;">
                                    <strong>📦 J&T Express Tracking Instructions:</strong>
                                    <ol style="margin: 8px 0; padding-left: 20px; font-size: 12px;">
                                        <li>Go to your nearest J&T Express branch</li>
                                        <li>Request a waybill for this order</li>
                                        <li>Get the tracking number from the waybill</li>
                                        <li>Enter the tracking number below and update status to "Shipped"</li>
                                    </ol>
                                    <a href="https://www.jtexpress.ph/branch-locator" target="_blank" style="color: #e74c3c; text-decoration: underline; font-size: 12px;">
                                        📍 Find nearest J&T Express branch
                                    </a>
                                </div>
                                ` : ''}
                                
                                <div class="update-form">
                                    <form method="POST" action="adminOrders.php">
                                        <input type="hidden" name="order_id" value="${order.order_id}">
                                        <input type="hidden" name="update_status" value="1">
                                        
                                        <label for="status">Update Status:</label>
                                        <select name="status" id="status" required onchange="toggleTrackingField(this.value)">
                                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                                            <option value="confirmed" ${order.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                                            <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                                            <option value="shipped" ${order.status === 'shipped' ? 'selected' : ''}>Shipped</option>
                                            <option value="delivered" ${order.status === 'delivered' ? 'selected' : ''}>Delivered</option>
                                            <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                        </select>
                                        
                                        <div id="trackingFieldContainer">
                                            <label for="tracking_number">J&T Express Tracking Number ${showTrackingHelp ? '(Required for Shipped status)' : '(Optional)'}:</label>
                                            <input type="text" 
                                                name="tracking_number" 
                                                id="tracking_number" 
                                                placeholder="Enter J&T tracking number (e.g., JT1234567890)"
                                                value="${order.tracking_number || ''}"
                                                style="text-transform: uppercase;">
                                            <small style="display: block; margin-top: 5px; color: #666;">
                                                Get this from your J&T Express waybill
                                            </small>
                                        </div>
                                        
                                        <button type="submit">Update Order</button>
                                    </form>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('orderModal').style.display = 'block';
                    } else {
                        alert('Error loading order details');
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        function toggleTrackingField(status) {
            const trackingContainer = document.getElementById('trackingFieldContainer');
            const trackingInput = document.getElementById('tracking_number');
            
            if (status === 'shipped' || status === 'delivered') {
                trackingContainer.style.display = 'block';
                trackingInput.required = true;
            } else {
                trackingContainer.style.display = 'block';
                trackingInput.required = false;
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
<?php $conn->close(); ?>