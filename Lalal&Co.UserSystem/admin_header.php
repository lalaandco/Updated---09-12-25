<?php
/**
 * Admin Header - Include this in all admin pages
 * Usage: <?php include 'admin_header.php'; ?>
 * 
 * IMPORTANT: This file now only handles session check and loads config.
 * Call renderAdminHeader() function AFTER any header() redirects in your page.
 */

// Session check and config loading - NO OUTPUT HERE
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.php');
    exit();
}

require_once 'config.php';
include 'admin_notifications.php'; // Load notifications

// Get admin name from session or database
$admin_name = $_SESSION['admin_name'] ?? "Maricel Lacdan Ng";

/**
 * Call this function to render the header HTML
 * Place this AFTER any header() redirects in your page
 */
function renderAdminHeader() {
    global $admin_name, $notificationsCount, $criticalCount, $adminNotifications;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <style>
            .header {
                background: white;
                padding: 15px 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 20px;
                flex-wrap: wrap;
            }
            
            .left-section {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .lc-logo img {
                height: 40px;
            }
            
            .center-section {
                flex: 1;
            }
            
            .welcome-text {
                font-size: 18px;
                font-weight: 600;
                color: #333;
            }
            
            .right-section {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            
            .notifications-btn {
                position: relative;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #666;
                transition: color 0.3s;
            }
            
            .notifications-btn:hover {
                color: #4CAF50;
            }
            
            .notification-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #f44336;
                color: white;
                border-radius: 50%;
                width: 22px;
                height: 22px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                font-weight: bold;
            }
            
            .notification-badge.critical {
                background: #d32f2f;
                animation: pulse 1.5s infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            
            .profile-section {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .profile-section img {
                width: 40px;
                height: 40px;
                border-radius: 50%;
            }
            
            .notification-dropdown {
                position: fixed;
                top: 60px;
                right: 20px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.2);
                max-width: 400px;
                max-height: 500px;
                overflow-y: auto;
                display: none;
                z-index: 9999;
            }
            
            .notification-dropdown.show {
                display: block;
            }
            
            .notification-dropdown-header {
                padding: 15px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8f9fa;
            }
            
            .notification-dropdown-header h3 {
                margin: 0;
                font-size: 16px;
                color: #333;
            }
            
            .close-notifications {
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #999;
            }
            
            .notification-item {
                padding: 15px;
                border-bottom: 1px solid #eee;
                display: flex;
                gap: 12px;
                align-items: flex-start;
                cursor: pointer;
                transition: background 0.3s;
                text-decoration: none;
                color: inherit;
            }
            
            .notification-item:hover {
                background: #f8f9fa;
            }
            
            .notification-item.critical {
                border-left: 4px solid #f44336;
            }
            
            .notification-item.warning {
                border-left: 4px solid #ff9800;
            }
            
            .notification-item.info {
                border-left: 4px solid #2196F3;
            }
            
            .notification-icon {
                font-size: 24px;
                min-width: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .notification-item.critical .notification-icon {
                color: #f44336;
            }
            
            .notification-item.warning .notification-icon {
                color: #ff9800;
            }
            
            .notification-item.info .notification-icon {
                color: #2196F3;
            }
            
            .notification-content {
                flex: 1;
            }
            
            .notification-content h4 {
                margin: 0 0 5px 0;
                font-size: 14px;
                font-weight: 600;
                color: #333;
            }
            
            .notification-content p {
                margin: 0;
                font-size: 13px;
                color: #666;
            }
            
            .notification-empty {
                padding: 30px 20px;
                text-align: center;
                color: #999;
            }
            
            .notification-empty i {
                font-size: 48px;
                display: block;
                margin-bottom: 10px;
                color: #ddd;
            }
            
            @media (max-width: 768px) {
                .notification-dropdown {
                    max-width: calc(100vw - 40px);
                    right: 20px;
                }
                
                .header {
                    flex-direction: column;
                }
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
                <h3>Admin Dashboard</h3>
                <p>Lalal & Co.</p>
            </div>
            <?php 
            $current_page = basename($_SERVER['PHP_SELF']);
            if ($current_page !== 'adminIndex.php'): 
            ?>
                <a href="javascript:history.back()" style="color: #666; text-decoration: none; margin-left: 20px; display: flex; align-items: center; gap: 5px;">
                    <i class='bx bx-arrow-back'></i> Back
                </a>
            <?php endif; ?>
        </div>
        
        <div class="right-section">
            <button class="notifications-btn" id="notificationBtn" title="Notifications">
                <i class='bx bx-bell'></i>
                <?php if ($notificationsCount > 0): ?>
                    <span class="notification-badge <?php echo $criticalCount > 0 ? 'critical' : ''; ?>">
                        <?php echo $notificationsCount; ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span><?php echo htmlspecialchars($admin_name); ?></span>
            </div>
            
            <a href="adminLogin.php?logout=1" style="color: #666; text-decoration: none; font-size: 20px;" title="Logout">
                <i class='bx bx-log-out'></i>
            </a>
        </div>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <h3>Notifications (<?php echo $notificationsCount; ?>)</h3>
            <button class="close-notifications" onclick="closeNotifications()">×</button>
        </div>
        
        <div id="notificationsList">
            <?php 
            $notifications = $adminNotifications->getNotifications();
            if (empty($notifications)): 
            ?>
                <div class="notification-empty">
                    <i class='bx bx-check-circle'></i>
                    <p>No notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo $notif['link']; ?>" class="notification-item <?php echo $notif['severity']; ?>">
                        <div class="notification-icon">
                            <i class="bx <?php echo $notif['icon']; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h4><?php echo $notif['title']; ?></h4>
                            <p>
                                <?php echo $notif['message']; ?>
                                <?php if (isset($notif['critical']) && $notif['critical']): ?>
                                    <br><strong style="color: #f44336;">⚠ <?php echo $notif['critical_count']; ?> critical!</strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });
        
        function closeNotifications() {
            notificationDropdown.classList.remove('show');
        }
        
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                closeNotifications();
            }
        });
        
        setInterval(function() {
            // Optional: Add AJAX refresh here
        }, 30000);
    </script>
    <?php
}
?>