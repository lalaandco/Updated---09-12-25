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
$admin_email = $_SESSION['email'] ?? '';

/**
 * Call this function to render the header HTML
 * Place this AFTER any header() redirects in your page
 */
function renderAdminHeader() {
    global $admin_name, $admin_email, $notificationsCount, $criticalCount, $adminNotifications;
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
                max-width: 450px;
                width: calc(100vw - 40px);
                max-height: 600px;
                overflow: hidden;
                display: none;
                z-index: 9999;
                animation: slideDown 0.3s ease;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .notification-dropdown.show {
                display: flex;
                flex-direction: column;
            }
            
            .notification-dropdown-header {
                padding: 15px 20px;
                border-bottom: 1px solid #eee;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: black;
                color: white;
                flex-shrink: 0;
            }
            
            .notification-dropdown-header h3 {
                margin: 0;
                font-size: 16px;
            }
            
            .close-notifications {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: white;
                line-height: 1;
            }
            
            .notification-list {
                overflow-y: auto;
                flex: 1;
                max-height: calc(600px - 120px);
            }
            
            .notification-item {
                padding: 15px 20px;
                border-bottom: 1px solid #eee;
                display: flex;
                gap: 12px;
                align-items: flex-start;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                color: inherit;
                position: relative;
            }
            
            .notification-item:hover {
                background: #f8f9fa;
            }
            
            .notification-item.unread {
                background: #e3f2fd;
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
            
            .notification-item.success {
                border-left: 4px solid #4CAF50;
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
            
            .notification-item.success .notification-icon {
                color: #4CAF50;
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
            
            .notification-time {
                font-size: 11px;
                color: #999;
                margin-top: 4px;
            }
            
            .notification-mark-read {
                position: absolute;
                top: 10px;
                right: 10px;
                background: none;
                border: none;
                color: #999;
                cursor: pointer;
                font-size: 18px;
                padding: 5px;
                transition: color 0.3s;
            }
            
            .notification-mark-read:hover {
                color: #4CAF50;
            }
            
            .notification-empty {
                padding: 40px 20px;
                text-align: center;
                color: #999;
            }
            
            .notification-empty i {
                font-size: 48px;
                display: block;
                margin-bottom: 10px;
                color: #ddd;
            }
            
            .notification-footer {
                padding: 12px 20px;
                text-align: center;
                border-top: 1px solid #eee;
                background: #f8f9fa;
                flex-shrink: 0;
            }
            
            .notification-footer a {
                color: #4CAF50;
                text-decoration: none;
                font-weight: 600;
                font-size: 13px;
            }
            
            .notification-footer a:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 768px) {
                .notification-dropdown {
                    max-width: calc(100vw - 20px);
                    right: 10px;
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
        
        <div class="notification-list" id="notificationsList">
            <?php 
            $notifications = $adminNotifications->getNotifications();
            if (empty($notifications)): 
            ?>
                <div class="notification-empty">
                    <i class='bx bx-check-circle'></i>
                    <p>No new notifications</p>
                    <p style="font-size: 12px; margin-top: 10px;">All caught up! 🎉</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['severity']; ?> <?php echo isset($notif['is_persisted']) && $notif['is_persisted'] ? 'unread' : ''; ?>" 
                         data-notification-id="<?php echo $notif['notification_id'] ?? ''; ?>"
                         data-link="<?php echo $notif['link']; ?>">
                        <?php if (isset($notif['notification_id'])): ?>
                            <button class="notification-mark-read" 
                                    onclick="markAsRead(event, <?php echo $notif['notification_id']; ?>)" 
                                    title="Mark as read">
                                <i class='bx bx-check'></i>
                            </button>
                        <?php endif; ?>
                        
                        <div class="notification-icon">
                            <i class="bx <?php echo $notif['icon']; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                            <p>
                                <?php echo htmlspecialchars($notif['message']); ?>
                                <?php if (isset($notif['critical']) && $notif['critical']): ?>
                                    <br><strong style="color: #f44336;">⚠ <?php echo $notif['critical_count']; ?> critical!</strong>
                                <?php endif; ?>
                            </p>
                            <?php if (isset($notif['created_at'])): ?>
                                <div class="notification-time">
                                    <?php echo timeAgo($notif['created_at']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-footer">
            <a href="adminNotificationLog.php">📋 View All Notifications</a>
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
        
        // Handle notification item clicks
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't navigate if clicking the mark-as-read button
                if (e.target.closest('.notification-mark-read')) {
                    return;
                }
                
                const link = this.getAttribute('data-link');
                const notificationId = this.getAttribute('data-notification-id');
                
                // Mark as read if it has a notification ID
                if (notificationId) {
                    markAsRead(e, parseInt(notificationId), link);
                } else if (link) {
                    window.location.href = link;
                }
            });
        });
        
        // Mark notification as read
        function markAsRead(event, notificationId, redirectUrl = null) {
            event.stopPropagation();
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            fetch('mark_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification from UI
                    const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notifElement) {
                        notifElement.style.transition = 'all 0.3s';
                        notifElement.style.opacity = '0';
                        notifElement.style.transform = 'translateX(20px)';
                        
                        setTimeout(() => {
                            notifElement.remove();
                            
                            // Update badge count
                            updateNotificationCount();
                            
                            // Check if list is empty
                            const remainingNotifs = document.querySelectorAll('.notification-item').length;
                            if (remainingNotifs === 0) {
                                document.getElementById('notificationsList').innerHTML = `
                                    <div class="notification-empty">
                                        <i class='bx bx-check-circle'></i>
                                        <p>No new notifications</p>
                                        <p style="font-size: 12px; margin-top: 10px;">All caught up! 🎉</p>
                                    </div>
                                `;
                            }
                            
                            // Redirect if URL provided
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            }
                        }, 300);
                    }
                } else {
                    console.error('Failed to mark notification as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Update notification badge count
        function updateNotificationCount() {
            const remainingCount = document.querySelectorAll('.notification-item').length;
            const badge = document.querySelector('.notification-badge');
            const headerCount = document.querySelector('.notification-dropdown-header h3');
            
            if (remainingCount === 0) {
                if (badge) badge.remove();
                if (headerCount) headerCount.textContent = 'Notifications (0)';
            } else {
                if (badge) badge.textContent = remainingCount;
                if (headerCount) headerCount.textContent = `Notifications (${remainingCount})`;
            }
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Optional: Add AJAX refresh to check for new notifications
            fetch('get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > <?php echo $notificationsCount; ?>) {
                        location.reload(); // Reload if new notifications
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }, 30000);
    </script>
    <?php
}

/**
 * Helper function to convert timestamp to relative time
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'Just now';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>