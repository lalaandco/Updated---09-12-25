<?php
session_start();
header('Content-Type: application/json');

// Check user authentication
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit();
}

require_once 'config.php';

try {
    $user_email = $_SESSION['email'];
    
    // Get unread notification count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_email = ? AND is_read = 0");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'count' => intval($data['count'])
        ]);
    } else {
        echo json_encode(['success' => false, 'count' => 0]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}

$conn->close();
?>