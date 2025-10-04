<?php
session_start();

// Check if user is logged in using your existing system
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

// Get user data from session (from your login system)
$user_name = $_SESSION['name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_address = $_SESSION['address'] ?? '';
$user_contact = $_SESSION['contact-number'] ?? '';

// Handle order processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    require_once 'config.php';
    
    try {
        // Get form data
        $user_email = $_SESSION["email"];
        $full_name = $_POST['full_name'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $city = $_POST['city'];
        $postal_code = $_POST['postal_code'];
        $payment_method = $_POST['payment_method'];
        $order_notes = $_POST['order_notes'] ?? '';
        
        // Get selected items from session
        $selected_items = json_decode($_POST['selected_items'], true);
        $total_amount = floatval($_POST['total_amount']);
        
        if (empty($selected_items)) {
            throw new Exception('No items selected for checkout');
        }
        
        // Start transaction
        $conn->autocommit(FALSE);
        
        // Insert order into orders table
        $order_stmt = $conn->prepare("INSERT INTO orders (user_email, full_name, phone, address, city, postal_code, payment_method, total_amount, order_notes, order_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
        $order_stmt->bind_param("sssssssds", $user_email, $full_name, $phone, $address, $city, $postal_code, $payment_method, $total_amount, $order_notes);
        
        if (!$order_stmt->execute()) {
            throw new Exception('Failed to create order');
        }
        
        $order_id = $conn->insert_id;
        
        // Insert order items
        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($selected_items as $item) {
            $product_id = intval($item['id']);
            $product_name = $item['name'];
            $product_price = floatval($item['price']);
            $quantity = intval($item['quantity']);
            $subtotal = $product_price * $quantity;
            
            $item_stmt->bind_param("iisdid", $order_id, $product_id, $product_name, $product_price, $quantity, $subtotal);
            
            if (!$item_stmt->execute()) {
                throw new Exception('Failed to insert order item');
            }
            
            // Remove selected items from cart
            if (isset($_SESSION['cart'][$product_id])) {
                unset($_SESSION['cart'][$product_id]);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['order_success'] = true;
        $_SESSION['order_id'] = $order_id;
        
        header("Location: order_success.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Order failed: " . $e->getMessage();
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
            margin-top: 150px;
        }
        
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .checkout-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .order-summary {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="tel"]:focus,
        input[type="email"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        .payment-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .payment-option:hover {
            border-color: #4CAF50;
        }
        
        .payment-option.selected {
            border-color: #4CAF50;
            background-color: #f0f8f0;
        }
        
        .payment-option input[type="radio"] {
            display: none;
        }
        
        .payment-icon {
            width: 40px;
            height: 40px;
            margin-bottom: 10px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
        }
        
        .item-price {
            color: #666;
            font-size: 14px;
        }
        
        .item-quantity {
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            margin: 0 10px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
        }
        
        .summary-row.total {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #eee;
            margin-top: 15px;
            padding-top: 15px;
        }
        
        .place-order-btn {
            width: 100%;
            background: #4CAF50;
            color: white;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s;
        }
        
        .place-order-btn:hover {
            background: #45a049;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="checkout-container">
        <div class="checkout-form">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="checkout-form">
                <!-- Delivery Address Section -->
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
                
                <!-- Payment Method Section -->
                <div class="section-title">💳 Payment Method</div>
                
                <div class="payment-methods">
                    <div class="payment-option" onclick="selectPayment('gcash')">
                        <input type="radio" name="payment_method" value="gcash" id="gcash">
                        <div class="payment-icon"><img src="https://images.seeklogo.com/logo-png/52/2/gcash-logo-png_seeklogo-522261.png" alt="Gcash" class="payment-icon"></div>
                        <div>GCash</div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPayment('mastercard')">
                        <input type="radio" name="payment_method" value="mastercard" id="mastercard">
                        <div class="payment-icon"><img src="https://financialit.net/sites/default/files/1609314895logo-mastercard-mobile_1_4.png" alt="Mastercard" class="payment-icon"></div>
                        <div>Mastercard</div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPayment('paypal')">
                        <input type="radio" name="payment_method" value="paypal" id="paypal">
                        <div class="payment-icon"><img src="https://upload.wikimedia.org/wikipedia/commons/a/a4/Paypal_2014_logo.png" alt="Paypal" class="payment-icon"></div>
                        <div>PayPal</div>
                    </div>
                    
                    <div class="payment-option" onclick="selectPayment('cod')">
                        <input type="radio" name="payment_method" value="cod" id="cod">
                        <div class="payment-icon"><img src="https://static.vecteezy.com/system/resources/previews/023/253/005/non_2x/cash-on-delivery-solid-icons-simple-stock-illustration-stock-vector.jpg" alt="Cash_on_delivery" class="payment-icon"></div>
                        <div>Cash on Delivery</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="order_notes">Order Notes (Optional)</label>
                    <textarea id="order_notes" name="order_notes" rows="3" placeholder="Special instructions for your order..."></textarea>
                </div>
                
                <input type="hidden" name="selected_items" id="selected_items">
                <input type="hidden" name="total_amount" id="total_amount">
                
                <button type="submit" name="place_order" class="place-order-btn">
                    Place Order
                </button>
            </form>
        </div>
        
        <!-- Order Summary -->
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
                <span>₱<span id="shipping-fee">36</span></span>
            </div>
            
            <div class="summary-row">
                <span>Handling Fee</span>
                <span>₱<span id="handling-fee">1</span></span>
            </div>
            
            <div class="summary-row">
                <span>Voucher Discount</span>
                <span>-₱<span id="voucher-discount">2</span></span>
            </div>
            
            <div class="summary-row total">
                <span>Total Payment</span>
                <span>₱<span id="total-payment">0</span></span>
            </div>
        </div>
    </div>
    
    <script>
        // Load selected items from sessionStorage
        let selectedItems = [];
        let checkoutTotal = 0;
        let checkoutItemCount = 0;
        
        // Payment method selection
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
            
            // Check the radio button
            document.getElementById(method).checked = true;
        }
        
        // Load checkout data
        function loadCheckoutData() {
            try {
                const storedItems = sessionStorage.getItem('selectedItems');
                const storedTotal = sessionStorage.getItem('checkoutTotal');
                const storedCount = sessionStorage.getItem('checkoutItemCount');
                
                if (storedItems) {
                    selectedItems = JSON.parse(storedItems);
                    checkoutTotal = parseFloat(storedTotal) || 0;
                    checkoutItemCount = parseInt(storedCount) || 0;
                    
                    renderOrderItems();
                    updateSummary();
                    
                    // Set hidden form fields
                    document.getElementById('selected_items').value = JSON.stringify(selectedItems);
                    document.getElementById('total_amount').value = checkoutTotal;
                } else {
                    // No items selected, redirect back to cart
                    alert('No items selected for checkout');
                    window.location.href = 'AddToCart.php';
                }
            } catch (error) {
                console.error('Error loading checkout data:', error);
                window.location.href = 'AddToCart.php';
            }
        }
        
        function renderOrderItems() {
            const container = document.getElementById('order-items-container');
            
            if (!selectedItems || selectedItems.length === 0) {
                container.innerHTML = '<p>No items selected</p>';
                return;
            }
            
            container.innerHTML = selectedItems.map(item => `
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
        
        function updateSummary() {
            const merchandiseSubtotal = selectedItems.reduce((total, item) => {
                return total + (parseFloat(item.price) * parseInt(item.quantity));
            }, 0);
            
            const shippingFee = 36;
            const handlingFee = 1;
            const voucherDiscount = 2;
            const totalPayment = merchandiseSubtotal + shippingFee + handlingFee - voucherDiscount;
            
            document.getElementById('merchandise-subtotal').textContent = merchandiseSubtotal.toFixed(2);
            document.getElementById('total-payment').textContent = totalPayment.toFixed(2);
            
            // Update hidden total amount field
            document.getElementById('total_amount').value = totalPayment;
        }
        
        // Form validation
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return;
            }
            
            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('No items to checkout');
                return;
            }
        });
        
        // Load data when page loads
        document.addEventListener('DOMContentLoaded', loadCheckoutData);
    </script>
</body>
</html>