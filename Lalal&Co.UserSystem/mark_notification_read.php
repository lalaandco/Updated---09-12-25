<?php
session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
    $admin_email = $_SESSION['email'];
    
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        exit();
    }
    
    try {
        // Call stored procedure to mark as read
        $stmt = $conn->prepare("CALL mark_notification_read(?, ?)");
        $stmt->bind_param("is", $notification_id, $admin_email);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read',
                'notification_id' => $notification_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to mark notification as read'
            ]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>