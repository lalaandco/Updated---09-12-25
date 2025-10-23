<?php
session_start();
header('Content-Type: application/json');

// Check user authentication
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $user_email = $_SESSION['email'];
    
    try {
        // Call stored procedure to mark as read
        $stmt = $conn->prepare("CALL mark_user_notification_read(?, ?)");
        $stmt->bind_param("is", $notification_id, $user_email);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
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
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}

$conn->close();
?>