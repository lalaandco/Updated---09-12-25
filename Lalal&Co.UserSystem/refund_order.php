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

// Validate inputs
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

if (empty($reason_description)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a reason for the refund']);
    exit();
}

// Check if video file was uploaded
if (!isset($_FILES['refund_video'])) {
    echo json_encode(['success' => false, 'message' => 'No video file uploaded']);
    exit();
}

if ($_FILES['refund_video']['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    
    $error_msg = $error_messages[$_FILES['refund_video']['error']] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error_msg]);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Verify the order belongs to the user and is delivered
    $check_query = "
        SELECT order_id, status, payment_status, full_name
        FROM orders
        WHERE order_id = ? AND user_email = ?
    ";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $order_id, $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Order not found or you do not have permission to access this order');
    }
    
    $order = $result->fetch_assoc();
    $stmt->close();
    
    // Check if order is delivered
    if ($order['status'] !== 'delivered') {
        throw new Exception('Refunds can only be requested for delivered orders. Current status: ' . ucfirst($order['status']));
    }
    
    // Check if there's already a pending or approved refund request
    $check_refund = $conn->prepare("
        SELECT refund_id, status 
        FROM order_refunds 
        WHERE order_id = ? 
        AND status IN ('pending', 'approved')
    ");
    $check_refund->bind_param("i", $order_id);
    $check_refund->execute();
    $refund_result = $check_refund->get_result();
    
    if ($refund_result->num_rows > 0) {
        $existing = $refund_result->fetch_assoc();
        throw new Exception('You already have a ' . $existing['status'] . ' refund request for this order');
    }
    $check_refund->close();
    
    // Handle video upload
    $file = $_FILES['refund_video'];
    
    // Validate file type
    $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('Invalid file type. Please upload a video file (MP4, MOV, AVI, or WEBM). Uploaded type: ' . $mime_type);
    }
    
    // Validate file size (50MB max)
    $max_size = 50 * 1024 * 1024; // 50MB in bytes
    if ($file['size'] > $max_size) {
        throw new Exception('Video file is too large. Maximum size is 50MB. Your file: ' . round($file['size'] / (1024 * 1024), 2) . 'MB');
    }
    
    if ($file['size'] === 0) {
        throw new Exception('Uploaded file is empty');
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/refund_videos/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload directory is not writable');
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'refund_' . $order_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded video file. Check server permissions.');
    }
    
    // Verify file was actually saved
    if (!file_exists($filepath)) {
        throw new Exception('File upload verification failed');
    }
    
    // Insert refund request
    $insert_stmt = $conn->prepare("
        INSERT INTO order_refunds (order_id, user_email, reason_description, video_path, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $insert_stmt->bind_param("isss", $order_id, $user_email, $reason_description, $filepath);
    
    if (!$insert_stmt->execute()) {
        // Delete uploaded file if database insert fails
        if (file_exists($filepath)) {
            @unlink($filepath);
        }
        throw new Exception('Failed to submit refund request to database: ' . $insert_stmt->error);
    }
    
    $insert_stmt->close();
    
    // Update order status to indicate refund pending
    $update_stmt = $conn->prepare("UPDATE orders SET status = 'refund_pending' WHERE order_id = ?");
    $update_stmt->bind_param("i", $order_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update order status: ' . $update_stmt->error);
    }
    
    $update_stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Refund request submitted successfully! Our admin team will review your request and contact you within 2-3 business days.'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $conn->rollback();
    
    // Clean up uploaded file if exists
    if (isset($filepath) && file_exists($filepath)) {
        @unlink($filepath);
    }
    
    // Log error for debugging (optional)
    error_log("Refund request error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>