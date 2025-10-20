<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sale_type = $_GET['sale_type'] ?? 'online'; // NEW: online, walkin, or all

// Handle order status updates (online orders only)
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
                o.total_amount,
                o.payment_method,
                o.payment_status,
                o.status,
                o.order_date as transaction_date,
                o.tracking_number,
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
                        ws.created_by
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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminOrdersStyle.css">
    <title>Sales Management - Admin Panel</title>
    <style>
        .sale-type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .tab-btn {
            padding: 12px 24px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tab-btn:hover {
            border-color: #4CAF50;
            color: #4CAF50;
        }
        
        .tab-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        .transaction-type-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-online {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-walkin {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
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
    </style>
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
                                <?php echo strtoupper($transaction['payment_method']); ?>
                                <?php if ($transaction['transaction_type'] === 'online'): ?>
                                    <span class="payment-status-badge payment-<?php 
                                        echo $transaction['payment_status'] === 'verified' ? 'verified' : 
                                            ($transaction['payment_status'] === 'pending_verification' ? 'pending' : 'failed'); 
                                    ?>">
                                        <?php 
                                        echo $transaction['payment_status'] === 'verified' ? '✓' : 
                                            ($transaction['payment_status'] === 'pending_verification' ? '⏳' : '✗');
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <span class="payment-status-badge payment-verified">✓</span>
                                <?php endif; ?>
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
                                        👁️ View
                                    </button>
                                    <?php if ($transaction['transaction_type'] === 'online'): ?>
                                        <button class="btn btn-primary" onclick="updateOrder(<?php echo $transaction['id']; ?>)">
                                            ✏️ Update
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
        function switchTab(type) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('sale_type', type);
            // Clear filters that don't apply to walk-in
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
                                    <span>${sale.payment_method.toUpperCase()}</span>
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
        
        // Keep your existing viewOrder and updateOrder functions...
        const orders = <?php echo json_encode(array_filter($all_transactions, function($t) { return $t['transaction_type'] === 'online'; })); ?>;
        
        function viewOrder(orderId) {
            // Your existing viewOrder code from adminOrders.php
            const order = orders.find(o => o.id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.id).padStart(8, '0')} Details`;
            document.getElementById('modalBody').innerHTML = `
                <div class="order-details">
                    <p><strong>Customer:</strong> ${order.customer_name}</p>
                    <p><strong>Email:</strong> ${order.user_email}</p>
                    <p><strong>Total:</strong> ₱${parseFloat(order.total_amount).toFixed(2)}</p>
                    <p><strong>Status:</strong> ${order.status}</p>
                    <p><strong>Payment:</strong> ${order.payment_status}</p>
                </div>
            `;
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function updateOrder(orderId) {
            // Your existing updateOrder code
            window.location.href = `adminOrders.php?update=${orderId}`;
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