<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.php");
    exit();
}

// Get today's sales data
$today_sales_query = "
    SELECT 
        COUNT(order_id) as order_count,
        COALESCE(SUM(total_amount), 0) as total_sales
    FROM orders
    WHERE DATE(order_date) = CURDATE()
    AND status != 'cancelled'
";
$today_sales = $conn->query($today_sales_query)->fetch_assoc();

// Get monthly sales data for chart (last 12 months)
$monthly_sales_query = "
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        DATE_FORMAT(order_date, '%b %Y') as month_name,
        COUNT(order_id) as order_count,
        SUM(total_amount) as revenue
    FROM orders
    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND status != 'cancelled'
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
";
$monthly_sales = $conn->query($monthly_sales_query)->fetch_all(MYSQLI_ASSOC);

// Get inventory transactions for today
$today_inventory_query = "
    SELECT 
        SUM(CASE WHEN transaction_type = 'restock' THEN quantity ELSE 0 END) as restocked,
        SUM(CASE WHEN transaction_type = 'move_to_display' THEN quantity ELSE 0 END) as moved
    FROM inventory_transactions
    WHERE DATE(created_at) = CURDATE()
";
$today_inventory = $conn->query($today_inventory_query)->fetch_assoc();

// Get low stock count
$low_stock_query = "
    SELECT COUNT(*) as low_stock_count
    FROM product_tbl
    WHERE display_quantity < 20
";
$low_stock = $conn->query($low_stock_query)->fetch_assoc();

// Get pending orders count
$pending_orders_query = "
    SELECT COUNT(*) as pending_count
    FROM orders
    WHERE status = 'pending'
";
$pending_orders = $conn->query($pending_orders_query)->fetch_assoc();

// Get pending payment verifications count
$pending_verifications_query = "
    SELECT COUNT(*) as pending_count
    FROM payment_verifications
    WHERE verification_status = 'pending'
