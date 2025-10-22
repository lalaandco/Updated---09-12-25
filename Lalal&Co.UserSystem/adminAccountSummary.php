<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Get filter parameters
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'total_spent';

// Build query for customer accounts
$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.address,
        u.contact_number,
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) as total_spent,
        COALESCE(SUM(CASE WHEN o.status != 'cancelled' THEN oi.quantity ELSE 0 END), 0) as total_items_purchased,
        MAX(o.order_date) as last_order_date,
        COALESCE(AVG(CASE WHEN o.status != 'cancelled' THEN o.total_amount END), 0) as avg_order_value
    FROM users u
    LEFT JOIN orders o ON u.email = o.user_email
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
";

if (!empty($search)) {
    $query .= " WHERE u.name LIKE ? OR u.email LIKE ?";
}

$query .= " GROUP BY u.id, u.name, u.email, u.address, u.contact_number";

// Add sorting
switch ($sort_by) {
    case 'name':
        $query .= " ORDER BY u.name ASC";
        break;
    case 'orders':
        $query .= " ORDER BY total_orders DESC";
        break;
    case 'recent':
        $query .= " ORDER BY last_order_date DESC";
        break;
    case 'total_spent':
    default:
        $query .= " ORDER BY total_spent DESC";
        break;
}

$stmt = $conn->prepare($query);

if (!empty($search)) {
    $search_param = "%{$search}%";
    $stmt->bind_param("ss", $search_param, $search_param);
}

