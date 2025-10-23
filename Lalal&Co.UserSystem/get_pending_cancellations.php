<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $query = "
        SELECT 
            oc.*,
            o.order_id,
            o.full_name,
            o.phone,
            o.total_amount,
            o.order_date
        FROM order_cancellations oc
        JOIN orders o ON oc.order_id = o.order_id
        WHERE oc.status = 'pending'
        ORDER BY oc.created_at DESC
    ";
    
    $result = $conn->query($query);
    $cancellations = [];
    
    while ($row = $result->fetch_assoc()) {
        $cancellations[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'cancellations' => $cancellations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>