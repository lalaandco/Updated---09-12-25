<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["email"])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a rating']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_email = $_SESSION['email'];
$order_id = intval($_POST['order_id'] ?? 0);
$product_id = intval($_POST['product_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review_title = trim($_POST['review_title'] ?? '');
$review_text = trim($_POST['review_text'] ?? '');

// Validate inputs
if ($order_id <= 0 || $product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order or product']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars']);
    exit();
}

try {
    // Verify that the user ordered this product
    $verify_stmt = $conn->prepare("
        SELECT oi.product_id, o.status 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_id = ? AND o.user_email = ? AND oi.product_id = ?
    ");
    $verify_stmt->bind_param("isi", $order_id, $user_email, $product_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You can only rate products you have purchased']);
        exit();
    }
    
    $order_data = $result->fetch_assoc();
    $verify_stmt->close();
    
    // Check if order is delivered (optional - you can remove this if you want to allow rating before delivery)
    if ($order_data['status'] !== 'delivered') {
        echo json_encode(['success' => false, 'message' => 'You can only rate products after they are delivered']);
        exit();
    }
    
    // Check if rating already exists
    $check_stmt = $conn->prepare("
        SELECT rating_id FROM product_ratings 
        WHERE order_id = ? AND product_id = ?
    ");
    $check_stmt->bind_param("ii", $order_id, $product_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();
    
    if ($existing->num_rows > 0) {
        // Update existing rating
        $rating_id = $existing->fetch_assoc()['rating_id'];
        $check_stmt->close();
        
        $update_stmt = $conn->prepare("
            UPDATE product_ratings 
            SET rating = ?, review_title = ?, review_text = ?, updated_at = NOW()
            WHERE rating_id = ?
        ");
        $update_stmt->bind_param("issi", $rating, $review_title, $review_text, $rating_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Your rating has been updated successfully!'
        ]);
    } else {
        $check_stmt->close();
        
        // Insert new rating
        $insert_stmt = $conn->prepare("
            INSERT INTO product_ratings 
            (order_id, user_email, product_id, rating, review_title, review_text, is_verified_purchase) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $insert_stmt->bind_param("isiiss", $order_id, $user_email, $product_id, $rating, $review_title, $review_text);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thank you for your rating!'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error submitting rating: ' . $e->getMessage()]);
}

$conn->close();
?>