<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$sale_id = intval($_GET['sale_id'] ?? 0);

if ($sale_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
    exit();
}

try {
    // Get sale details
    $sale_query = "SELECT * FROM walkin_sales WHERE sale_id = ?";
    $stmt = $conn->prepare($sale_query);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sale = $result->fetch_assoc();
    $stmt->close();
    
    if (!$sale) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sale not found']);
        $conn->close();
        exit();
    }
    
    // Get sale items with product details (image, description)
    $items_query = "
        SELECT 
            wsi.*,
            p.image_path,
            p.product_description
        FROM walkin_sale_items wsi
        LEFT JOIN product_tbl p ON wsi.product_id = p.product_id
        WHERE wsi.sale_id = ?
        ORDER BY wsi.item_id
    ";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items = $items_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'sale' => $sale,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
    if (isset($conn)) {
        $conn->close();
    }
}
?>