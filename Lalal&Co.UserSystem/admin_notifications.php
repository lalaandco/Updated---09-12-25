<?php

if (!isset($_SESSION['admin_logged_in'])) {
    exit();
}

require_once 'config.php';

class AdminNotifications {
    private $conn;
    private $notifications = [];
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->loadNotifications();
    }
    
    private function loadNotifications() {
        // Load unread notifications from database
        $this->loadPersistedNotifications();
        
        // Also check for real-time alerts (for backward compatibility)
        $this->checkLowStockAlerts();
        $this->checkPendingOrders();
        $this->checkPendingPayments();
        $this->checkPendingPurchaseOrders();
        $this->checkPendingCancellations();
        $this->checkPendingRefunds();
    }
    
    /**
     * Load persisted notifications from database
     */
    private function loadPersistedNotifications() {
        $query = "
            SELECT 
                notification_id,
                notification_type,
                title,
                message,
                related_id,
                related_table,
                severity,
                link,
                icon,
                created_at
            FROM admin_notifications
            WHERE is_read = 0
            ORDER BY created_at DESC
        ";
        
        $result = $this->conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->notifications[] = [
                    'notification_id' => $row['notification_id'],
                    'type' => $row['notification_type'],
                    'title' => $row['title'],
                    'message' => $row['message'],
                    'related_id' => $row['related_id'],
                    'related_table' => $row['related_table'],
                    'severity' => $row['severity'],
                    'link' => $row['link'],
                    'icon' => $row['icon'],
                    'created_at' => $row['created_at'],
                    'is_persisted' => true
                ];
            }
        }
    }
    
    /**
     * Check and create low stock alert notifications
     */
    private function checkLowStockAlerts() {
        $query = "
            SELECT 
                COUNT(*) as count,
                SUM(CASE WHEN display_quantity = 0 THEN 1 ELSE 0 END) as critical_count
            FROM product_tbl
            WHERE display_quantity < 30
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            // Check if notification already exists
            $existing = $this->conn->query("
                SELECT notification_id FROM admin_notifications 
                WHERE notification_type = 'low_stock' AND is_read = 0
            ")->num_rows;
            
            if ($existing == 0) {
                // Create new notification
                $this->createNotification(
                    'low_stock',
                    'Low Stock Alert',
                    $result['count'] . ' products low on stock',
                    null,
                    'product_tbl',
                    $result['critical_count'] > 0 ? 'critical' : 'warning',
                    'adminInventory.php',
                    'bx-package'
                );
            }
        }
    }
    
    private function checkPendingOrders() {
        $query = "
            SELECT COUNT(*) as count
            FROM orders
            WHERE status = 'pending' AND DATE(order_date) = CURDATE()
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $existing = $this->conn->query("
                SELECT notification_id FROM admin_notifications 
                WHERE notification_type = 'pending_order' 
                  AND DATE(created_at) = CURDATE()
                  AND is_read = 0
            ")->num_rows;
            
            if ($existing == 0) {
                $this->createNotification(
                    'pending_order',
                    'Pending Orders',
                    $result['count'] . ' order(s) awaiting confirmation',
                    null,
                    'orders',
                    'info',
                    'adminOrders.php?status=pending',
                    'bx-shopping-bag'
                );
            }
        }
    }
    
    private function checkPendingPayments() {
        $query = "
            SELECT COUNT(*) as count
            FROM payment_verifications
            WHERE verification_status = 'pending'
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $existing = $this->conn->query("
                SELECT notification_id FROM admin_notifications 
                WHERE notification_type = 'pending_payment' AND is_read = 0
            ")->num_rows;
            
            if ($existing == 0) {
                $this->createNotification(
                    'pending_payment',
                    'Payment Verification',
                    $result['count'] . ' payment(s) need verification',
                    null,
                    'payment_verifications',
                    'warning',
                    'adminPaymentVerification.php',
                    'bx-wallet-alt'
                );
            }
        }
    }
    
    private function checkPendingPurchaseOrders() {
        $query = "
            SELECT COUNT(*) as count
            FROM purchase_orders
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $existing = $this->conn->query("
                SELECT notification_id FROM admin_notifications 
                WHERE notification_type = 'pending_purchase' AND is_read = 0
            ")->num_rows;
            
            if ($existing == 0) {
                $this->createNotification(
                    'pending_purchase',
                    'Purchase Orders',
                    $result['count'] . ' purchase order(s) pending',
                    null,
                    'purchase_orders',
                    'info',
                    'adminPurchaseOrders.php',
                    'bx-receipt'
                );
            }
        }
    }
    
    private function checkPendingCancellations() {
        $query = "
            SELECT COUNT(*) as count
            FROM order_cancellations
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query);
        
        if ($result) {
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $existing = $this->conn->query("
                    SELECT notification_id FROM admin_notifications 
                    WHERE notification_type = 'pending_cancellation' AND is_read = 0
                ")->num_rows;
                
                if ($existing == 0) {
                    $this->createNotification(
                        'pending_cancellation',
                        'Order Cancellations',
                        $data['count'] . ' cancellation request(s) pending',
                        null,
                        'order_cancellations',
                        'warning',
                        'adminIndex.php#cancellations',
                        'bx-x-circle'
                    );
                }
            }
        }
    }
    
    private function checkPendingRefunds() {
        $query = "
            SELECT COUNT(*) as count
            FROM order_refunds
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query);
        
        if ($result) {
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $existing = $this->conn->query("
                    SELECT notification_id FROM admin_notifications 
                    WHERE notification_type = 'pending_refund' AND is_read = 0
                ")->num_rows;
                
                if ($existing == 0) {
                    $this->createNotification(
                        'pending_refund',
                        'Refund Requests',
                        $data['count'] . ' refund request(s) pending review',
                        null,
                        'order_refunds',
                        'warning',
                        'adminPaymentVerification.php?tab=refunds',
                        'bx-undo'
                    );
                }
            }
        }
    }
    
    /**
     * Create a new notification in the database
     */
    private function createNotification($type, $title, $message, $related_id, $related_table, $severity, $link, $icon) {
        $stmt = $this->conn->prepare("
            INSERT INTO admin_notifications 
            (notification_type, title, message, related_id, related_table, severity, link, icon)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssisssss", $type, $title, $message, $related_id, $related_table, $severity, $link, $icon);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $admin_email) {
        $stmt = $this->conn->prepare("CALL mark_notification_read(?, ?)");
        $stmt->bind_param("is", $notification_id, $admin_email);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get all notifications (including read and unread)
     */
    public function getAllNotifications($limit = 50) {
        $query = "
            SELECT 
                notification_id,
                notification_type,
                title,
                message,
                related_id,
                related_table,
                severity,
                link,
                icon,
                is_read,
                created_at,
                read_at,
                read_by
            FROM admin_notifications
            ORDER BY is_read ASC, created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $all_notifications = [];
        while ($row = $result->fetch_assoc()) {
            $all_notifications[] = $row;
        }
        
        $stmt->close();
        return $all_notifications;
    }
    
    public function getNotifications() {
        return $this->notifications;
    }
    
    public function getTotalCount() {
        return count($this->notifications);
    }
    
    public function getCriticalCount() {
        return count(array_filter($this->notifications, function($n) {
            return $n['severity'] === 'critical';
        }));
    }
    
    public function getJSON() {
        return json_encode($this->notifications);
    }
}

// Initialize notifications
$adminNotifications = new AdminNotifications($conn);
$notificationsCount = $adminNotifications->getTotalCount();
$criticalCount = $adminNotifications->getCriticalCount();
?>