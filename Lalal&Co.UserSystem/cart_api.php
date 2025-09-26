<?php
// cart_api.php - Pure API endpoint for cart operations
session_start();

// Set headers to prevent caching and ensure JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if action is provided
if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action not specified']);
    exit;
}

$response = [];

try {
    switch ($_POST['action']) {
        case 'add_to_cart':
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);
            
            if ($productId <= 0) {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
                break;
            }
            
            // Initialize cart if not exists
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            if (isset($_SESSION['cart'][$productId])) {
                // Product already in cart, update quantity
                $_SESSION['cart'][$productId]['quantity'] += $quantity;
                $response = ['success' => true, 'message' => 'Cart updated'];
            } else {
                // New product, get details from database
                require_once 'config.php';
                
                $stmt = $conn->prepare("SELECT product_id, product_name, product_price, image_path FROM product_tbl WHERE product_id = ?");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $product = $result->fetch_assoc();
                    
                    // Clean price by removing currency symbols and converting to float
                    $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product['product_price']));
                    
                    $_SESSION['cart'][$productId] = [
                        'id' => $productId,
                        'name' => $product['product_name'],
                        'price' => $cleanPrice,
                        'image' => $product['image_path'],
                        'quantity' => $quantity
                    ];
                    
                    $response = ['success' => true, 'message' => 'Product added to cart'];
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                }
                
                $stmt->close();
                $conn->close();
            }
            break;
            
        case 'remove_from_cart':
            $productId = intval($_POST['product_id'] ?? 0);
            
            if (isset($_SESSION['cart'][$productId])) {
                unset($_SESSION['cart'][$productId]);
                $response = ['success' => true, 'message' => 'Item removed from cart'];
            } else {
                $response = ['success' => false, 'message' => 'Item not found in cart'];
            }
            break;
            
        case 'update_quantity':
            $productId = intval($_POST['product_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 0);
            
            if ($quantity <= 0) {
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                    $response = ['success' => true, 'message' => 'Item removed from cart'];
                } else {
                    $response = ['success' => false, 'message' => 'Item not found'];
                }
            } else {
                if (isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId]['quantity'] = $quantity;
                    $response = ['success' => true, 'message' => 'Quantity updated'];
                } else {
                    $response = ['success' => false, 'message' => 'Item not found in cart'];
                }
            }
            break;
            
        case 'get_cart':
            $cart = $_SESSION['cart'] ?? [];
            $response = [
                'success' => true, 
                'cart' => array_values($cart),
                'count' => count($cart)
            ];
            break;
            
        case 'get_cart_count':
            $cart = $_SESSION['cart'] ?? [];
            $totalItems = 0;
            foreach ($cart as $item) {
                $totalItems += intval($item['quantity'] ?? 0);
            }
            $response = ['success' => true, 'count' => $totalItems];
            break;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            $response = ['success' => true, 'message' => 'Cart cleared'];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

// Output clean JSON
echo json_encode($response);
exit;