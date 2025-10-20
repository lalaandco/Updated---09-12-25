<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'sales';

// Sales Report Data (INCLUDES WALK-IN SALES)
if ($report_type === 'sales') {
    // Get online sales
    $online_sales_query = "
        SELECT 
            DATE(o.order_date) as sale_date,
            'online' as sale_type,
            COUNT(o.order_id) as total_orders,
            SUM(CASE WHEN o.payment_status = 'verified' THEN o.total_amount ELSE 0 END) as verified_revenue,
            SUM(CASE WHEN o.payment_status = 'pending_verification' THEN o.total_amount ELSE 0 END) as pending_revenue,
            SUM(o.total_amount) as total_revenue,
            SUM(CASE WHEN o.payment_status = 'verified' THEN 1 ELSE 0 END) as verified_orders,
            SUM(CASE WHEN o.payment_status = 'pending_verification' THEN 1 ELSE 0 END) as pending_orders
        FROM orders o
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        AND o.status != 'cancelled'
        GROUP BY DATE(o.order_date)
    ";
    
    $stmt = $conn->prepare($online_sales_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $online_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get walk-in sales
    $walkin_sales_query = "
        SELECT 
            DATE(ws.sale_date) as sale_date,
            'walkin' as sale_type,
            COUNT(ws.sale_id) as total_orders,
            SUM(ws.total_amount) as verified_revenue,
            0 as pending_revenue,
            SUM(ws.total_amount) as total_revenue,
            COUNT(ws.sale_id) as verified_orders,
            0 as pending_orders
        FROM walkin_sales ws
        WHERE DATE(ws.sale_date) BETWEEN ? AND ?
        GROUP BY DATE(ws.sale_date)
    ";
    
    $stmt = $conn->prepare($walkin_sales_query);
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $walkin_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Combine both by date
    $combined_sales = [];
    
    foreach ($online_sales as $sale) {
        $date = $sale['sale_date'];
        if (!isset($combined_sales[$date])) {
            $combined_sales[$date] = [
                'sale_date' => $date,
                'total_orders' => 0,
                'verified_revenue' => 0,
                'pending_revenue' => 0,
                'total_revenue' => 0,
                'verified_orders' => 0,
                'pending_orders' => 0,
                'online_orders' => 0,
                'walkin_orders' => 0,
                'online_revenue' => 0,
                'walkin_revenue' => 0
            ];
        }
        $combined_sales[$date]['online_orders'] = $sale['total_orders'];
        $combined_sales[$date]['online_revenue'] = $sale['total_revenue'];
        $combined_sales[$date]['verified_orders'] += $sale['verified_orders'];
        $combined_sales[$date]['pending_orders'] += $sale['pending_orders'];
        $combined_sales[$date]['verified_revenue'] += $sale['verified_revenue'];
        $combined_sales[$date]['pending_revenue'] += $sale['pending_revenue'];
        $combined_sales[$date]['total_revenue'] += $sale['total_revenue'];
        $combined_sales[$date]['total_orders'] += $sale['total_orders'];
    }
    
    foreach ($walkin_sales as $sale) {
        $date = $sale['sale_date'];
        if (!isset($combined_sales[$date])) {
            $combined_sales[$date] = [
                'sale_date' => $date,
                'total_orders' => 0,
                'verified_revenue' => 0,
                'pending_revenue' => 0,
                'total_revenue' => 0,
                'verified_orders' => 0,
                'pending_orders' => 0,
                'online_orders' => 0,
                'walkin_orders' => 0,
                'online_revenue' => 0,
                'walkin_revenue' => 0
            ];
        }
        $combined_sales[$date]['walkin_orders'] = $sale['total_orders'];
        $combined_sales[$date]['walkin_revenue'] = $sale['total_revenue'];
        $combined_sales[$date]['verified_orders'] += $sale['verified_orders'];
        $combined_sales[$date]['verified_revenue'] += $sale['verified_revenue'];
        $combined_sales[$date]['total_revenue'] += $sale['total_revenue'];
        $combined_sales[$date]['total_orders'] += $sale['total_orders'];
    }
    
    $sales_data = array_values($combined_sales);
    usort($sales_data, function($a, $b) {
        return strtotime($b['sale_date']) - strtotime($a['sale_date']);
    });
    
    // Top products (combined)
    $top_products_query = "
        SELECT 
            product_name,
            SUM(total_sold) as total_sold,
            SUM(total_revenue) as total_revenue,
            SUM(verified_sold) as verified_sold
        FROM (
            SELECT 
                oi.product_name,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as total_revenue,
                SUM(CASE WHEN o.payment_status = 'verified' THEN oi.quantity ELSE 0 END) as verified_sold
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE DATE(o.order_date) BETWEEN ? AND ?
            AND o.status != 'cancelled'
            GROUP BY oi.product_name
            
            UNION ALL
            
            SELECT 
                wsi.product_name,
                SUM(wsi.quantity) as total_sold,
                SUM(wsi.subtotal) as total_revenue,
                SUM(wsi.quantity) as verified_sold
            FROM walkin_sale_items wsi
            JOIN walkin_sales ws ON wsi.sale_id = ws.sale_id
            WHERE DATE(ws.sale_date) BETWEEN ? AND ?
            GROUP BY wsi.product_name
        ) as combined_sales
        GROUP BY product_name
        ORDER BY total_sold DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($top_products_query);
    $stmt->bind_param("ssss", $date_from, $date_to, $date_from, $date_to);
    $stmt->execute();
    $top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Summary (combined)
    // Summary (combined) - SIMPLIFIED VERSION
    // Breaking it down to avoid bind_param counting errors
    
    // Get online order counts
    $online_verified_orders = $conn->query("SELECT COUNT(DISTINCT order_id) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled' AND payment_status = 'verified'")->fetch_row()[0];
    $online_pending_orders = $conn->query("SELECT COUNT(DISTINCT order_id) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled' AND payment_status = 'pending_verification'")->fetch_row()[0];
    $online_total_orders = $conn->query("SELECT COUNT(DISTINCT order_id) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled'")->fetch_row()[0];
    
    // Get walk-in counts
    $walkin_orders = $conn->query("SELECT COUNT(DISTINCT sale_id) FROM walkin_sales WHERE DATE(sale_date) BETWEEN '{$date_from}' AND '{$date_to}'")->fetch_row()[0];
    
    // Get online revenues
    $online_verified_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled' AND payment_status = 'verified'")->fetch_row()[0];
    $online_pending_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled' AND payment_status = 'pending_verification'")->fetch_row()[0];
    $online_total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled'")->fetch_row()[0];
    
    // Get walk-in revenue
    $walkin_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM walkin_sales WHERE DATE(sale_date) BETWEEN '{$date_from}' AND '{$date_to}'")->fetch_row()[0];
    
    // Get average order value
    $avg_query = "SELECT COALESCE(AVG(total_amount), 0) FROM (
        SELECT total_amount FROM orders WHERE DATE(order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND status != 'cancelled'
        UNION ALL
        SELECT total_amount FROM walkin_sales WHERE DATE(sale_date) BETWEEN '{$date_from}' AND '{$date_to}'
    ) as all_sales";
    $average_order_value = $conn->query($avg_query)->fetch_row()[0];
    
    // Get total items sold
    $online_items = $conn->query("SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE DATE(o.order_date) BETWEEN '{$date_from}' AND '{$date_to}' AND o.status != 'cancelled'")->fetch_row()[0];
    $walkin_items = $conn->query("SELECT COALESCE(SUM(wsi.quantity), 0) FROM walkin_sale_items wsi JOIN walkin_sales ws ON wsi.sale_id = ws.sale_id WHERE DATE(ws.sale_date) BETWEEN '{$date_from}' AND '{$date_to}'")->fetch_row()[0];
    
    // Combine everything
    $summary = [
        'verified_orders' => $online_verified_orders + $walkin_orders,
        'pending_orders' => $online_pending_orders,
        'total_orders' => $online_total_orders + $walkin_orders,
        'verified_revenue' => $online_verified_revenue + $walkin_revenue,
        'pending_revenue' => $online_pending_revenue,
        'total_revenue' => $online_total_revenue + $walkin_revenue,
        'average_order_value' => $average_order_value,
        'total_items_sold' => $online_items + $walkin_items,
        'walkin_sales_count' => $walkin_orders,
        'walkin_revenue' => $walkin_revenue
    ];
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

renderAdminHeader()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <link rel="stylesheet" href="adminReports.css">
    <title>Reports - Admin Dashboard</title>
</head>
<body>
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
                    <h3>Total Revenue (All Sources)</h3>
                    <div class="value">₱<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                    <div class="subtext">
                        Walk-in: ₱<?php echo number_format($summary['walkin_revenue'] ?? 0, 2); ?>
                    </div>
                </div>
                <div class="summary-card">
                    <h3>Verified Revenue</h3>
                    <div class="value">₱<?php echo number_format($summary['verified_revenue'] ?? 0, 2); ?></div>
                    <div class="subtext"><?php echo $summary['verified_orders'] ?? 0; ?> verified orders</div>
                </div>
                <div class="summary-card">
                    <h3>Pending Verification</h3>
                    <div class="value">₱<?php echo number_format($summary['pending_revenue'] ?? 0, 2); ?></div>
                    <div class="subtext"><?php echo $summary['pending_orders'] ?? 0; ?> pending</div>
                </div>
                <div class="summary-card">
                    <h3>Total Transactions</h3>
                    <div class="value"><?php echo $summary['total_orders'] ?? 0; ?></div>
                    <div class="subtext">
                        Walk-in: <?php echo $summary['walkin_sales_count'] ?? 0; ?>
                    </div>
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
                <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Daily Sales Breakdown (Online + Walk-in)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Online Orders</th>
                            <th>Walk-in Sales</th>
                            <th>Total Orders</th>
                            <th>Online Revenue</th>
                            <th>Walk-in Revenue</th>
                            <th>Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $sale): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                            <td><?php echo $sale['online_orders']; ?></td>
                            <td><strong><?php echo $sale['walkin_orders']; ?></strong></td>
                            <td><strong><?php echo $sale['total_orders']; ?></strong></td>
                            <td>₱<?php echo number_format($sale['online_revenue'] ?? 0, 2); ?></td>
                            <td><strong>₱<?php echo number_format($sale['walkin_revenue'] ?? 0, 2); ?></strong></td>
                            <td><strong>₱<?php echo number_format($sale['total_revenue'], 2); ?></strong></td>
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