";
$pending_verifications_result = $conn->query($pending_verifications_query);
$pending_verifications = $pending_verifications_result ? $pending_verifications_result->fetch_assoc()['pending_count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Admin Dashboard - Lalal & Co.</title>
    <style>
        .card a {
            display: block;
            text-decoration: none;
            color: inherit;
            width: 100%;
            height: 100%;
        }
        
        .card {
            cursor: pointer;
            position: relative;
        }
        
        .card a i {
            font-size: 48px;
            color: #4CAF50;
            margin-bottom: 15px;
        }
        
        .card a h4 {
            font-size: 16px;
            color: #333333;
            margin-top: 10px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        #salesChart {
            max-height: 350px;
        }
        
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .report-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .report-item:last-child {
            border-bottom: none;
        }
        
        .report-item span:first-child {
            color: #666;
            font-size: 14px;
        }
        
        .report-value {
            font-size: 20px;
            font-weight: 600;
            color: #4CAF50;
        }
        
        .stat-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
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
            <div class="logout-section">
                <a href="adminLogin.php?logout=1" class="logout-btn">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </div>
        </div>
    </div>

    <hr>

    <div class="main-content">
        <div class="card">
            <a href="adminOrders.php">
                <i class='bx bx-shopping-bag'></i>
                <h4>Manage Orders</h4>
                <?php if ($pending_orders['pending_count'] > 0): ?>
                    <span class="stat-badge badge-warning"><?php echo $pending_orders['pending_count']; ?> pending</span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="card">
            <a href="adminInventory.php">
                <i class='bx bx-package'></i>
                <h4>Inventory Management</h4>
                <?php if ($low_stock['low_stock_count'] > 0): ?>
                    <span class="stat-badge badge-warning"><?php echo $low_stock['low_stock_count']; ?> low stock</span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="card">
            <a href="adminCreateInvoice.php">
                <i class='bx bxs-file-plus'></i>
                <h4>Create New Invoice</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminPaymentVerification.php">
                <i class='bx bx-wallet-alt'></i>
                <h4>Payment Verification</h4>
                <?php if ($pending_verifications > 0): ?>
                    <span class="stat-badge badge-warning"><?php echo $pending_verifications; ?> pending</span>
                <?php endif; ?>
            </a>
        </div>
        
        <div class="card">
            <a href="adminAddProduct.php">
                <i class='bx bx-box'></i>
                <h4>Add Product</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminEditProducts.php">
                <i class='bx bx-edit'></i>
                <h4>Edit Product Details</h4>
            </a>
        </div>
    
        <div class="card">
            <a href="adminReports.php?report_type=sales">
                <i class='bx bx-purchase-tag'></i>
                <h4>Reports</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminWalkInSales.php">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h4>Walk In</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminAccountSummary.php">
                <i class='bx bx-user-check'></i>
                <h4>Account Summary</h4>
            </a>
        </div>

        <div class="card">
            <a href="adminPurchaseOrders.php">
                <i class='bx bx-receipt'></i>
                <h4>Purchase Orders</h4>
            </a>
        </div>
    </div>

    <div class="dashboard-widgets" style="max-width: 1400px; margin: 40px auto; padding: 0 20px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
            
            <!-- Left Column: Widgets -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                
                <!-- Recent Orders Widget -->
                <div class="widget-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;"><i class='bx bx-shopping-bag'></i> Recent Orders</h3>
                        <a href="adminOrders.php" style="color: #4CAF50; text-decoration: none; font-size: 14px;">View All →</a>
                    </div>
                    <?php
                    $recent_orders_query = "
                        SELECT order_id, full_name, total_amount, status, order_date
                        FROM orders
                        ORDER BY order_date DESC
                        LIMIT 5
                    ";
                    $recent_orders = $conn->query($recent_orders_query)->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <?php if (!empty($recent_orders)): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($recent_orders as $order): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #333;">#<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($order['full_name']); ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: #4CAF50;">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                        <span style="font-size: 11px; padding: 3px 8px; background: <?php 
                                            echo $order['status'] === 'pending' ? '#fff3cd' : 
                                                ($order['status'] === 'delivered' ? '#d4edda' : '#d1ecf1'); 
                                        ?>; border-radius: 10px;">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #999;">
                            <i class='bx bx-shopping-bag' style="font-size: 48px;"></i>
                            <p>No recent orders</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Low Stock Alerts Widget -->
                <div class="widget-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;"><i class='bx bx-error'></i> Low Stock Alerts</h3>
                        <a href="adminInventory.php" style="color: #ff9800; text-decoration: none; font-size: 14px;">Manage →</a>
                    </div>
                    <?php
                    $low_stock_items_query = "
                        SELECT product_id, product_name, display_quantity, inventory_quantity
                        FROM product_tbl
                        WHERE display_quantity < 20
                        ORDER BY display_quantity ASC
                        LIMIT 5
                    ";
                    $low_stock_items = $conn->query($low_stock_items_query)->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <?php if (!empty($low_stock_items)): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($low_stock_items as $item): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #fff3cd; border-radius: 5px; border-left: 4px solid #ff9800;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;">Warehouse: <?php echo $item['inventory_quantity']; ?> units</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 20px; font-weight: bold; color: <?php echo $item['display_quantity'] == 0 ? '#f44336' : '#ff9800'; ?>;">
                                            <?php echo $item['display_quantity']; ?>
                                        </div>
                                        <div style="font-size: 11px; color: #666;">on display</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #4CAF50;">
                            <i class='bx bx-check-circle' style="font-size: 48px;"></i>
                            <p>All stock levels are good!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Stats -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Quick Actions Panel -->
                <div class="widget-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0 0 20px 0;"><i class='bx bx-zap'></i> Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="adminAddProduct.php" style="display: flex; align-items: center; gap: 12px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class='bx bx-plus-circle' style="font-size: 24px;"></i>
                            <div>
                                <div style="font-weight: 600;">Add New Product</div>
                                <div style="font-size: 12px; opacity: 0.9;">Create product listing</div>
                            </div>
                        </a>

                        <a href="adminOrders.php?status=pending" style="display: flex; align-items: center; gap: 12px; padding: 15px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; text-decoration: none; border-radius: 8px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class='bx bx-bell' style="font-size: 24px;"></i>
                            <div>
                                <div style="font-weight: 600;">Pending Orders</div>
                                <div style="font-size: 12px; opacity: 0.9;"><?php echo $pending_orders['pending_count']; ?> need attention</div>
                            </div>
                        </a>

                        <a href="adminPurchaseOrders.php" style="display: flex; align-items: center; gap: 12px; padding: 15px; background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; text-decoration: none; border-radius: 8px; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                            <i class='bx bx-receipt' style="font-size: 24px;"></i>
                            <div>
                                <div style="font-weight: 600;">Purchase Order</div>
                                <div style="font-size: 12px; opacity: 0.8;">Order from supplier</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Quick Stats Widget -->
                <div class="widget-card" style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="margin: 0;"><i class='bx bx-line-chart'></i> This Week</h3>
                    </div>
                    <?php
                    $week_stats_query = "
                        SELECT 
                            COUNT(DISTINCT CASE WHEN order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN order_id END) as orders_this_week,
                            COALESCE(SUM(CASE WHEN order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status != 'cancelled' THEN total_amount ELSE 0 END), 0) as revenue_this_week,
                            COUNT(DISTINCT CASE WHEN order_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND order_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN order_id END) as orders_last_week
                        FROM orders
                    ";
                    $week_stats = $conn->query($week_stats_query)->fetch_assoc();
                    
                    $order_change = $week_stats['orders_last_week'] > 0 
                        ? (($week_stats['orders_this_week'] - $week_stats['orders_last_week']) / $week_stats['orders_last_week']) * 100 
                        : 0;
                    ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white;">
                            <div style="font-size: 14px; opacity: 0.9;">Revenue</div>
                            <div style="font-size: 28px; font-weight: bold; margin: 5px 0;">
                                ₱<?php echo number_format($week_stats['revenue_this_week'], 2); ?>
                            </div>
                            <div style="font-size: 12px; opacity: 0.8;">Last 7 days</div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 12px; color: #666;">Orders</div>
                                <div style="font-size: 24px; font-weight: bold; color: #333; margin: 5px 0;">
                                    <?php echo $week_stats['orders_this_week']; ?>
                                </div>
                                <?php if ($order_change != 0): ?>
                                    <div style="font-size: 11px; color: <?php echo $order_change > 0 ? '#4CAF50' : '#f44336'; ?>;">
                                        <?php echo $order_change > 0 ? '↑' : '↓'; ?> <?php echo abs(round($order_change, 1)); ?>%
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <div style="font-size: 12px; color: #666;">Avg Order</div>
                                <div style="font-size: 24px; font-weight: bold; color: #333; margin: 5px 0;">
                                    ₱<?php echo $week_stats['orders_this_week'] > 0 ? number_format($week_stats['revenue_this_week'] / $week_stats['orders_this_week'], 0) : '0'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-section">
        <div class="chart-card">
            <div class="chart-title">Monthly Sales Progress Report</div>
            <canvas id="salesChart"></canvas>
        </div>
        
        <div class="report-card">
            <div class="report-title">Today's Report</div>
            <div class="report-item">
                <span>Total Sales</span>
                <span class="report-value">₱ <?php echo number_format($today_sales['total_sales'], 2); ?></span>
            </div>
            <div class="report-item">
                <span>Orders Completed</span>
                <span class="report-value"><?php echo $today_sales['order_count']; ?></span>
            </div>
            <div class="report-item">
                <span>Items Restocked</span>
                <span class="report-value"><?php echo $today_inventory['restocked'] ?? 0; ?> units</span>
            </div>
            <div class="report-item">
                <span>Items Moved to Display</span>
                <span class="report-value"><?php echo $today_inventory['moved'] ?? 0; ?> units</span>
            </div>
            <div class="report-item">
                <span>Low Stock Alerts</span>
                <span class="report-value" style="color: #ff9800;">
                    <?php echo $low_stock['low_stock_count']; ?>
                    <?php if ($low_stock['low_stock_count'] > 0): ?>
                        <span class="stat-badge badge-warning">Action needed</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="report-item">
                <span>Pending Orders</span>
                <span class="report-value" style="color: #2196f3;">
                    <?php echo $pending_orders['pending_count']; ?>
                    <?php if ($pending_orders['pending_count'] > 0): ?>
                        <span class="stat-badge badge-info">Review</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <script>
        // Prepare chart data from PHP
        const monthlyData = <?php echo json_encode($monthly_sales); ?>;
        
        // Extract labels and data
        const labels = monthlyData.map(item => item.month_name);
        const revenueData = monthlyData.map(item => parseFloat(item.revenue));
        const orderData = monthlyData.map(item => parseInt(item.order_count));
        
        // Create the chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue (₱)',
                        data: revenueData,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: orderData,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.dataset.label === 'Revenue (₱)') {
                                        label += '₱' + context.parsed.y.toLocaleString('en-PH', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    } else {
                                        label += context.parsed.y;
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>