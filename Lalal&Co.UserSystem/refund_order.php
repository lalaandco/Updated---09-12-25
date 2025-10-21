<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to request refunds']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$order_id = intval($_POST['order_id'] ?? 0);
$reason_description = trim($_POST['reason_description'] ?? '');
$user_email = $_SESSION['email'];

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

if (empty($reason_description)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for the refund']);
    exit();
}

if (!isset($_FILES['refund_video']) || $_FILES['refund_video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please upload a video showing the product condition']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Verify the order belongs to the user and is delivered
    $check_query = "
        SELECT order_id, status, payment_status
        FROM orders
        WHERE order_id = ? AND user_email = ?
    ";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $order_id, $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Order not found or access denied');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Check if order is delivered
    if ($order['status'] !== 'delivered') {
        throw new Exception('Refunds can only be requested for delivered orders');
    }
    
    // Check if there's already a pending refund request
    $check_refund = $conn->prepare("SELECT refund_id FROM order_refunds WHERE order_id = ? AND status = 'pending'");
    $check_refund->bind_param("i", $order_id);
    $check_refund->execute();
    if ($check_refund->get_result()->num_rows > 0) {
        throw new Exception('You already have a pending refund request for this order');
    }
    $check_refund->close();
    
    // Handle video upload
    $file = $_FILES['refund_video'];
    $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
    $max_size = 50 * 1024 * 1024; // 50MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Please upload MP4, MOV, AVI, or WEBM video');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Video file too large. Maximum size is 50MB');
    }
    
    $upload_dir = 'uploads/refund_videos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = time() . '_order_' . $order_id . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload video file');
    }
    
    // Insert refund request
    $insert_stmt = $conn->prepare("
        INSERT INTO order_refunds (order_id, user_email, reason_description, video_path, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $insert_stmt->bind_param("isss", $order_id, $user_email, $reason_description, $filepath);
    
    if (!$insert_stmt->execute()) {
        @unlink($filepath); // Delete uploaded file if database insert fails
        throw new Exception('Failed to submit refund request');
    }
    
    $insert_stmt->close();
    
    // Update order status to indicate refund pending
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'refund_pending' WHERE order_id = ?");
    $update_stmt->bind_param("i", $order_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Refund request submitted successfully. Admin will review your request and contact you.'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    if (isset($filepath) && file_exists($filepath)) {
        @unlink($filepath);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>