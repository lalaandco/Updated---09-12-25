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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Admin Dashboard - La Gal & Co.</title>
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
                <h4>Sales Report</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminReports.php?report_type=purchase">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h4>Purchase Report</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminReports.php?report_type=stock">
                <i class='bx bx-trending-up'></i>
                <h4>Stock Report</h4>
            </a>
        </div>
        
        <div class="card">
            <a href="adminAccountSummary.php">
                <i class='bx bx-user-check'></i>
                <h4>Account Summary</h4>
            </a>
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