<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Handle AJAX requests for cart operations FIRST - before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear any output buffers to prevent HTML from being sent
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    
    // Prevent any further HTML output
    $response = [];
    
    try {
        switch ($_POST['action']) {
            case 'add_to_cart':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity'] ?? 1);
                
                if ($quantity <= 0) {
                    $response = ['success' => false, 'message' => 'Quantity must be greater than 0'];
                    break;
                }
                
                // ✅ CHECK DISPLAY QUANTITY (what's available for customers to buy)
                require_once 'config.php';
                
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
                            $response = ['success' => false, 'message' => "Only $available_display items available for purchase right now"];
                        } else {
                            $response = ['success' => false, 'message' => "Only $available_display items available in stock"];
                        }
                        $stock_check->close();
                        $conn->close();
                        break;
                    }
                    
                    // ✅ DECREASE DISPLAY QUANTITY (not inventory - that stays in warehouse)
                    $conn->begin_transaction();
                    
                    try {
                        // Get current quantities for transaction log
                        $display_before = $available_display;
                        $display_after = $available_display - $quantity;
                        
                        // Update display quantity
                        $update_stock = $conn->prepare("UPDATE product_tbl SET display_quantity = display_quantity - ? WHERE product_id = ?");
                        $update_stock->bind_param("ii", $quantity, $productId);
                        $update_stock->execute();
                        $update_stock->close();
                        
                        // Log transaction
                        $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes) VALUES (?, 'customer_purchase', ?, ?, ?, ?, ?, ?)");
                        $negative_qty = -$quantity; // Negative because it's a removal
                        $notes = "Added to cart by customer";
                        $log_stmt->bind_param("iiiiis", $productId, $negative_qty, $available_inventory, $available_inventory, $display_before, $display_after, $notes);
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
                    
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                    $stock_check->close();
                    $conn->close();
                    break;
                }
                
                $stock_check->close();
                
                // Add to cart logic
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                if (isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId]['quantity'] += $quantity;
                    $response = ['success' => true, 'message' => 'Item quantity updated in cart'];
                } else {
                    // Clean price by removing currency symbols
                    $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product_price));
                    
                    $_SESSION['cart'][$productId] = [
                        'id' => $productId,
                        'name' => $product_name,
                        'price' => $cleanPrice,
                        'image' => $image_path,
                        'quantity' => $quantity,
                        'selected' => true // Default to selected
                    ];
                    $response = ['success' => true, 'message' => 'Item added to cart'];
                }
                
                $conn->close();
                break;
                
            case 'toggle_selection':
                $productId = intval($_POST['product_id']);
                $selected = $_POST['selected'] === 'true';
                
                if (isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId]['selected'] = $selected;
                    $response = ['success' => true, 'message' => 'Selection updated'];
                } else {
                    $response = ['success' => false, 'message' => 'Item not found in cart'];
                }
                break;
                
            case 'remove_from_cart':
                $productId = intval($_POST['product_id']);
                
                // Restore display quantity when item is removed from cart
                if (isset($_SESSION['cart'][$productId])) {
                    $removedQuantity = $_SESSION['cart'][$productId]['quantity'];
                    
                    require_once 'config.php';
                    $conn->begin_transaction();
                    
                    try {
                        // Get current display quantity
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
                            
                            // Log the restoration
                            $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, display_before, display_after, notes) VALUES (?, 'cart_removal', ?, ?, ?, ?)");
                            $notes = "Item removed from cart - quantity restored";
                            $log_stmt->bind_param("iiis", $productId, $removedQuantity, $current_display, $new_display, $notes);
                            $log_stmt->execute();
                            $log_stmt->close();
                        }
                        
                        $get_qty->close();
                        $conn->commit();
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        // Continue with removal even if restore fails
                    }
                    
                    $conn->close();
                    
                    // Remove from cart session
                    unset($_SESSION['cart'][$productId]);
                    $response = ['success' => true, 'message' => 'Item removed from cart'];
                } else {
                    $response = ['success' => false, 'message' => 'Item not found in cart'];
                }
                break;
                
            case 'update_quantity':
                $productId = intval($_POST['product_id']);
                $newQuantity = intval($_POST['quantity']);
                
                if ($newQuantity <= 0) {
                    // Remove item if quantity is 0 or less
                    $formData = new FormData();
                    $formData.append('action', 'remove_from_cart');
                    $formData.append('product_id', $productId);
                    // This will trigger the case above
                    break;
                }
                
                if (isset($_SESSION['cart'][$productId])) {
                    $oldQuantity = $_SESSION['cart'][$productId]['quantity'];
                    $quantityDiff = $newQuantity - $oldQuantity;
                    
                    if ($quantityDiff != 0) {
                        require_once 'config.php';
                        
                        // Check if we have enough display stock for the increase
                        if ($quantityDiff > 0) {
                            $stock_check = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                            $stock_check->bind_param("i", $productId);
                            $stock_check->execute();
                            $stock_result = $stock_check->get_result();
                            
                            if ($stock_row = $stock_result->fetch_assoc()) {
                                $available_display = $stock_row['display_quantity'];
                                
                                if ($available_display < $quantityDiff) {
                                    $response = ['success' => false, 'message' => "Only $available_display additional items available"];
                                    $stock_check->close();
                                    $conn->close();
                                    break;
                                }
                            }
                            $stock_check->close();
                        }
                        
                        $conn->begin_transaction();
                        
                        try {
                            // Get current display quantity
                            $get_qty = $conn->prepare("SELECT display_quantity FROM product_tbl WHERE product_id = ?");
                            $get_qty->bind_param("i", $productId);
                            $get_qty->execute();
                            $qty_result = $get_qty->get_result();
                            
                            if ($qty_row = $qty_result->fetch_assoc()) {
                                $current_display = $qty_row['display_quantity'];
                                $new_display = $current_display - $quantityDiff;
                                
                                // Update display quantity
                                $update_qty = $conn->prepare("UPDATE product_tbl SET display_quantity = ? WHERE product_id = ?");
                                $update_qty->bind_param("ii", $new_display, $productId);
                                $update_qty->execute();
                                $update_qty->close();
                                
                                // Log the quantity change
                                $log_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, display_before, display_after, notes) VALUES (?, 'cart_update', ?, ?, ?, ?)");
                                $notes = "Cart quantity updated from $oldQuantity to $newQuantity";
                                $log_stmt->bind_param("iiis", $productId, -$quantityDiff, $current_display, $new_display, $notes);
                                $log_stmt->execute();
                                $log_stmt->close();
                                
                                // Update cart session
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
                        // No change in quantity
                        $response = ['success' => true, 'message' => 'Quantity unchanged'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Item not found in cart'];
                }
                break;
                
            case 'get_cart':
                $cart = $_SESSION['cart'] ?? [];
                $response = ['success' => true, 'cart' => array_values($cart)];
                break;
                
            case 'get_cart_count':
                $cart = $_SESSION['cart'] ?? [];
                $totalItems = array_sum(array_column($cart, 'quantity'));
                $response = ['success' => true, 'count' => $totalItems];
                break;
                
            default:
                $response = ['success' => false, 'message' => 'Invalid action'];
                break;
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    }
    
    // Ensure clean JSON output
    echo json_encode($response);
    exit; // Important: Stop execution here to prevent HTML output
}

// Only include other files AFTER handling AJAX requests
require_once 'productConfig.php';
$isLoggedIn = isset($_SESSION["email"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="cart.css">
    <title>Your Bag - La Gal & Co.</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Cart Container -->
    <?php if ($isLoggedIn): ?>
    <div class="cart-container">
        <!-- Cart Items -->
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
                <a href="index.php" class="continue-shopping">Continue Shopping</a>
            </div>
        </div>

        <!-- Order Summary -->
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
        // Cart Management System
        class CartManager {
            constructor() {
                this.deliveryFee = 25;
                this.init();
            }

            async init() {
                await this.loadCartFromSession();
                this.renderCart();
                this.updateSummary();
                this.updateCartBadge();
            }

            // Load cart from PHP session via AJAX
            async loadCartFromSession() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_cart');
                    
                    const response = await fetch('AddToCart.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const text = await response.text();
                    console.log('Raw response:', text); // Debug log
                    
                    const data = JSON.parse(text);
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
                        // Update local cart data
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
                        // Reload cart data
                        await this.loadCartFromSession();
                        this.renderCart();
                        this.updateSummary();
                        this.updateCartBadge();
                    }
                } catch (error) {
                    console.error('Error removing item:', error);
                }
            }

            async updateQuantity(id, quantity) {
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
                        // Reload cart data
                        await this.loadCartFromSession();
                        this.renderCart();
                        this.updateSummary();
                        this.updateCartBadge();
                    } else {
                        alert(data.message || 'Error updating quantity');
                    }
                } catch (error) {
                    console.error('Error updating quantity:', error);
                    alert('Error updating quantity');
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
                        
                        <img src="${item.image}" alt="${item.name}" class="product-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' fill=\'%23ddd\'%3E%3Crect width=\'80\' height=\'80\' fill=\'%23f5f5f5\'/%3E%3Ctext x=\'40\' y=\'45\' text-anchor=\'middle\' font-size=\'10\' fill=\'%23999\'%3EProduct%3C/text%3E%3C/svg%3E'">
                        
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
                
                // Update selected items summary
                document.getElementById('selected-count').textContent = selectedCount;
                document.getElementById('selected-total').textContent = selectedSubtotal.toFixed(2);
                
                // Update main summary
                document.getElementById('item-count').textContent = selectedItemsCount;
                document.getElementById('total-amount').textContent = selectedSubtotal.toFixed(2);
                document.getElementById('summary-item-count').textContent = selectedItemsCount;
                document.getElementById('summary-subtotal').textContent = selectedSubtotal.toFixed(2);
                document.getElementById('summary-total').textContent = selectedTotal.toFixed(2);
                
                // Update checkout button
                const checkoutBtn = document.getElementById('checkout-btn');
                if (selectedCount > 0) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.innerHTML = `CHECKOUT SELECTED ITEMS (${selectedCount}) <span>→</span>`;
                } else {
                    checkoutBtn.disabled = true;
                    checkoutBtn.innerHTML = 'SELECT ITEMS TO CHECKOUT <span>→</span>';
                }
                
                // Update delivery fee display
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

        // Initialize cart manager
        const cartManager = new CartManager();

        // Checkout selected items function
        function checkoutSelected() {
            const selectedItems = cartManager.getSelectedItems();
            
            if (selectedItems.length === 0) {
                alert('Please select items to checkout!');
                return;
            }
            
            const total = cartManager.getSelectedTotal();
            const itemCount = cartManager.getSelectedItemsCount();
            
            // Store selected items in session storage for the checkout page
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