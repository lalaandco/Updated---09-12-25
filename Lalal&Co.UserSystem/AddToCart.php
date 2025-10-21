<?php
ob_start();
session_start();

require_once 'shippingFee.php';

$shippingFee = 25; // Default fee

if (isset($_SESSION['city']) && !empty($_SESSION['city'])) {
    $calculator = new JNTShippingCalculator();
    $userCity = $_SESSION['city'];
    
    // Calculate shipping from South Caloocan to user's city
    $calculatedFee = $calculator->calculateShippingFee('South Caloocan', $userCity);
    $shippingFee = $calculatedFee ?: 25;
}

// Store shipping fee in session for checkout
$_SESSION['calculated_shipping_fee'] = $shippingFee;

ini_set('display_errors', 0);
error_reporting(0);

// Handle AJAX requests for cart operations FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear any output buffers to prevent HTML from being sent
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $response = [];
    $isLoggedIn = isset($_SESSION['email']);
    
    try {
        require_once 'config.php';
        
        switch ($_POST['action']) {
            case 'add_to_cart':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if ($quantity <= 0) {
                    $response = ['success' => false, 'message' => 'Quantity must be greater than 0'];
                    break;
                }
                
                // Check stock availability
                $stock_check = $conn->prepare("SELECT display_quantity, product_name, product_price, image_path FROM product_tbl WHERE product_id = ?");
                $stock_check->bind_param("i", $productId);
                $stock_check->execute();
                $stock_result = $stock_check->get_result();
                
                if ($stock_row = $stock_result->fetch_assoc()) {
                    $available_display = $stock_row['display_quantity'];
                    $product_name = $stock_row['product_name'];
                    $product_price = $stock_row['product_price'];
                    $image_path = $stock_row['image_path'];
                    
                    if ($available_display < $quantity) {
                        $response = ['success' => false, 'message' => "Only $available_display items available for purchase"];
                        $stock_check->close();
                        break;
                    }
                    
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    $stock_check->close();
                    break;
                }
                
                $stock_check->close();
                
                // Add to database if logged in, otherwise use session
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    
                    // Check if item already in cart
                    $check_stmt = $conn->prepare("SELECT quantity FROM shopping_cart WHERE user_email = ? AND product_id = ?");
                    $check_stmt->bind_param("si", $user_email, $productId);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        // Update quantity
                        $existing = $check_result->fetch_assoc();
                        $new_quantity = $existing['quantity'] + $quantity;
                        
                        $update_stmt = $conn->prepare("UPDATE shopping_cart SET quantity = ? WHERE user_email = ? AND product_id = ?");
                        $update_stmt->bind_param("isi", $new_quantity, $user_email, $productId);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        $response = ['success' => true, 'message' => 'Item quantity updated in cart'];
                    } else {
                        // Insert new item
                        $insert_stmt = $conn->prepare("INSERT INTO shopping_cart (user_email, product_id, product_name, product_price, quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                        $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product_price));
                        $insert_stmt->bind_param("sisdis", $user_email, $productId, $product_name, $cleanPrice, $quantity, $image_path);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        
                        $response = ['success' => true, 'message' => 'Item added to cart'];
                    }
                } else {
                    // Use session for guests
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }
                    
                    $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product_price));
                    
                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['quantity'] += $quantity;
                        $response = ['success' => true, 'message' => 'Item quantity updated in cart'];
                    } else {
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
                }
                break;
                
            case 'toggle_selection':
                $productId = intval($_POST['product_id']);
                $selected = $_POST['selected'] === 'true';
                
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    // For database cart, we can store selection status if needed
                    // For now, handle in session
                    if (!isset($_SESSION['cart_selection'])) {
                        $_SESSION['cart_selection'] = [];
                    }
                    $_SESSION['cart_selection'][$productId] = $selected;
                    $response = ['success' => true, 'message' => 'Selection updated'];
                } else {
                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['selected'] = $selected;
                        $response = ['success' => true, 'message' => 'Selection updated'];
                    } else {
                        $response = ['success' => false, 'message' => 'Item not found in cart'];
                    }
                }
                break;
                
            case 'remove_from_cart':
                $productId = intval($_POST['product_id']);
                
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    $delete_stmt = $conn->prepare("DELETE FROM shopping_cart WHERE user_email = ? AND product_id = ?");
                    $delete_stmt->bind_param("si", $user_email, $productId);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    $response = ['success' => true, 'message' => 'Item removed from cart'];
                } else {
                    if (isset($_SESSION['cart'][$productId])) {
                        unset($_SESSION['cart'][$productId]);
                        $response = ['success' => true, 'message' => 'Item removed from cart'];
                    } else {
                        $response = ['success' => false, 'message' => 'Item not found in cart'];
                    }
                }
                break;
                
            case 'update_quantity':
                $productId = intval($_POST['product_id']);
                $newQuantity = intval($_POST['quantity']);
                
                if ($newQuantity <= 0) {
                    $response = ['success' => false, 'message' => 'Invalid quantity'];
                    break;
                }
                
                // Check stock
                $stock_check = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                $stock_check->bind_param("i", $productId);
                $stock_check->execute();
                $stock_result = $stock_check->get_result();
                
                if ($stock_row = $stock_result->fetch_assoc()) {
                    $available_display = $stock_row['display_quantity'];
                    
                    if ($available_display < $newQuantity) {
                        $response = ['success' => false, 'message' => "Only $available_display items available"];
                        $stock_check->close();
                        break;
                    }
                }
                $stock_check->close();
                
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    $update_stmt = $conn->prepare("UPDATE shopping_cart SET quantity = ? WHERE user_email = ? AND product_id = ?");
                    $update_stmt->bind_param("isi", $newQuantity, $user_email, $productId);
                    $update_stmt->execute();
                    $update_stmt->close();
                    $response = ['success' => true, 'message' => 'Quantity updated'];
                } else {
                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
                        $response = ['success' => true, 'message' => 'Quantity updated'];
                    } else {
                        $response = ['success' => false, 'message' => 'Item not found in cart'];
                    }
                }
                break;
                
            case 'get_cart':
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    $cart_stmt = $conn->prepare("SELECT product_id as id, product_name as name, product_price as price, image_path as image, quantity FROM shopping_cart WHERE user_email = ?");
                    $cart_stmt->bind_param("s", $user_email);
                    $cart_stmt->execute();
                    $cart_result = $cart_stmt->get_result();
                    
                    $cart = [];
                    while ($item = $cart_result->fetch_assoc()) {
                        $item['selected'] = $_SESSION['cart_selection'][$item['id']] ?? true;
                        $cart[] = $item;
                    }
                    $cart_stmt->close();
                    
                    $response = ['success' => true, 'cart' => $cart];
                } else {
                    $cart = $_SESSION['cart'] ?? [];
                    $response = ['success' => true, 'cart' => array_values($cart)];
                }
                break;
                
            case 'get_cart_count':
                if ($isLoggedIn) {
                    $user_email = $_SESSION['email'];
                    $count_stmt = $conn->prepare("SELECT SUM(quantity) as total FROM shopping_cart WHERE user_email = ?");
                    $count_stmt->bind_param("s", $user_email);
                    $count_stmt->execute();
                    $count_result = $count_stmt->get_result();
                    $count_row = $count_result->fetch_assoc();
                    $count_stmt->close();
                    
                    $totalItems = $count_row['total'] ?? 0;
                } else {
                    $cart = $_SESSION['cart'] ?? [];
                    $totalItems = array_sum(array_column($cart, 'quantity'));
                }
                $response = ['success' => true, 'count' => $totalItems];
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

