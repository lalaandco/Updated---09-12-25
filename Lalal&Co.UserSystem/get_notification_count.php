<?php
session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'count' => 0]);
    exit();
}

require_once 'config.php';

try {
    // Get unread notification count
    $query = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
    $result = $conn->query($query);
    
    if ($result) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'count' => intval($data['count'])
        ]);
    } else {
        echo json_encode(['success' => false, 'count' => 0]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}

$conn->close();
?>