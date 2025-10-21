<?php
session_start();

if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

require_once 'shippingFee.php';

$user_name = $_SESSION['name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
$user_contact = $_SESSION['contact-number'] ?? '';

$house_number = $_SESSION['house_number'] ?? '';
$street = $_SESSION['street'] ?? '';
$barangay = $_SESSION['barangay'] ?? '';
$city = $_SESSION['city'] ?? '';
$province = $_SESSION['province'] ?? '';
$postal_code = $_SESSION['postal_code'] ?? '';

$full_address = trim("$house_number $street, $barangay, $city, $province $postal_code");

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    require_once 'config.php';
    
    try {
        // Validate inputs
        $full_name = $_POST['full_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        $house_number = $_POST['house_number'] ?? '';
        $street = $_POST['street'] ?? '';
        $barangay = $_POST['barangay'] ?? '';
        $city_data = $_POST['city'] ?? '';
        $postal_code = $_POST['postal_code'] ?? '';
        
        $city_parts = explode('|', $city_data);
        $city = $city_parts[0] ?? '';
        $province = $city_parts[1] ?? '';
        
        $address = trim("$house_number $street");
        $full_address_string = trim("$house_number $street, $barangay, $city, $province $postal_code");
        
        $payment_method = $_POST['payment_method'] ?? '';
        $order_notes = $_POST['order_notes'] ?? '';
        
        if (empty($full_name) || empty($phone) || empty($house_number) || empty($street) || 
            empty($barangay) || empty($city) || empty($postal_code) || empty($payment_method)) {
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
        
        // IMPORTANT: Only check stock availability - DO NOT deduct stock yet
        $stock_errors = [];
        foreach ($selected_items as $item) {
            $product_id = intval($item['id']);
            $requested_qty = intval($item['quantity']);
            
            $stock_stmt = $conn->prepare("SELECT display_quantity, product_name FROM product_tbl WHERE product_id = ?");
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

        if (!empty($stock_errors)) {
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
            // ✅ Stock is available - Store order data in session (DON'T CREATE ORDER YET)
            $_SESSION['pending_order'] = [
                'user_email' => $user_email,
                'full_name' => $full_name,
                'phone' => $phone,
                'address' => $full_address_string,
                'city' => $city,
                'postal_code' => $postal_code,
                'payment_method' => $payment_method,
                'total_amount' => $total_amount,
                'order_notes' => $order_notes,
                'selected_items' => $selected_items
            ];
            
            $conn->close();
            
            // Redirect to payment page WITHOUT creating order
            header("Location: gcashPayment.php");
            exit();
        }
        
    } catch (Exception $e) {
        if (isset($conn) && $conn) {
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
    <title>GCash Payment - Lalal & Co.</title>
    <link rel="stylesheet" href="checkout.css">
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
                    <div class="address-grid">
                        <div>
                            <label for="house_number">House/Unit Number *</label>
                            <input type="text" id="house_number" name="house_number" value="<?php echo htmlspecialchars($house_number); ?>" required>
                        </div>
                        <div>
                            <label for="street">Street Name *</label>
                            <input type="text" id="street" name="street" value="<?php echo htmlspecialchars($street); ?>" required>
                        </div>
                        <div class="full-width">
                            <label for="barangay">Barangay *</label>
                            <input type="text" id="barangay" name="barangay" value="<?php echo htmlspecialchars($barangay); ?>" required>
                        </div>
                        <div>
                            <label for="city">City *</label>
                            <select name="city" id="city" required>
                                <option value="">Select City</option>
                                <optgroup label="Metro Manila">
                                    <?php
                                    $metro_cities = ['Manila', 'Quezon City', 'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig', 'Pateros', 'San Juan', 'Taguig', 'Valenzuela'];
                                    foreach ($metro_cities as $mc) {
                                        $value = "$mc|Metro Manila";
                                        $selected = ($city == $mc && $province == 'Metro Manila') ? 'selected' : '';
                                        echo "<option value='$value' $selected>$mc</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="CALABARZON">
                                    <?php
                                    $calabarzon_cities = [
                                        'Antipolo|Rizal',
                                        'Bacoor|Cavite',
                                        'Dasmariñas|Cavite',
                                        'Imus|Cavite',
                                        'Biñan|Laguna',
                                        'Calamba|Laguna',
                                        'San Pedro|Laguna',
                                        'Santa Rosa|Laguna'
                                    ];
                                    foreach ($calabarzon_cities as $cc) {
                                        $parts = explode('|', $cc);
                                        $city_name = $parts[0];
                                        $prov = $parts[1];
                                        $value = "$city_name|$prov";
                                        $selected = ($city == $city_name && $province == $prov) ? 'selected' : '';
                                        echo "<option value='$value' $selected>$city_name</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Visayas">
                                    <?php
                                    $visayas = ['Cebu City|Cebu', 'Iloilo City|Iloilo', 'Bacolod|Negros Occidental'];
                                    foreach ($visayas as $v) {
                                        $parts = explode('|', $v);
                                        $city_name = $parts[0];
                                        $prov = $parts[1];
                                        $value = "$city_name|$prov";
                                        $selected = ($city == $city_name && $province == $prov) ? 'selected' : '';
                                        echo "<option value='$value' $selected>$city_name</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Mindanao">
                                    <?php
                                    $mindanao = ['Davao City|Davao del Sur', 'Zamboanga City|Zamboanga del Sur'];
                                    foreach ($mindanao as $m) {
                                        $parts = explode('|', $m);
                                        $city_name = $parts[0];
                                        $prov = $parts[1];
                                        $value = "$city_name|$prov";
                                        $selected = ($city == $city_name && $province == $prov) ? 'selected' : '';
                                        echo "<option value='$value' $selected>$city_name</option>";
                                    }
                                    ?>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label for="postal_code">Postal Code *</label>
                            <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>" maxlength="4" pattern="[0-9]{4}" required>
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
            
            <div id="order-items-container"></div>
            
            <div class="summary-row">
                <span>Merchandise Subtotal</span>
                <span>₱<span id="merchandise-subtotal">0</span></span>
            </div>
            
            <div class="summary-row">
                <span>Shipping Fee</span>
                <span>₱<span id="shipping-subtotal"></span></span>
            </div>
            
            <div class="summary-row total">
                <span>Total Payment</span>
                <span>₱<span id="total-payment">25</span></span>
            </div>
        </div>
    </div>
    
    <script>
        let currentShippingFee = 25;
        let selectedItems = [];
        let merchandiseSubtotal = 0;
        
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
                    selectedItems = JSON.parse(storedItems);
                    merchandiseSubtotal = parseFloat(storedTotal) || 0;
                    
                    renderOrderItems(selectedItems);
                    updateSummary();
                    
                    document.getElementById('selected_items').value = JSON.stringify(selectedItems);
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
        
        function updateSummary() {
            document.getElementById('merchandise-subtotal').textContent = merchandiseSubtotal.toFixed(2);
            document.getElementById('shipping-subtotal').textContent = currentShippingFee;
            const totalWithShipping = merchandiseSubtotal + currentShippingFee;
            document.getElementById('total-payment').textContent = totalWithShipping.toFixed(2);
            document.getElementById('total_amount').value = totalWithShipping.toFixed(2);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const citySelect = document.getElementById('city');
            citySelect.addEventListener('change', async function() {
                const cityValue = this.value;
                
                if (!cityValue) {
                    currentShippingFee = 25;
                    updateSummary();
                    return;
                }
                
                try {
                    const formData = new FormData();
                    formData.append('city', cityValue);
                    
                    const response = await fetch('./get_shipping_fee.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        currentShippingFee = parseInt(data.fee);
                    } else {
                        currentShippingFee = 75;
                    }
                } catch (error) {
                    console.error('Error fetching shipping fee:', error);
                    currentShippingFee = 75;
                }
                
                updateSummary();
            });
            
            loadCheckoutData();
        });
        
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
    </script>
</body>
</html>