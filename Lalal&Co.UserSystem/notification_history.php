<?php
// DON'T start session here - header.php will do it
// session_start(); // REMOVED

// Check if user is logged in
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

require_once 'config.php';
require_once 'user_notifications.php';

// Create new connection for notifications to avoid conflicts
$notif_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($notif_conn->connect_error) {
    die("Connection failed: " . $notif_conn->connect_error);
}

$userNotifications = new UserNotifications($notif_conn, $_SESSION['email']);
$all_notifications = $userNotifications->getAllNotifications(100);

// Close connection at the end
$notif_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="myOrderStyle.css">
    <title>My Notifications - Lalal & Co.</title>
    <style>
        .notifications-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }
        
        .notification-filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            color: #666;
        }
        
        .filter-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            cursor: pointer;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            position: relative;
        }
        
        .notification-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .notification-card.unread {
            background: linear-gradient(to right, #e3f2fd 0%, #ffffff 100%);
            border-left: 4px solid #2196F3;
        }
        
        .notification-card.read {
            opacity: 0.7;
        }
        
        .notification-icon-large {
            font-size: 40px;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 50%;
            width: 60px;
            height: 60px;
        }
        
        .notification-card.success .notification-icon-large {
            background: #e8f5e9;
            color: #4CAF50;
        }
        
        .notification-card.info .notification-icon-large {
            background: #e3f2fd;
            color: #2196F3;
        }
        
        .notification-card.warning .notification-icon-large {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .notification-card.error .notification-icon-large {
            background: #ffebee;
            color: #f44336;
        }
        
        .notification-body {
            flex: 1;
        }
        
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .notification-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .notification-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .notification-status.unread {
            background: #2196F3;
            color: white;
        }
        
        .notification-status.read {
            background: #e0e0e0;
            color: #666;
        }
        
        .notification-message {
            color: #666;
            margin: 0 0 12px 0;
            line-height: 1.6;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 13px;
            color: #999;
        }
        
        .notification-meta i {
            margin-right: 4px;
        }
        
        .order-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            transition: color 0.3s;
        }
        
        .order-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 24px;
        }
        
        .empty-state p {
            margin: 0;
            color: #999;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stat-card.total {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }
        
        .stat-card.info {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            color: white;
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="notifications-container">
        <div class="page-header">
            <h1><i class='bx bx-bell'></i> My Notifications</h1>
            <p>Stay updated with your order status and important updates</p>
        </div>
        
        <?php
        $unread_count = count(array_filter($all_notifications, fn($n) => !$n['is_read']));
        $success_count = count(array_filter($all_notifications, fn($n) => $n['severity'] === 'success'));
        $info_count = count(array_filter($all_notifications, fn($n) => $n['severity'] === 'info'));
        $warning_count = count(array_filter($all_notifications, fn($n) => $n['severity'] === 'warning'));
        ?>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <i class='bx bx-bell'></i>
                <div class="stat-number"><?php echo count($all_notifications); ?></div>
                <div class="stat-label">Total Notifications</div>
            </div>
            
            <div class="stat-card success">
                <i class='bx bx-check-circle'></i>
                <div class="stat-number"><?php echo $success_count; ?></div>
                <div class="stat-label">Success Updates</div>
            </div>
            
            <div class="stat-card info">
                <i class='bx bx-info-circle'></i>
                <div class="stat-number"><?php echo $info_count; ?></div>
                <div class="stat-label">Info Updates</div>
            </div>
            
            <div class="stat-card warning">
                <i class='bx bx-error-circle'></i>
                <div class="stat-number"><?php echo $warning_count; ?></div>
                <div class="stat-label">Warnings</div>
            </div>
        </div>
        
        <div class="notification-filters">
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterNotifications('all')">
                    <i class='bx bx-list-ul'></i> All (<?php echo count($all_notifications); ?>)
                </button>
                <button class="filter-btn" onclick="filterNotifications('unread')">
                    <i class='bx bx-bell'></i> Unread (<?php echo $unread_count; ?>)
                </button>
                <button class="filter-btn" onclick="filterNotifications('success')">
                    <i class='bx bx-check-circle'></i> Success (<?php echo $success_count; ?>)
                </button>
                <button class="filter-btn" onclick="filterNotifications('info')">
                    <i class='bx bx-info-circle'></i> Info (<?php echo $info_count; ?>)
                </button>
                <button class="filter-btn" onclick="filterNotifications('warning')">
                    <i class='bx bx-error-circle'></i> Warning (<?php echo $warning_count; ?>)
                </button>
            </div>
        </div>
        
        <?php if (empty($all_notifications)): ?>
            <div class="empty-state">
                <i class='bx bx-bell-off'></i>
                <h3>No Notifications Yet</h3>
                <p>You don't have any notifications. We'll notify you when there are updates on your orders!</p>
                <a href="index.php" class="order-link" style="margin-top: 20px; font-size: 16px;">
                    <i class='bx bx-shopping-bag'></i> Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div id="notificationsContainer">
                <?php foreach ($all_notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['severity']; ?> <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>" 
                         data-filter="<?php echo $notif['severity']; ?>" 
                         data-read="<?php echo $notif['is_read'] ? 'read' : 'unread'; ?>"
                         onclick="window.location.href='<?php echo htmlspecialchars($notif['link']); ?>'">
                        <div class="notification-icon-large">
                            <i class='bx <?php echo htmlspecialchars($notif['icon']); ?>'></i>
                        </div>
                        <div class="notification-body">
                            <div class="notification-header">
                                <h3 class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></h3>
                                <span class="notification-status <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                                    <?php echo $notif['is_read'] ? 'Read' : 'New'; ?>
                                </span>
                            </div>
                            <p class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <div class="notification-meta">
                                <span>
                                    <i class='bx bx-time-five'></i>
                                    <?php echo userTimeAgo($notif['created_at']); ?>
                                </span>
                                <span>
                                    <i class='bx bx-shopping-bag'></i>
                                    Order #<?php echo str_pad($notif['order_id'], 8, '0', STR_PAD_LEFT); ?>
                                </span>
                                <?php if ($notif['is_read'] && $notif['read_at']): ?>
                                    <span>
                                        <i class='bx bx-check-double'></i>
                                        Read <?php echo userTimeAgo($notif['read_at']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="order-link" onclick="event.stopPropagation();">
                                <i class='bx bx-right-arrow-circle'></i> View Order Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; 2025 Lalal & Co. All rights reserved.</p>
    </footer>

    <script>
        function filterNotifications(filter) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.closest('.filter-btn').classList.add('active');
            
            // Filter cards
            const cards = document.querySelectorAll('.notification-card');
            cards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'flex';
                } else if (filter === 'unread') {
                    card.style.display = card.dataset.read === 'unread' ? 'flex' : 'none';
                } else {
                    card.style.display = card.dataset.filter === filter ? 'flex' : 'none';
                }
            });
            
            // Check if any cards are visible
            const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
            const container = document.getElementById('notificationsContainer');
            
            if (visibleCards.length === 0) {
                const emptyMessage = document.createElement('div');
                emptyMessage.className = 'empty-state';
                emptyMessage.style.marginTop = '20px';
                emptyMessage.innerHTML = `
                    <i class='bx bx-filter-alt'></i>
                    <h3>No Notifications Found</h3>
                    <p>No notifications match the selected filter.</p>
                `;
                container.appendChild(emptyMessage);
            } else {
                // Remove empty message if exists
                const emptyMessage = container.querySelector('.empty-state');
                if (emptyMessage) {
                    emptyMessage.remove();
                }
            }
        }
    </script>
</body>
</html>