<?php
header('Content-Type: application/json');
require_once 'config.php';

$query = $_GET['query'] ?? '';
$response = ['success' => false, 'products' => []];

if (!empty($query) && strlen($query) >= 2) {
    $search_term = "%{$query}%";
    
    // Search in product name and description, limit to 8 results
    $stmt = $conn->prepare("
        SELECT 
            product_id,
            product_name,
            product_price,
            category,
            image_path,
            display_quantity
        FROM product_tbl 
        WHERE (product_name LIKE ? OR product_description LIKE ?)
        AND display_quantity > 0
        ORDER BY 
            CASE 
                WHEN product_name LIKE ? THEN 1 
                WHEN product_description LIKE ? THEN 2 
                ELSE 3 
            END,
            product_name ASC
        LIMIT 8
    ");
    
    $search_term_start = "{$query}%";
    $stmt->bind_param('ssss', $search_term, $search_term, $search_term_start, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['products'][] = [
                'product_id' => $row['product_id'],
                'product_name' => htmlspecialchars($row['product_name']),
                'product_price' => $row['product_price'],
                'category' => htmlspecialchars($row['category']),
                'image_path' => htmlspecialchars($row['image_path']),
                'in_stock' => $row['display_quantity'] > 0
            ];
        }
        $response['success'] = true;
    }
    
    $stmt->close();
}

$conn->close();
echo json_encode($response);