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
        // 1. Low Stock Alerts (Reorder Point)
        $this->getLowStockAlerts();
        
        // 2. Pending Orders
        $this->getPendingOrders();
        
        // 3. Payment Verifications
        $this->getPendingPayments();
        
        // 4. Purchase Orders Status
        $this->getPendingPurchaseOrders();
        
        // 5. Pending Cancellations (NEW)
        $this->getPendingCancellations();
        
        // 6. Pending Refunds (NEW)
        $this->getPendingRefunds();
    }
    
    private function getLowStockAlerts() {
        $query = "
            SELECT 
                COUNT(*) as count,
                SUM(CASE WHEN display_quantity = 0 THEN 1 ELSE 0 END) as critical_count
            FROM product_tbl
            WHERE display_quantity < 30
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $this->notifications[] = [
                'type' => 'low_stock',
                'title' => 'Low Stock Alert',
                'message' => $result['count'] . ' products low on stock',
                'count' => $result['count'],
                'critical' => $result['critical_count'] > 0,
                'critical_count' => $result['critical_count'],
                'link' => 'adminInventory.php',
                'icon' => 'bx-package',
                'severity' => $result['critical_count'] > 0 ? 'critical' : 'warning'
            ];
        }
    }
    
    private function getPendingOrders() {
        $query = "
            SELECT COUNT(*) as count
            FROM orders
            WHERE status = 'pending' AND DATE(order_date) = CURDATE()
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $this->notifications[] = [
                'type' => 'pending_orders',
                'title' => 'Pending Orders',
                'message' => $result['count'] . ' order(s) awaiting confirmation',
                'count' => $result['count'],
                'link' => 'adminOrders.php?status=pending',
                'icon' => 'bx-shopping-bag',
                'severity' => 'info'
            ];
        }
    }
    
    private function getPendingPayments() {
        $query = "
            SELECT COUNT(*) as count
            FROM payment_verifications
            WHERE verification_status = 'pending'
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $this->notifications[] = [
                'type' => 'pending_payments',
                'title' => 'Payment Verification',
                'message' => $result['count'] . ' payment(s) need verification',
                'count' => $result['count'],
                'link' => 'adminPaymentVerification.php',
                'icon' => 'bx-wallet-alt',
                'severity' => 'warning'
            ];
        }
    }
    
    private function getPendingPurchaseOrders() {
        $query = "
            SELECT COUNT(*) as count
            FROM purchase_orders
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query)->fetch_assoc();
        
        if ($result['count'] > 0) {
            $this->notifications[] = [
                'type' => 'pending_purchases',
                'title' => 'Purchase Orders',
                'message' => $result['count'] . ' purchase order(s) pending',
                'count' => $result['count'],
                'link' => 'adminPurchaseOrders.php',
                'icon' => 'bx-receipt',
                'severity' => 'info'
            ];
        }
    }
    
    // ✅ NEW: Get pending cancellation requests
    private function getPendingCancellations() {
        $query = "
            SELECT COUNT(*) as count
            FROM order_cancellations
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query);
        
        // Check if table exists (in case schema not run yet)
        if ($result) {
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $this->notifications[] = [
                    'type' => 'pending_cancellations',
                    'title' => 'Order Cancellations',
                    'message' => $data['count'] . ' cancellation request(s) pending',
                    'count' => $data['count'],
                    'link' => 'adminIndex.php#cancellations',
                    'icon' => 'bx-x-circle',
                    'severity' => 'warning'
                ];
            }
        }
    }
    
    // ✅ NEW: Get pending refund requests
    private function getPendingRefunds() {
        $query = "
            SELECT COUNT(*) as count
            FROM order_refunds
            WHERE status = 'pending'
        ";
        $result = $this->conn->query($query);
        
        // Check if table exists (in case schema not run yet)
        if ($result) {
            $data = $result->fetch_assoc();
            
            if ($data['count'] > 0) {
                $this->notifications[] = [
                    'type' => 'pending_refunds',
                    'title' => 'Refund Requests',
                    'message' => $data['count'] . ' refund request(s) pending review',
                    'count' => $data['count'],
                    'link' => 'adminPaymentVerification.php?tab=refunds',
                    'icon' => 'bx-undo',
                    'severity' => 'warning'
                ];
            }
        }
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