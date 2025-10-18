<?php
session_start();

if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

$user_name = $_SESSION['name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_address = $_SESSION['address'] ?? '';
$user_contact = $_SESSION['contact-number'] ?? '';

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    require_once 'config.php';
    
    try {
        $conn->begin_transaction();
        
        $user_email = $_SESSION["email"];
        $full_name = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        $payment_method = $_POST['payment_method'] ?? '';
        $order_notes = $_POST['order_notes'] ?? '';
        
        if (empty($full_name) || empty($phone) || empty($address) || empty($city) || empty($postal_code) || empty($payment_method)) {
            throw new Exception('All delivery fields are required');
        }
        
        $selected_items_json = $_POST['selected_items'] ?? '[]';
        $selected_items = json_decode($selected_items_json, true);
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        
        if (!$selected_items || !is_array($selected_items) || count($selected_items) === 0) {
            throw new Exception('No items selected for checkout');
        }
        
        if ($total_amount <= 0) {
            throw new Exception('Invalid total amount');
        }
        
        // Verify stock availability before creating order
        $stock_errors = [];

        foreach ($selected_items as $item) {
            $product_id = intval($item['id']);
            $requested_qty = intval($item['quantity']);
            
            $stock_stmt = $conn->prepare("SELECT display_quantity, product_name FROM product_tbl WHERE product_id = ? FOR UPDATE");
            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if ($stock_row = $stock_result->fetch_assoc()) {
                $available_display = $stock_row['display_quantity'];
                $product_name = $stock_row['product_name'];
                
                if ($available_display < $requested_qty) {
                    $stock_errors[] = [
                        'product_id' => $product_id,
                        'product_name' => $product_name,
                        'requested' => $requested_qty,
                        'available' => $available_display
                    ];
                }
            } else {
                $stock_errors[] = [
                    'product_id' => $product_id,
                    'product_name' => $item['name'] ?? 'Unknown',
                    'error' => 'Product not found in database'
                ];
            }
            $stock_stmt->close();
        }

        // If stock errors exist, rollback and show message
        if (!empty($stock_errors)) {
            $conn->rollback();
            
            $error_message = "<strong>⚠️ Sorry, some items are no longer available:</strong><br><br>";
            foreach ($stock_errors as $error) {
                if (isset($error['available'])) {
                    if ($error['available'] == 0) {
                        $error_message .= "• <strong>{$error['product_name']}</strong>: Out of stock<br>";
                    } else {
                        $error_message .= "• <strong>{$error['product_name']}</strong>: Only {$error['available']} available (You requested {$error['requested']})<br>";
                    }
                } else {
                    $error_message .= "• <strong>{$error['product_name']}</strong>: {$error['error']}<br>";
                }
            }
            $error_message .= "<br><em>Please return to your cart and update the quantities.</em>";
        } else {
            // All stock available - proceed with order
            
            // Step 1: Create main order record with payment_status = 'pending_verification'
            $payment_status = 'pending_verification';
            $order_stmt = $conn->prepare("INSERT INTO orders (user_email, full_name, phone, address, city, postal_code, payment_method, total_amount, order_notes, order_date, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', ?)");
            $order_stmt->bind_param("sssssssdss", $user_email, $full_name, $phone, $address, $city, $postal_code, $payment_method, $total_amount, $order_notes, $payment_status);
            
            if (!$order_stmt->execute()) {
                throw new Exception('Failed to create order');
            }
            
            $order_id = $conn->insert_id;
            $order_stmt->close();
            
            // Step 2: Prepare statements for order items and stock updates
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $stock_update_stmt = $conn->prepare("UPDATE product_tbl SET display_quantity = display_quantity - ? WHERE product_id = ?");
            $transaction_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes) VALUES (?, 'customer_purchase', ?, ?, ?, ?, ?, ?)");

            // Step 3: Process each item - STOCK DECREASES HERE AT CHECKOUT
            foreach ($selected_items as $item) {
                $product_id = intval($item['id']);
                $product_name = $item['name'];
                $product_price = floatval($item['price']);
                $quantity = intval($item['quantity']);
                $subtotal = $product_price * $quantity;
                
                // Get current quantities for logging
                $log_stmt = $conn->prepare("SELECT inventory_quantity, display_quantity FROM product_tbl WHERE product_id = ?");
                $log_stmt->bind_param("i", $product_id);
                $log_stmt->execute();
                $log_result = $log_stmt->get_result();
                $log_data = $log_result->fetch_assoc();
                $log_stmt->close();
                
                $inv_before = $log_data['inventory_quantity'];
                $display_before = $log_data['display_quantity'];
                $display_after = $display_before - $quantity;
                
                // Insert order item
                $item_stmt->bind_param("iisdid", $order_id, $product_id, $product_name, $product_price, $quantity, $subtotal);
                if (!$item_stmt->execute()) {
                    throw new Exception('Failed to insert order item for: ' . $product_name);
                }
                
                // Decrease DISPLAY quantity
                $stock_update_stmt->bind_param("ii", $quantity, $product_id);
                if (!$stock_update_stmt->execute()) {
                    throw new Exception('Failed to update stock for product: ' . $product_name);
                }
                
                // Log transaction
                $notes = "Customer purchase - Order #{$order_id}";
                $negative_qty = -$quantity;
                $transaction_stmt->bind_param("iiiiiss", $product_id, $negative_qty, $inv_before, $inv_before, $display_before, $display_after, $notes);
                if (!$transaction_stmt->execute()) {
                    throw new Exception('Failed to log transaction for: ' . $product_name);
                }
                
                // Remove from cart session
                if (isset($_SESSION['cart'][$product_id])) {
                    unset($_SESSION['cart'][$product_id]);
                }
            }

            // Close prepared statements
            $item_stmt->close();
            $stock_update_stmt->close();
            $transaction_stmt->close();
            
            // Commit transaction
            $conn->commit();
            $conn->close();
            
            // Set success message and redirect to GCash payment
            $_SESSION['order_id'] = $order_id;
            $_SESSION['order_total'] = $total_amount;
            
            header("Location: gcashPayment.php");
            exit();
        }
        
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
            try {
                $conn->rollback();
            } catch (Exception $rollbackEx) {
                error_log("Rollback failed: " . $rollbackEx->getMessage());
            }
            $conn->close();
        }
        
        $error_message = "Order failed: " . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - La Gal & Co.</title>
    <link rel="stylesheet" href="carts.css">
    <link rel="stylesheet" href="checkout.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="checkout-container">
        <div class="checkout-form">
            <?php if ($error_message): ?>
                <div class="error-message">
                    <?php echo $error_message; ?>
                    <a href="AddToCart.php" class="back-to-cart-btn">← Back to Cart</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="checkout-form">
                <div class="section-title">📍 Delivery Address</div>
                
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                        </div>
                        <div>
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user_contact); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Street Address *</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user_address); ?>" required>
                </div>
                
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label for="city">City *</label>
                            <input type="text" id="city" name="city" required>
                        </div>
                        <div>
                            <label for="postal_code">Postal Code *</label>
                            <input type="text" id="postal_code" name="postal_code" required>
                        </div>
                    </div>
                </div>
                
                <div class="section-title">💳 Payment Method</div>
                
                <div class="payment-methods">
                    <div class="payment-option" onclick="selectPayment('gcash', event)">
                        <input type="radio" name="payment_method" value="gcash" id="gcash" checked>
                        <div class="payment-icon"><img src="https://gadgetsmagazine.com.ph/wp-content/uploads/2020/05/GCASH-logo.jpg" alt="Gcash" style="width: 40px; height: 40px;"></div>
                        <div>GCash</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="order_notes">Order Notes (Optional)</label>
                    <textarea id="order_notes" name="order_notes" rows="3" placeholder="Special instructions for your order..."></textarea>
                </div>
                
                <input type="hidden" name="selected_items" id="selected_items" value="">
                <input type="hidden" name="total_amount" id="total_amount" value="">
                <input type="hidden" name="place_order" value="1">
                
                <button type="submit" class="place-order-btn" id="submit-btn">
                    Proceed to Payment
                </button>
            </form>
        </div>
        
        <div class="order-summary">
            <div class="section-title">📋 Order Summary</div>
            
            <div id="order-items-container">
                <!-- Order items will be loaded here -->
            </div>
            
            <div class="summary-row">
                <span>Merchandise Subtotal</span>
                <span>₱<span id="merchandise-subtotal">0</span></span>
            </div>
            
            <div class="summary-row">
                <span>Shipping Subtotal</span>
                <span>₱<span id="shipping-fee">25</span></span>
            </div>
            
            <div class="summary-row total">
                <span>Total Payment</span>
                <span>₱<span id="total-payment">25</span></span>
            </div>
        </div>
    </div>
    
    <script>
        function selectPayment(method, event) {
            event.preventDefault();
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            event.currentTarget.classList.add('selected');
            document.getElementById(method).checked = true;
        }
        
        function loadCheckoutData() {
            try {
                const storedItems = sessionStorage.getItem('selectedItems');
                const storedTotal = sessionStorage.getItem('checkoutTotal');
                
                if (storedItems) {
                    const selectedItems = JSON.parse(storedItems);
                    const checkoutTotal = parseFloat(storedTotal) || 0;
                    
                    renderOrderItems(selectedItems);
                    updateSummary(selectedItems, checkoutTotal);
                    
                    document.getElementById('selected_items').value = JSON.stringify(selectedItems);
                    document.getElementById('total_amount').value = checkoutTotal.toFixed(2);
                } else {
                    alert('No items selected for checkout. Redirecting to cart...');
                    window.location.href = 'AddToCart.php';
                }
            } catch (error) {
                console.error('Error loading checkout data:', error);
                alert('Error loading checkout data. Redirecting to cart...');
                window.location.href = 'AddToCart.php';
            }
        }
        
        function renderOrderItems(items) {
            const container = document.getElementById('order-items-container');
            
            if (!items || items.length === 0) {
                container.innerHTML = '<p>No items selected</p>';
                return;
            }
            
            container.innerHTML = items.map(item => `
                <div class="order-item">
                    <div class="item-info">
                        <div class="item-name">${item.name}</div>
                        <div class="item-price">₱${parseFloat(item.price).toFixed(2)}</div>
                    </div>
                    <div class="item-quantity">×${item.quantity}</div>
                    <div class="item-total">₱${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)}</div>
                </div>
            `).join('');
        }
        
        function updateSummary(items, total) {
            const merchandiseSubtotal = items.reduce((total, item) => {
                return total + (parseFloat(item.price) * parseInt(item.quantity));
            }, 0);
            
            document.getElementById('merchandise-subtotal').textContent = merchandiseSubtotal.toFixed(2);
            document.getElementById('total-payment').textContent = total.toFixed(2);
        }
        
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return;
            }
            
            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        });
        
        document.addEventListener('DOMContentLoaded', loadCheckoutData);
    </script>
</body>
</html>