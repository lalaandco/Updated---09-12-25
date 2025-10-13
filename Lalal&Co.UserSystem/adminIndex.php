<?php
// Start session and check authentication
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Admin Dashboard - La Gal & Co.</title>
    <style>
        /* Additional styles for clickable cards */
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
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bxs-file-plus'></i>
                <h4>Create New Invoice</h4>
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bx-package'></i>
                <h4>Add Product</h4>
            </a>
        </div>
        <div class="card">
            <a href="adminEditProducts.php">
                <i class='bx bx-edit'></i>
                <h4>Edit Product</h4>
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bx-purchase-tag'></i>
                <h4>Sales Report</h4>
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bx-bar-chart-alt-2'></i>
                <h4>Purchase Report</h4>
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bx-trending-up'></i>
                <h4>Stock Report</h4>
            </a>
        </div>
        <div class="card">
            <a href="#">
                <i class='bx bx-user-check'></i>
                <h4>Account Summary</h4>
            </a>
        </div>
    </div>

    <div class="bottom-section">
        <div class="chart-card">
            <div class="chart-title">Monthly Progress Report</div>
            <div class="chart-placeholder">
                Chart will be displayed here
            </div>
        </div>
        <div class="report-card">
            <div class="report-title">Today's Report</div>
            <div class="report-item">
                <span>Total Sales</span>
                <span class="report-value">₱ 0.00</span>
            </div>
            <div class="report-item">
                <span>Total Purchase</span>
                <span class="report-value">₱ 0.00</span>
            </div>
        </div>
    </div>
</body>
</html>