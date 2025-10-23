<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION["email"])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? 0;

// Validate order belongs to user
$stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_email = ? AND status = 'delivered'");
$stmt->bind_param("is", $order_id, $_SESSION["email"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order']);
    exit();
}

// Update order with delivery confirmation
$stmt = $conn->prepare("UPDATE orders SET delivery_confirmed = 1, delivery_confirmed_date = NOW() WHERE order_id = ?");
$stmt->bind_param("i", $order_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Delivery confirmed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$stmt->close();
$conn->close();