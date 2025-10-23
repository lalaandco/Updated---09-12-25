<?php
session_start();
include 'admin_header.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: adminLogin.php");
    exit();
}

require_once 'config.php';

// Get filter parameters
$filter_type = $_GET['filter_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_archived = isset($_GET['show_archived']) ? 1 : 0;

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($filter_type)) {
    $where_conditions[] = "nl.notification_type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(nl.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(nl.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

if (!$show_archived) {
    $where_conditions[] = "nl.archived_at IS NULL";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get notification logs
$query = "
    SELECT 
        nl.*,
        an.is_read,
        an.read_at,
        CASE 
            WHEN nl.archived_at IS NOT NULL THEN 'Archived'
            WHEN an.is_read = 1 THEN 'Read'
            ELSE 'Active'
        END as status
    FROM notification_log nl
    LEFT JOIN admin_notifications an ON nl.notification_id = an.notification_id
    {$where_clause}
    ORDER BY nl.created_at DESC
    LIMIT 200
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get notification type counts
$type_counts_query = "
    SELECT 
        notification_type,
        COUNT(*) as count
    FROM notification_log
    GROUP BY notification_type
";
$type_counts = $conn->query($type_counts_query)->fetch_all(MYSQLI_ASSOC);

renderAdminHeader();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Notification Log - Admin Panel</title>
    <style>
        .notification-log-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .log-header {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group button {
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #0000;
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }
        
        .stat-card h4 {
            margin: 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .log-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .log-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .log-table thead {
            background: #f8f9fa;
        }
        
        .log-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        .log-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .log-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-read {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-archived {
            background: #f8d7da;
            color: #721c24;
        }
        
        .severity-critical {
            background: #f8d7da;
            color: #721c24;
        }
        
        .severity-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .severity-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .severity-success {
            background: #d4edda;
            color: #155724;
        }
        
        .type-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .type-delivery_confirmed {
            background: #d4edda;
            color: #155724;
        }
        
        .type-low_stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .type-pending_order {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="notification-log-container">
        <div class="log-header">
            <h2><i class='bx bx-bell'></i> Notification Activity Log</h2>
            <p>View and track all notification history and admin actions</p>
            
            <div class="stats-grid">
                <?php foreach ($type_counts as $type): ?>
                    <div class="stat-card">
                        <h4><?php echo ucwords(str_replace('_', ' ', $type['notification_type'])); ?></h4>
                        <div class="stat-value"><?php echo $type['count']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="filters-section">
            <form method="GET">
                <div class="filters-row">
                    <div class="form-group">
                        <label for="filter_type">Notification Type</label>
                        <select id="filter_type" name="filter_type">
                            <option value="">All Types</option>
                            <option value="delivery_confirmed" <?php echo $filter_type === 'delivery_confirmed' ? 'selected' : ''; ?>>Delivery Confirmed</option>
                            <option value="low_stock" <?php echo $filter_type === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="pending_order" <?php echo $filter_type === 'pending_order' ? 'selected' : ''; ?>>Pending Orders</option>
                            <option value="pending_payment" <?php echo $filter_type === 'pending_payment' ? 'selected' : ''; ?>>Pending Payments</option>
                            <option value="pending_cancellation" <?php echo $filter_type === 'pending_cancellation' ? 'selected' : ''; ?>>Cancellations</option>
                            <option value="pending_refund" <?php echo $filter_type === 'pending_refund' ? 'selected' : ''; ?>>Refunds</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_archived" <?php echo $show_archived ? 'checked' : ''; ?>>
                            Show Archived
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit">🔍 Filter</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="log-table">
            <?php if (empty($logs)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <i class='bx bx-bell-off' style="font-size: 64px; color: #ccc;"></i>
                    <h3>No notification logs found</h3>
                    <p>No notifications match your current filters.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Message</th>
                            <th>Severity</th>
                            <th>Triggered By</th>
                            <th>Action</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: #333;">
                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo date('h:i A', strtotime($log['created_at'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="type-badge type-<?php echo $log['notification_type']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $log['notification_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['title']); ?></strong>
                                <?php if ($log['related_id']): ?>
                                    <br><small style="color: #666;">
                                        <?php echo $log['related_table']; ?> #<?php echo $log['related_id']; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                            <td>
                                <?php if ($log['severity']): ?>
                                    <span class="status-badge severity-<?php echo $log['severity']; ?>">
                                        <?php echo ucfirst($log['severity']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                echo $log['triggered_by'] ? htmlspecialchars($log['triggered_by']) : 'System'; 
                                ?>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: #666;">
                                    <?php echo ucwords(str_replace('_', ' ', $log['action_taken'] ?? 'N/A')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($log['status']); ?>">
                                    <?php echo $log['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <p>Showing <?php echo count($logs); ?> notification logs</p>
            <?php if (!$show_archived): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['show_archived' => 1])); ?>" 
                   style="color: #4CAF50; text-decoration: none;">
                    📦 Show archived notifications
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>