require_once 'productConfig.php';
$isLoggedIn = isset($_SESSION["email"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="cart.css">
    <title>Your Bag - Lalal & Co.</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if ($isLoggedIn): ?>
    <div class="cart-container">
        <div class="cart-items">
            <div class="cart-header">
                <h1 class="cart-title">YOUR BAG</h1>
                <p class="cart-subtitle">TOTAL (<span id="item-count">0</span> items) ₱<span id="total-amount">0</span></p>
                <p class="cart-note">Items in your bag are not reserved – check out now to make them yours.</p>
            </div>
            
            <div id="cart-items-container">
                <!-- Cart items will be dynamically inserted here -->
            </div>
            
            <div id="empty-cart" class="empty-cart" style="display: none;">
                <h3>Your bag is empty</h3>
                <p>Add some products to get started</p>
                <a href="productCategories.php" class="continue-shopping">Continue Shopping</a>
            </div>
        </div>

        <div class="order-summary">
            <h2 class="summary-title">ORDER SUMMARY</h2>
            
            <div class="selected-summary">
                <strong>Selected Items: <span id="selected-count">0</span></strong>
                <div>Selected Total: ₱<span id="selected-total">0</span></div>
            </div>
            
            <div class="summary-row">
                <span><span id="summary-item-count">0</span> ITEMS</span>
                <span>₱<span id="summary-subtotal">0</span></span>
            </div>
            
            <div class="summary-row">
                <span>Delivery</span>
                <span id="delivery-fee">₱25</span>
            </div>
            
            <div class="summary-row total">
                <span>TOTAL</span>
                <span>₱<span id="summary-total">25</span></span>
            </div>
            
            <button class="checkout-selected-btn" onclick="checkoutSelected()" id="checkout-btn" disabled>
                CHECKOUT SELECTED ITEMS
                <span>→</span>
            </button>
            
            <div class="payment-methods">
                <br>
                <p id="paymethods">Accepted Payment Methods</p>
                <img src="https://gadgetsmagazine.com.ph/wp-content/uploads/2020/05/GCASH-logo.jpg" alt="Gcash" class="payment-icon">
                <img src="https://financialit.net/sites/default/files/1609314895logo-mastercard-mobile_1_4.png" alt="Mastercard" class="payment-icon">
                <img src="https://upload.wikimedia.org/wikipedia/commons/a/a4/Paypal_2014_logo.png" alt="Paypal" class="payment-icon">
                <img src="https://static.vecteezy.com/system/resources/previews/023/253/005/non_2x/cash-on-delivery-solid-icons-simple-stock-illustration-stock-vector.jpg" alt="Cash_on_delivery" class="payment-icon">
            </div>
        </div>
    </div>

    <script>
        class CartManager {
            constructor() {
                this.deliveryFee = 25;
                this.cart = [];
                this.init();
            }

            async init() {
                await this.loadCartFromSession();
                this.renderCart();
                this.updateSummary();
                this.updateCartBadge();
            }

            async loadCartFromSession() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_cart');
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        this.cart = data.cart;
                    } else {
                        this.cart = [];
                    }
                } catch (error) {
                    console.error('Error loading cart:', error);
                    this.cart = [];
                }
            }

            async toggleItemSelection(id, selected) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'toggle_selection');
                    formData.append('product_id', id);
                    formData.append('selected', selected);
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        const item = this.cart.find(item => item.id == id);
                        if (item) item.selected = selected;
                        this.updateSummary();
                    }
                } catch (error) {
                    console.error('Error toggling selection:', error);
                }
            }

            async removeItem(id) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'remove_from_cart');
                    formData.append('product_id', id);
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        await this.loadCartFromSession();
                        this.renderCart();
                        this.updateSummary();
                        this.updateCartBadge();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to remove item'));
                    }
                } catch (error) {
                    console.error('Error removing item:', error);
                    alert('Error removing item. Please try again.');
                }
            }

            async updateQuantity(id, quantity) {
                if (quantity <= 0) {
                    this.removeItem(id);
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('action', 'update_quantity');
                    formData.append('product_id', id);
                    formData.append('quantity', quantity);
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        await this.loadCartFromSession();
                        this.renderCart();
                        this.updateSummary();
                        this.updateCartBadge();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update quantity'));
                        await this.loadCartFromSession();
                        this.renderCart();
                        this.updateSummary();
                    }
                } catch (error) {
                    console.error('Error updating quantity:', error);
                    alert('Error updating quantity. Please try again.');
                }
            }

            getSelectedItems() {
                if (!this.cart || this.cart.length === 0) return [];
                return this.cart.filter(item => item.selected === true || item.selected === undefined);
            }

            getSelectedSubtotal() {
                const selectedItems = this.getSelectedItems();
                return selectedItems.reduce((total, item) => {
                    const price = parseFloat(item.price) || 0;
                    const quantity = parseInt(item.quantity) || 0;
                    return total + (price * quantity);
                }, 0);
            }

            getSelectedTotal() {
                const subtotal = this.getSelectedSubtotal();
                return subtotal > 0 ? subtotal + this.deliveryFee : 0;
            }

            getSelectedItemsCount() {
                const selectedItems = this.getSelectedItems();
                return selectedItems.reduce((total, item) => total + (parseInt(item.quantity) || 0), 0);
            }

            renderCart() {
                const container = document.getElementById('cart-items-container');
                const emptyCart = document.getElementById('empty-cart');
                
                if (!this.cart || this.cart.length === 0) {
                    container.innerHTML = '';
                    emptyCart.style.display = 'block';
                    return;
                }
                
                emptyCart.style.display = 'none';
                
                container.innerHTML = this.cart.map(item => `
                    <div class="cart-item" data-id="${item.id}">
                        <input type="checkbox" class="item-checkbox" 
                               ${item.selected !== false ? 'checked' : ''} 
                               onchange="cartManager.toggleItemSelection(${item.id}, this.checked)">
                        
                        <img src="${item.image}" alt="${item.name}" class="product-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2780%27 fill=%27%23ddd%27%3E%3Crect width=%2780%27 height=%2780%27 fill=%27%23f5f5f5%27/%3E%3Ctext x=%2740%27 y=%2745%27 text-anchor=%27middle%27 font-size=%2710%27 fill=%27%23999%27%3EProduct%3C/text%3E%3C/svg%3E'">
                        
                        <div class="product-details">
                            <div class="product-name">${item.name}</div>
                            <div class="product-price">₱${parseFloat(item.price).toFixed(2)}</div>
                        </div>
                        
                        <div class="quantity-controls">
                            <button class="qty-btn minus" onclick="cartManager.updateQuantity(${item.id}, ${item.quantity - 1})">−</button>
                            <span class="quantity">${item.quantity}</span>
                            <button class="qty-btn plus" onclick="cartManager.updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
                        </div>
                        
                        <button class="remove-btn" onclick="cartManager.removeItem(${item.id})" title="Remove item">×</button>
                    </div>
                `).join('');
            }

            updateSummary() {
                const selectedItems = this.getSelectedItems();
                const selectedCount = selectedItems.length;
                const selectedItemsCount = this.getSelectedItemsCount();
                const selectedSubtotal = this.getSelectedSubtotal();
                const selectedTotal = this.getSelectedTotal();
                
                document.getElementById('selected-count').textContent = selectedCount;
                document.getElementById('selected-total').textContent = selectedSubtotal.toFixed(2);
                
                document.getElementById('item-count').textContent = selectedItemsCount;
                document.getElementById('total-amount').textContent = selectedSubtotal.toFixed(2);
                document.getElementById('summary-item-count').textContent = selectedItemsCount;
                document.getElementById('summary-subtotal').textContent = selectedSubtotal.toFixed(2);
                document.getElementById('summary-total').textContent = selectedTotal.toFixed(2);
                
                const checkoutBtn = document.getElementById('checkout-btn');
                if (selectedCount > 0) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.innerHTML = `CHECKOUT SELECTED ITEMS (${selectedCount}) <span>→</span>`;
                } else {
                    checkoutBtn.disabled = true;
                    checkoutBtn.innerHTML = 'SELECT ITEMS TO CHECKOUT <span>→</span>';
                }
                
                const deliveryElement = document.getElementById('delivery-fee');
                if (selectedSubtotal === 0) {
                    deliveryElement.textContent = '₱0';
                } else {
                    deliveryElement.textContent = `₱${this.deliveryFee}`;
                }
            }

            async updateCartBadge() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_cart_count');
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    if (data.success) {
                        const allCartBadges = document.querySelectorAll('.cart-count, #cart-badge');
                        allCartBadges.forEach(badge => {
                            badge.textContent = data.count;
                        });
                    }
                } catch (error) {
                    console.error('Error updating cart badge:', error);
                }
            }
        }

        const cartManager = new CartManager();

        function checkoutSelected() {
            const selectedItems = cartManager.getSelectedItems();
            
            if (selectedItems.length === 0) {
                alert('Please select items to checkout!');
                return;
            }
            
            const total = cartManager.getSelectedTotal();
            const itemCount = cartManager.getSelectedItemsCount();
            
            sessionStorage.setItem('selectedItems', JSON.stringify(selectedItems));
            sessionStorage.setItem('checkoutTotal', total);
            sessionStorage.setItem('checkoutItemCount', itemCount);
            
            window.location.href = 'checkout.php';
        }
    </script>

    <?php else: ?>
        <div style="text-align: center; padding: 100px; font-family: Arial, sans-serif; margin-top: 100px;">
            <h2 style="margin-bottom: 20px;">Please log in to view your cart</h2>
            <a href="login.php" style="padding: 10px 20px; background: #333; color: white; text-decoration: none;">Login</a>
        </div>
    <?php endif; ?>
</body>
</html>