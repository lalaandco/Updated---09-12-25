<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.php');
    exit();
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'sales';

// Sales Report Data
if ($report_type === 'sales') {
    $sales_query = "
        SELECT 
            DATE(o.order_date) as sale_date,
            COUNT(o.order_id) as total_orders,
            SUM(CASE WHEN o.payment_status = 'verified' THEN o.total_amount ELSE 0 END) as verified_revenue,
            SUM(CASE WHEN o.payment_status = 'pending_verification' THEN o.total_amount ELSE 0 END) as pending_revenue,
            SUM(o.total_amount) as total_revenue,
            SUM(CASE WHEN o.payment_status = 'verified' THEN 1 ELSE 0 END) as verified_orders,
            SUM(CASE WHEN o.payment_status = 'pending_verification' THEN 1 ELSE 0 END) as pending_orders,
            GROUP_CONCAT(DISTINCT o.payment_method) as payment_methods
        FROM orders o
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        AND o.status != 'cancelled'
        GROUP BY DATE(o.order_date)
        ORDER BY sale_date DESC
    ";
    
    $stmt = $conn->prepare($sales_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $sales_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Top selling products
    $top_products_query = "
        SELECT 
            oi.product_name,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as total_revenue,
            SUM(CASE WHEN o.payment_status = 'verified' THEN oi.quantity ELSE 0 END) as verified_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        AND o.status != 'cancelled'
        GROUP BY oi.product_id, oi.product_name
        ORDER BY total_sold DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($top_products_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Total summary
    $summary_query = "
        SELECT 
            COUNT(DISTINCT CASE WHEN o.payment_status = 'verified' THEN o.order_id END) as verified_orders,
            COUNT(DISTINCT CASE WHEN o.payment_status = 'pending_verification' THEN o.order_id END) as pending_orders,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(CASE WHEN o.payment_status = 'verified' THEN o.total_amount ELSE 0 END) as verified_revenue,
            SUM(CASE WHEN o.payment_status = 'pending_verification' THEN o.total_amount ELSE 0 END) as pending_revenue,
            SUM(o.total_amount) as total_revenue,
            AVG(o.total_amount) as average_order_value,
            SUM(oi.quantity) as total_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        AND o.status != 'cancelled'
    ";
    
    $stmt = $conn->prepare($summary_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
}

// Purchase/Inventory Report Data
if ($report_type === 'purchase') {
    $purchase_query = "
        SELECT 
            DATE(it.created_at) as transaction_date,
            it.transaction_type,
            COUNT(it.transaction_id) as transaction_count,
            SUM(it.quantity) as total_quantity,
            GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') as products
        FROM inventory_transactions it
        JOIN product_tbl p ON it.product_id = p.product_id
        WHERE DATE(it.created_at) BETWEEN ? AND ?
        AND it.transaction_type IN ('restock', 'move_to_display')
        GROUP BY DATE(it.created_at), it.transaction_type
        ORDER BY transaction_date DESC
    ";
    
    $stmt = $conn->prepare($purchase_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $purchase_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Summary
    $purchase_summary_query = "
        SELECT 
            transaction_type,
            COUNT(transaction_id) as count,
            SUM(quantity) as total_quantity
        FROM inventory_transactions
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND transaction_type IN ('restock', 'move_to_display')
        GROUP BY transaction_type
    ";
    
    $stmt = $conn->prepare($purchase_summary_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $purchase_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Stock Report Data
if ($report_type === 'stock') {
    $stock_query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.category,
            p.inventory_quantity,
            p.display_quantity,
            (p.inventory_quantity + p.display_quantity) as total_stock,
            p.product_price,
            (p.inventory_quantity + p.display_quantity) * p.product_price as stock_value
        FROM product_tbl p
        ORDER BY total_stock ASC
    ";
    
    $stock_data = $conn->query($stock_query)->fetch_all(MYSQLI_ASSOC);
    
    // Stock summary by category
    $category_stock_query = "
        SELECT 
            category,
            COUNT(product_id) as product_count,
            SUM(inventory_quantity) as total_warehouse,
            SUM(display_quantity) as total_display,
            SUM(inventory_quantity + display_quantity) as total_stock,
            SUM((inventory_quantity + display_quantity) * product_price) as total_value
        FROM product_tbl
        GROUP BY category
    ";
    
    $category_stock = $conn->query($category_stock_query)->fetch_all(MYSQLI_ASSOC);
    
    // Low stock alerts
    $low_stock_query = "
        SELECT 
            product_name,
            inventory_quantity,
            display_quantity,
            (inventory_quantity + display_quantity) as total_stock
        FROM product_tbl
        WHERE display_quantity < 20 OR inventory_quantity < 30
        ORDER BY total_stock ASC
    ";
    
    $low_stock = $conn->query($low_stock_query)->fetch_all(MYSQLI_ASSOC);
}

// Payment Verification Report
if ($report_type === 'payments') {
    $payment_summary_query = "
        SELECT 
            pv.verification_status,
            COUNT(pv.verification_id) as count,
            SUM(o.total_amount) as total_amount,
            AVG(o.total_amount) as avg_amount
        FROM payment_verifications pv
        JOIN orders o ON pv.order_id = o.order_id
        WHERE DATE(pv.submitted_at) BETWEEN ? AND ?
        GROUP BY pv.verification_status
    ";
    
    $stmt = $conn->prepare($payment_summary_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $payment_summary = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $payment_details_query = "
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
        WHERE DATE(pv.submitted_at) BETWEEN ? AND ?
        ORDER BY pv.submitted_at DESC
    ";
    
    $stmt = $conn->prepare($payment_details_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $payment_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Reports - Admin Dashboard</title>
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .report-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: #f5f5f5;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #4CAF50;
            color: white;
        }
        
        .date-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filters input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .summary-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .summary-card .subtext {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }
        
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-low {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-medium {
            background: #fff3e0;
            color: #ef6c00;
        }
        
        .badge-good {
            background: #e8f5e9;
            color: #2e7d32;
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
            <a href="adminIndex.php" style="color: #666; text-decoration: none;">
                <i class='bx bx-arrow-back'></i> Back to Dashboard
            </a>
        </div>
        
        <div class="center-section">
            <div class="welcome-text">REPORTS</div>
        </div>
        
        <div class="right-section">
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Admin</span>
            </div>
        </div>
    </div>

    <div class="reports-container">
        <div class="report-header">
            <div class="report-tabs">
                <button class="tab-btn <?php echo $report_type === 'sales' ? 'active' : ''; ?>" 
                        onclick="switchReport('sales')">
                    <i class='bx bx-purchase-tag'></i> Sales Report
                </button>
                <button class="tab-btn <?php echo $report_type === 'purchase' ? 'active' : ''; ?>" 
                        onclick="switchReport('purchase')">
                    <i class='bx bx-bar-chart-alt-2'></i> Purchase Report
                </button>
                <button class="tab-btn <?php echo $report_type === 'stock' ? 'active' : ''; ?>" 
                        onclick="switchReport('stock')">
                    <i class='bx bx-trending-up'></i> Stock Report
                </button>
                <button class="tab-btn <?php echo $report_type === 'payments' ? 'active' : ''; ?>" 
                        onclick="switchReport('payments')">
                    <i class='bx bx-wallet-alt'></i> Payment Verification
                </button>
            </div>
            
            <form method="GET" class="date-filters">
                <input type="hidden" name="report_type" value="<?php echo $report_type; ?>">
                <label>From:</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                <label>To:</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                <button type="submit" class="btn-primary" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    <i class='bx bx-filter'></i> Filter
                </button>
                <button type="button" class="btn-primary" onclick="window.print()" style="background: #2196F3; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    <i class='bx bx-printer'></i> Print
                </button>
            </form>
        </div>

        <!-- SALES REPORT -->
        <?php if ($report_type === 'sales'): ?>
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Verified Revenue</h3>
                    <div class="value">₱<?php echo number_format($summary['verified_revenue'] ?? 0, 2); ?></div>
                    <div class="subtext"><?php echo $summary['verified_orders'] ?? 0; ?> verified orders</div>
                </div>
                <div class="summary-card">
                    <h3>Pending Revenue</h3>
                    <div class="value">₱<?php echo number_format($summary['pending_revenue'] ?? 0, 2); ?></div>
                    <div class="subtext"><?php echo $summary['pending_orders'] ?? 0; ?> awaiting verification</div>
                </div>
                <div class="summary-card">
                    <h3>Total Orders</h3>
                    <div class="value"><?php echo $summary['total_orders'] ?? 0; ?></div>
                    <div class="subtext">All orders in period</div>
                </div>
                <div class="summary-card">
                    <h3>Average Order Value</h3>
                    <div class="value">₱<?php echo number_format($summary['average_order_value'] ?? 0, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Items Sold</h3>
                    <div class="value"><?php echo $summary['total_items_sold'] ?? 0; ?></div>
                </div>
            </div>

            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Top Selling Products</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Units Sold</th>
                            <th>Verified Sales</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo $product['total_sold']; ?></td>
                            <td><?php echo $product['verified_sold']; ?></td>
                            <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Daily Sales by Payment Status</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Total Orders</th>
                            <th>Verified Orders</th>
                            <th>Pending Orders</th>
                            <th>Verified Revenue</th>
                            <th>Pending Revenue</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $sale): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                            <td><?php echo $sale['total_orders']; ?></td>
                            <td><?php echo $sale['verified_orders']; ?></td>
                            <td><?php echo $sale['pending_orders']; ?></td>
                            <td>₱<?php echo number_format($sale['verified_revenue'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($sale['pending_revenue'] ?? 0, 2); ?></td>
                            <td>₱<?php echo number_format($sale['total_revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- PURCHASE REPORT -->
        <?php elseif ($report_type === 'purchase'): ?>
            <div class="summary-cards">
                <?php foreach ($purchase_summary as $summary): ?>
                <div class="summary-card">
                    <h3><?php echo ucfirst(str_replace('_', ' ', $summary['transaction_type'])); ?></h3>
                    <div class="value"><?php echo $summary['total_quantity']; ?> units</div>
                    <div class="subtext"><?php echo $summary['count']; ?> transactions</div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Inventory Transactions</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Transactions</th>
                            <th>Total Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchase_data as $purchase): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($purchase['transaction_date'])); ?></td>
                            <td><span class="status-badge badge-good"><?php echo ucfirst(str_replace('_', ' ', $purchase['transaction_type'])); ?></span></td>
                            <td><?php echo $purchase['transaction_count']; ?></td>
                            <td><?php echo $purchase['total_quantity']; ?> units</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- STOCK REPORT -->
        <?php elseif ($report_type === 'stock'): ?>
            <div class="summary-cards">
                <?php foreach ($category_stock as $cat): ?>
                <div class="summary-card">
                    <h3><?php echo $cat['category']; ?></h3>
                    <div class="value"><?php echo $cat['total_stock']; ?> units</div>
                    <div class="subtext">₱<?php echo number_format($cat['total_value'], 2); ?> value</div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($low_stock)): ?>
            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #fff3e0; color: #ef6c00;">
                    <i class='bx bx-error'></i> Low Stock Alerts
                </h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Warehouse</th>
                            <th>Display</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo $item['inventory_quantity']; ?></td>
                            <td><?php echo $item['display_quantity']; ?></td>
                            <td><?php echo $item['total_stock']; ?></td>
                            <td>
                                <?php if ($item['total_stock'] < 20): ?>
                                    <span class="status-badge badge-low">Critical</span>
                                <?php else: ?>
                                    <span class="status-badge badge-medium">Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">All Products Stock</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Warehouse</th>
                            <th>Display</th>
                            <th>Total Stock</th>
                            <th>Stock Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_data as $stock): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stock['product_name']); ?></td>
                            <td><?php echo $stock['category']; ?></td>
                            <td><?php echo $stock['inventory_quantity']; ?></td>
                            <td><?php echo $stock['display_quantity']; ?></td>
                            <td><?php echo $stock['total_stock']; ?></td>
                            <td>₱<?php echo number_format($stock['stock_value'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <!-- PAYMENT VERIFICATION REPORT -->
        <?php elseif ($report_type === 'payments'): ?>
            <div class="summary-cards">
                <?php foreach ($payment_summary as $summary): ?>
                <div class="summary-card">
                    <h3><?php echo ucfirst($summary['verification_status']); ?></h3>
                    <div class="value"><?php echo $summary['count']; ?></div>
                    <div class="subtext">₱<?php echo number_format($summary['total_amount'] ?? 0, 2); ?> total</div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="data-table">
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Payment Verification Details</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Verified By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_details as $payment): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($payment['order_id'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                            <td>₱<?php echo number_format($payment['total_amount'], 2); ?></td>
                            <td>
                                <code style="background: #f5f5f5; padding: 4px 6px; border-radius: 3px; font-size: 11px;">
                                    <?php echo htmlspecialchars($payment['gcash_transaction_id']); ?>
                                </code>
                            </td>
                            <td>
                                <span class="status-badge badge-<?php echo $payment['verification_status']; ?>">
                                    <?php 
                                    echo $payment['verification_status'] === 'pending' ? '⏳ Pending' : 
                                        ($payment['verification_status'] === 'verified' ? '✓ Verified' : 
                                        ($payment['verification_status'] === 'failed' ? '✗ Failed' : 'Resubmit'));
                                    ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($payment['submitted_at'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['verified_by'] ?? 'Pending'); ?></td>
                            <td><?php echo $payment['verified_at'] ? date('M d, Y', strtotime($payment['verified_at'])) : 'N/A'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function switchReport(type) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('report_type', type);
            window.location.href = '?' + urlParams.toString();
        }
    </script>
</body>
</html>