$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT u.id) as total_customers,
        COUNT(DISTINCT o.order_id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_revenue,
        COALESCE(AVG(o.total_amount), 0) as avg_order_value
    FROM users u
    LEFT JOIN orders o ON u.email = o.user_email
    WHERE o.status != 'cancelled' OR o.status IS NULL
";
$stats = $conn->query($stats_query)->fetch_assoc();

renderAdminHeader()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Account Summary - Admin Panel</title>
    <style>
        .accounts-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-box h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-box .value {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
        }

        .controls-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .controls-bar input,
        .controls-bar select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .controls-bar input {
            flex: 1;
            min-width: 250px;
        }

        .controls-bar button {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .customers-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .customers-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .customers-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }

        .customers-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        .customers-table tr:hover {
            background: #f8f9fa;
        }

        .customer-name {
            font-weight: 600;
            color: #333;
        }

        .customer-email {
            font-size: 12px;
            color: #666;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-vip {
            background: #ffd700;
            color: #333;
        }

        .badge-regular {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-new {
            background: #e3f2fd;
            color: #1976d2;
        }

        .action-btn {
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .action-btn:hover {
            background: #1976d2;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }

        .close-modal:hover {
            color: #333;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h4 {
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-label {
            font-weight: 600;
            width: 180px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="accounts-container">
        <div class="stats-overview">
            <div class="stat-box">
                <h3>Total Customers</h3>
                <div class="value"><?php echo number_format($stats['total_customers']); ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($stats['total_orders']); ?></div>
            </div>
            <div class="stat-box">
                <h3>Total Revenue</h3>
                <div class="value">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
            </div>
            <div class="stat-box">
                <h3>Avg Order Value</h3>
                <div class="value">₱<?php echo number_format($stats['avg_order_value'], 2); ?></div>
            </div>
        </div>

        <div class="controls-bar">
            <form method="GET" style="display: flex; gap: 15px; flex: 1; flex-wrap: wrap;">
                <input type="text" name="search" placeholder="Search by name or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="sort_by">
                    <option value="total_spent" <?php echo $sort_by === 'total_spent' ? 'selected' : ''; ?>>
                        Highest Spender
                    </option>
                    <option value="orders" <?php echo $sort_by === 'orders' ? 'selected' : ''; ?>>
                        Most Orders
                    </option>
                    <option value="recent" <?php echo $sort_by === 'recent' ? 'selected' : ''; ?>>
                        Recent Activity
                    </option>
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>
                        Name (A-Z)
                    </option>
                </select>
                
                <button type="submit">
                    <i class='bx bx-filter'></i> Filter
                </button>
            </form>

            <button onclick="window.print()" style="background: #2196F3;">
                <i class='bx bx-printer'></i> Print
            </button>
        </div>

        <div class="customers-table">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Total Orders</th>
                        <th>Total Spent</th>
                        <th>Avg Order</th>
                        <th>Items Bought</th>
                        <th>Last Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <i class='bx bx-user-x' style="font-size: 48px; color: #ddd;"></i>
                                <p style="color: #666; margin-top: 10px;">No customers found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                            <?php
                            // Determine customer status
                            $status = 'new';
                            $statusClass = 'badge-new';
                            
                            if ($customer['total_spent'] > 100000) {
                                $status = 'VIP';
                                $statusClass = 'badge-vip';
                            } elseif ($customer['total_orders'] > 0) {
                                $status = 'Regular';
                                $statusClass = 'badge-regular';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="customer-name"><?php echo htmlspecialchars($customer['name']); ?></div>
                                    <div class="customer-email"><?php echo htmlspecialchars($customer['email']); ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($customer['contact_number']); ?></div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo htmlspecialchars(substr($customer['address'], 0, 30)) . '...'; ?>
                                    </div>
                                </td>
                                <td><?php echo $customer['total_orders']; ?></td>
                                <td><strong>₱<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                                <td>₱<?php echo number_format($customer['avg_order_value'], 2); ?></td>
                                <td><?php echo $customer['total_items_purchased']; ?> items</td>
                                <td>
                                    <?php 
                                    if ($customer['last_order_date']) {
                                        echo date('M d, Y', strtotime($customer['last_order_date']));
                                    } else {
                                        echo '<span style="color: #999;">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                </td>
                                <td>
                                    <button class="action-btn" onclick='viewCustomerDetails(<?php echo json_encode($customer); ?>)'>
                                        <i class='bx bx-show'></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Customer Details</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody"></div>
        </div>
    </div>

    <script>
        function viewCustomerDetails(customer) {
            document.getElementById('modalTitle').textContent = customer.name + ' - Account Details';
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div class="detail-section">
                    <h4>Contact Information</h4>
                    <div class="detail-row">
                        <span class="detail-label">Full Name:</span>
                        <span>${customer.name}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span>${customer.email}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact Number:</span>
                        <span>${customer.contact_number}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address:</span>
                        <span>${customer.address}</span>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>Purchase History</h4>
                    <div class="detail-row">
                        <span class="detail-label">Total Orders:</span>
                        <span><strong>${customer.total_orders}</strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Spent:</span>
                        <span><strong>₱${parseFloat(customer.total_spent).toFixed(2)}</strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Average Order Value:</span>
                        <span>₱${parseFloat(customer.avg_order_value).toFixed(2)}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Items Purchased:</span>
                        <span>${customer.total_items_purchased} items</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Order:</span>
                        <span>${customer.last_order_date ? new Date(customer.last_order_date).toLocaleDateString() : 'Never'}</span>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>Customer Status</h4>
                    <div class="detail-row">
                        <span class="detail-label">Account Type:</span>
                        <span>
                            ${customer.total_spent > 100000 ? '<span class="badge badge-vip">VIP Customer</span>' : 
                              customer.total_orders > 0 ? '<span class="badge badge-regular">Regular Customer</span>' : 
                              '<span class="badge badge-new">New Customer</span>'}
                        </span>
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    <a href="adminOrders.php?search=${encodeURIComponent(customer.email)}" 
                       style="padding: 10px 20px; background: #4CAF50; color: white; border-radius: 5px; text-decoration: none; display: inline-block;">
                        <i class='bx bx-shopping-bag'></i> View Orders
                    </a>
                </div>
            `;
            
            document.getElementById('customerModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('customerModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('customerModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>