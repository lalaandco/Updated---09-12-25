<?php
// cart_api.php - Pure API endpoint for cart operations with stock management
session_start();

// Disable all output buffering and error display to ensure clean JSON
while (ob_get_level()) {
    ob_end_clean();
}
ini_set('display_errors', 0);
error_reporting(0);

// Set headers to prevent caching and ensure JSON response
header('Content-Type: application/json; charset=utf-8');
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
            
            if ($quantity <= 0) {
                $response = ['success' => false, 'message' => 'Quantity must be greater than 0'];
                break;
            }
            
            // Get database connection
            require_once 'config.php';
            
            // Check stock availability
            $stock_check = $conn->prepare("SELECT display_quantity, inventory_quantity, product_name, product_price, image_path FROM product_tbl WHERE product_id = ?");
            $stock_check->bind_param("i", $productId);
            $stock_check->execute();
            $stock_result = $stock_check->get_result();
            
            if ($stock_row = $stock_result->fetch_assoc()) {
                $available_display = $stock_row['display_quantity'];
                $available_inventory = $stock_row['inventory_quantity'];
                $product_name = $stock_row['product_name'];
                $product_price = $stock_row['product_price'];
                $image_path = $stock_row['image_path'];
                
                // Check if enough DISPLAY stock is available
                if ($available_display < $quantity) {
                    if ($available_inventory > 0) {
                        $response = ['success' => false, 'message' => "Only {$available_display} items available for purchase right now"];
                    } else {
                        $response = ['success' => false, 'message' => "Only {$available_display} items available in stock"];
                    }
                    $stock_check->close();
                    $conn->close();
                    break;
                }
                
                // Begin transaction for stock update
                $conn->begin_transaction();
                
                try {
                    $display_before = $available_display;
                    $display_after = $available_display - $quantity;
                    
                    // Update display quantity
                    $update_stock = $conn->prepare("UPDATE product_tbl SET display_quantity = display_quantity - ? WHERE product_id = ?");
                    $update_stock->bind_param("ii", $quantity, $productId);
                    $update_stock->execute();
                    $update_stock->close();
                    
                    // Log transaction
                    $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes) VALUES (?, 'customer_purchase', ?, ?, ?, ?, ?, ?)");
                    $negative_qty = -$quantity;
                    $notes = "Added to cart by customer";
                    $log_stmt->bind_param("iiiiiss", $productId, $negative_qty, $available_inventory, $available_inventory, $display_before, $display_after, $notes);
                    $log_stmt->execute();
                    $log_stmt->close();
                    
                    $conn->commit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Error updating stock: ' . $e->getMessage()];
                    $stock_check->close();
                    $conn->close();
                    break;
                }
                
                $stock_check->close();
                
                // Initialize cart if not exists
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                // Clean price
                $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product_price));
                
                if (isset($_SESSION['cart'][$productId])) {
                    // Product already in cart, update quantity
                    $_SESSION['cart'][$productId]['quantity'] += $quantity;
                    $response = ['success' => true, 'message' => 'Item quantity updated in cart'];
                } else {
                    // Add new product to cart
                    $_SESSION['cart'][$productId] = [
                        'id' => $productId,
                        'name' => $product_name,
                        'price' => $cleanPrice,
                        'image' => $image_path,
                        'quantity' => $quantity,
                        'selected' => true
                    ];
                    $response = ['success' => true, 'message' => 'Item added to cart'];
                }
                
                $conn->close();
                
            } else {
                $response = ['success' => false, 'message' => 'Product not found'];
                $stock_check->close();
                $conn->close();
            }
            break;
            
        case 'remove_from_cart':
            $productId = intval($_POST['product_id'] ?? 0);
            
            if (isset($_SESSION['cart'][$productId])) {
                $removedQuantity = $_SESSION['cart'][$productId]['quantity'];
                
                // Restore display quantity
                require_once 'config.php';
                $conn->begin_transaction();
                
                try {
                    $get_qty = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                    $get_qty->bind_param("i", $productId);
                    $get_qty->execute();
                    $qty_result = $get_qty->get_result();
                    
                    if ($qty_row = $qty_result->fetch_assoc()) {
                        $current_display = $qty_row['display_quantity'];
                        $new_display = $current_display + $removedQuantity;
                        
                        // Restore display quantity
                        $update_qty = $conn->prepare("UPDATE product_tbl SET display_quantity = ? WHERE product_id = ?");
                        $update_qty->bind_param("ii", $new_display, $productId);
                        $update_qty->execute();
                        $update_qty->close();
                        
                        // Log restoration
                        $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, display_before, display_after, notes) VALUES (?, 'cart_removal', ?, ?, ?, ?)");
                        $notes = "Item removed from cart - quantity restored";
                        $log_stmt->bind_param("iiiis", $productId, $removedQuantity, $current_display, $new_display, $notes);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    $get_qty->close();
                    $conn->commit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                }
                
                $conn->close();
                
                unset($_SESSION['cart'][$productId]);
                $response = ['success' => true, 'message' => 'Item removed from cart'];
            } else {
                $response = ['success' => false, 'message' => 'Item not found in cart'];
            }
            break;
            
        case 'update_quantity':
            $productId = intval($_POST['product_id'] ?? 0);
            $newQuantity = intval($_POST['quantity'] ?? 0);
            
            if ($newQuantity <= 0) {
                // Remove item if quantity is 0 or less
                if (isset($_SESSION['cart'][$productId])) {
                    $removedQuantity = $_SESSION['cart'][$productId]['quantity'];
                    
                    require_once 'config.php';
                    $conn->begin_transaction();
                    
                    try {
                        $get_qty = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                        $get_qty->bind_param("i", $productId);
                        $get_qty->execute();
                        $qty_result = $get_qty->get_result();
                        
                        if ($qty_row = $qty_result->fetch_assoc()) {
                            $current_display = $qty_row['display_quantity'];
                            $new_display = $current_display + $removedQuantity;
                            
                            $update_qty = $conn->prepare("UPDATE product_tbl SET display_quantity = ? WHERE product_id = ?");
                            $update_qty->bind_param("ii", $new_display, $productId);
                            $update_qty->execute();
                            $update_qty->close();
                        }
                        
                        $get_qty->close();
                        $conn->commit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                    }
                    
                    $conn->close();
                    
                    unset($_SESSION['cart'][$productId]);
                    $response = ['success' => true, 'message' => 'Item removed from cart'];
                } else {
                    $response = ['success' => false, 'message' => 'Item not found'];
                }
            } else {
                if (isset($_SESSION['cart'][$productId])) {
                    $oldQuantity = $_SESSION['cart'][$productId]['quantity'];
                    $quantityDiff = $newQuantity - $oldQuantity;
                    
                    if ($quantityDiff != 0) {
                        require_once 'config.php';
                        
                        // Check stock if increasing quantity
                        if ($quantityDiff > 0) {
                            $stock_check = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                            $stock_check->bind_param("i", $productId);
                            $stock_check->execute();
                            $stock_result = $stock_check->get_result();
                            
                            if ($stock_row = $stock_result->fetch_assoc()) {
                                $available_display = $stock_row['display_quantity'];
                                
                                if ($available_display < $quantityDiff) {
                                    $response = ['success' => false, 'message' => "Only {$available_display} additional items available"];
                                    $stock_check->close();
                                    $conn->close();
                                    break;
                                }
                            }
                            $stock_check->close();
                        }
                        
                        $conn->begin_transaction();
                        
                        try {
                            $get_qty = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                            $get_qty->bind_param("i", $productId);
                            $get_qty->execute();
                            $qty_result = $get_qty->get_result();
                            
                            if ($qty_row = $qty_result->fetch_assoc()) {
                                $current_display = $qty_row['display_quantity'];
                                $new_display = $current_display - $quantityDiff;
                                
                                $update_qty = $conn->prepare("UPDATE product_tbl SET display_quantity = ? WHERE product_id = ?");
                                $update_qty->bind_param("ii", $new_display, $productId);
                                $update_qty->execute();
                                $update_qty->close();
                                
                                $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, display_before, display_after, notes) VALUES (?, 'cart_update', ?, ?, ?, ?)");
                                $notes = "Cart quantity updated from {$oldQuantity} to {$newQuantity}";
                                $log_stmt->bind_param("iiiis", $productId, -$quantityDiff, $current_display, $new_display, $notes);
                                $log_stmt->execute();
                                $log_stmt->close();
                                
                                $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
                                $response = ['success' => true, 'message' => 'Quantity updated'];
                            }
                            
                            $get_qty->close();
                            $conn->commit();
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            $response = ['success' => false, 'message' => 'Error updating quantity: ' . $e->getMessage()];
                        }
                        
                        $conn->close();
                    } else {
                        $response = ['success' => true, 'message' => 'Quantity unchanged'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Item not found in cart'];
                }
            }
            break;
            
        case 'toggle_selection':
            $productId = intval($_POST['product_id'] ?? 0);
            $selected = $_POST['selected'] === 'true';
            
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]['selected'] = $selected;
                $response = ['success' => true, 'message' => 'Selection updated'];
            } else {
                $response = ['success' => false, 'message' => 'Item not found in cart'];
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