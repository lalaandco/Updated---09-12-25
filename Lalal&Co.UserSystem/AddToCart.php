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
                
                // Add to cart logic
                if (!isset($_SESSION['cart'])) {
                    $_SESSION['cart'] = [];
                }
                
                if (isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId]['quantity'] += $quantity;
                } else {
                    // Get product details from database
                    require_once 'config.php';
                    $stmt = $conn->prepare("SELECT * FROM product_tbl WHERE product_id = ?");
                    $stmt->bind_param("i", $productId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows > 0) {
                        $product = $result->fetch_assoc();
                        // Clean price by removing currency symbols
                        $cleanPrice = floatval(preg_replace('/[^\d.]/', '', $product['product_price']));
                        
                        $_SESSION['cart'][$productId] = [
                            'id' => $productId,
                            'name' => $product['product_name'],
                            'price' => $cleanPrice,
                            'image' => $product['image_path'],
                            'quantity' => $quantity
                        ];
                        $response = ['success' => true, 'message' => 'Item added to cart'];
                    } else {
                        $response = ['success' => false, 'message' => 'Product not found'];
                    }
                    $stmt->close();
                    $conn->close();
                }
                
                if (empty($response)) {
                    $response = ['success' => true, 'message' => 'Item quantity updated'];
                }
                break;
                
            case 'remove_from_cart':
                $productId = intval($_POST['product_id']);
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                }
                $response = ['success' => true, 'message' => 'Item removed from cart'];
                break;
                
            case 'update_quantity':
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                
                if ($quantity <= 0) {
                    unset($_SESSION['cart'][$productId]);
                } else {
                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['quantity'] = $quantity;
                    }
                }
                
                $response = ['success' => true, 'message' => 'Quantity updated'];
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
                <p class="cart-note">Items in your bag are not reserved — check out now to make them yours.</p>
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
            
            <button class="checkout-btn" onclick="checkout()">
                CHECKOUT
                <span>→</span>
            </button>
            
            <div class="payment-methods">
                <br>
                <p id="paymethods">Accepted Payment Methods</p>
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS1CefFQibodbtysbX6PStx8gLRhlPgfMoLlA&s" alt="Visa" class="payment-icon">
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
                    }
                } catch (error) {
                    console.error('Error updating quantity:', error);
                }
            }

            toggleWishlist(id) {
                console.log('Toggle wishlist for item:', id);
                // Implement wishlist functionality
            }

            getSubtotal() {
                if (!this.cart || this.cart.length === 0) return 0;
                return this.cart.reduce((total, item) => {
                    const price = parseFloat(item.price) || 0;
                    const quantity = parseInt(item.quantity) || 0;
                    return total + (price * quantity);
                }, 0);
            }

            getTotal() {
                const subtotal = this.getSubtotal();
                return subtotal > 0 ? subtotal + this.deliveryFee : 0;
            }

            getTotalItems() {
                if (!this.cart || this.cart.length === 0) return 0;
                return this.cart.reduce((total, item) => total + (parseInt(item.quantity) || 0), 0);
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
                        
                        <button class="wishlist-btn" onclick="cartManager.toggleWishlist(${item.id})" title="Move to wishlist">♡</button>
                        
                        <button class="remove-btn" onclick="cartManager.removeItem(${item.id})" title="Remove item">×</button>
                    </div>
                `).join('');
            }

            updateSummary() {
                const itemCount = this.getTotalItems();
                const subtotal = this.getSubtotal();
                const total = this.getTotal();
                
                document.getElementById('item-count').textContent = itemCount;
                document.getElementById('total-amount').textContent = subtotal.toFixed(2);
                document.getElementById('summary-item-count').textContent = itemCount;
                document.getElementById('summary-subtotal').textContent = subtotal.toFixed(2);
                document.getElementById('summary-total').textContent = total.toFixed(2);
                
                // Update delivery fee display
                const deliveryElement = document.getElementById('delivery-fee');
                if (subtotal === 0) {
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

        // Checkout function
        function checkout() {
            if (!cartManager.cart || cartManager.cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            
            const total = cartManager.getTotal();
            const itemCount = cartManager.getTotalItems();
            
            if (confirm(`Proceed to checkout?\n\nItems: ${itemCount}\nTotal: ₱${total.toFixed(2)}`)) {
                window.location.href = 'checkout.php';
            }
        }
    </script>

    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <h2>Please log in to view your cart</h2>
            <a href="login.php" style="padding: 10px 20px; background: #333; color: white; text-decoration: none;">Login</a>
        </div>
    <?php endif; ?>
</body>
</html>