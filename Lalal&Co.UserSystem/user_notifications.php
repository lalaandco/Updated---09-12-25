<?php
/**
 * User Notifications Class - FIXED VERSION
 * Handles fetching and displaying user notifications
 */

class UserNotifications {
    private $conn;
    private $user_email;
    private $notifications = [];
    
    public function __construct($connection, $user_email) {
        $this->conn = $connection;
        $this->user_email = $user_email;
        $this->loadNotifications();
    }
    
    /**
     * Load unread notifications for the current user
     */
    private function loadNotifications() {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    notification_id,
                    notification_type,
                    title,
                    message,
                    order_id,
                    severity,
                    link,
                    icon,
                    created_at,
                    is_read
                FROM user_notifications
                WHERE user_email = ? 
                  AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 10
            ");
            
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return;
            }
            
            $stmt->bind_param("s", $this->user_email);
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                $stmt->close();
                return;
            }
            
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $this->notifications[] = $row;
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Error loading notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Get all unread notifications
     */
    public function getNotifications() {
        return $this->notifications;
    }
    
    /**
     * Get total notification count
     */
    public function getTotalCount() {
        return count($this->notifications);
    }
    
    /**
     * Get count by severity
     */
    public function getCountBySeverity($severity) {
        return count(array_filter($this->notifications, function($n) use ($severity) {
            return $n['severity'] === $severity;
        }));
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id) {
        try {
            $stmt = $this->conn->prepare("CALL mark_user_notification_read(?, ?)");
            
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param("is", $notification_id, $this->user_email);
            $result = $stmt->execute();
            $stmt->close();
            
            // Clear any remaining results
            while ($this->conn->more_results()) {
                $this->conn->next_result();
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all notifications (including read ones) for history page
     */
    public function getAllNotifications($limit = 50) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    notification_id,
                    notification_type,
                    title,
                    message,
                    order_id,
                    severity,
                    link,
                    icon,
                    is_read,
                    created_at,
                    read_at
                FROM user_notifications
                WHERE user_email = ?
                ORDER BY is_read ASC, created_at DESC
                LIMIT ?
            ");
            
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return [];
            }
            
            $stmt->bind_param("si", $this->user_email, $limit);
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                $stmt->close();
                return [];
            }
            
            $result = $stmt->get_result();
            $all_notifications = [];
            
            while ($row = $result->fetch_assoc()) {
                $all_notifications[] = $row;
            }
            
            $stmt->close();
            return $all_notifications;
            
        } catch (Exception $e) {
            error_log("Error getting all notifications: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Helper function to convert timestamp to relative time
 */
function userTimeAgo($datetime) {
    if (empty($datetime)) {
        return 'Unknown';
    }
    
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'Unknown';
    }
    
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
    } elseif ($difference < 2592000) {
        $weeks = floor($difference / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
?>