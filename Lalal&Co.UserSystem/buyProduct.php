<?php
session_start();

require_once 'productConfig.php'; // This sets $_SESSION['product_name'], etc.
$isLoggedIn = isset($_SESSION["email"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_SESSION['product_name'] ?? 'Product') ?> - La Gal & Co.</title>
    <link rel="stylesheet" href="buyingProduct.css">
    <style>
        body {
            padding-top: 120px;
        }
        
        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .btn {
            transition: all 0.3s ease;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
            display: none;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
            display: none;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .quantity-selector button {
            width: 30px;
            height: 30px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .quantity-selector input {
            width: 60px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    
    <div class="page">
        <div class="product">
            <!-- LEFT: image in a light-gray card -->
            <div class="image-panel">
                <img src="<?= htmlspecialchars($_SESSION['image'] ?? 'images/default.png') ?>" alt="<?= htmlspecialchars($_SESSION['product_name'] ?? 'Product') ?>">
            </div>

            <!-- RIGHT: product details -->
            <div class="details">
                <h1 class="title"><?= htmlspecialchars($_SESSION['product_name'] ?? 'Unknown Product') ?></h1>
                <p class="price"><?= htmlspecialchars($_SESSION['product_price'] ?? '₱0.00') ?></p>

                <div class="stars">
                    ⭐ ⭐ ⭐ ⭐ ⭐ <span class="muted">4.9 | 1k sold | 740 ratings <br></span>
                    <span class="muted">Shipping Fee: ₱25</span>
                </div>

                <?php if ($isLoggedIn): ?>
                    <div class="ship">
                        <span class="muted">Shipping to:</span> <?= htmlspecialchars($_SESSION['address'] ?? 'No address provided') ?>
                    </div>
                    
                    <!-- Quantity selector -->
                    <div class="quantity-selector">
                        <label for="quantity">Quantity:</label>
                        <button type="button" onclick="changeQuantity(-1)">-</button>
                        <input type="number" id="quantity" value="1" min="1" max="10">
                        <button type="button" onclick="changeQuantity(1)">+</button>
                    </div>
                    
                    <div id="success-message" class="success-message"></div>
                    <div id="error-message" class="error-message"></div>
                    
                    <button class="btn" id="add-to-cart-btn" onclick="addToCart()">Add to Bag</button>
                    
                    <p class="note"><?= htmlspecialchars($_SESSION['product_description'] ?? 'No description available.') ?></p>
                <?php else: ?>
                    <button class="btn" onclick="handleAddToBag()">Add to Bag</button>
                    
                    <p class="note"><b>Top notes:</b> Lavender, Pineapple, Bergamot, Lemon Verbena</p>
                    <p class="note"><b>Middle notes:</b> Red Apple, Dried Fruits, Oak Moss, Geranium, Rose</p>
                    <p class="note"><b>Base notes:</b> Tonka Bean, Sandalwood</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Get product data from PHP
        const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
        const productData = {
            id: <?= intval($_GET['id'] ?? 0) ?>,
            name: <?= json_encode($_SESSION['product_name'] ?? 'Unknown Product') ?>,
            price: <?= json_encode(preg_replace('/[^\d.]/', '', $_SESSION['product_price'] ?? '0')) ?>,
            image: <?= json_encode($_SESSION['image'] ?? 'images/default.png') ?>
        };

        console.log('Product Data:', productData);
        console.log('User logged in:', isLoggedIn);

        // Quantity management
        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let currentValue = parseInt(quantityInput.value);
            let newValue = currentValue + change;
            
            if (newValue < 1) newValue = 1;
            if (newValue > 10) newValue = 10;
            
            quantityInput.value = newValue;
        }

        // Add to cart function for logged-in users
        async function addToCart() {
            if (!isLoggedIn) {
                handleAddToBag();
                return;
            }

            const button = document.getElementById('add-to-cart-btn');
            const quantity = parseInt(document.getElementById('quantity').value);
            const successMsg = document.getElementById('success-message');
            const errorMsg = document.getElementById('error-message');
            
            // Disable button during request
            button.disabled = true;
            button.textContent = 'Adding...';
            
            // Hide previous messages
            successMsg.style.display = 'none';
            errorMsg.style.display = 'none';
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productData.id);
                formData.append('quantity', quantity);
                
                console.log('Sending request to AddToCart.php with:', {
                    action: 'add_to_cart',
                    product_id: productData.id,
                    quantity: quantity
                });
                
                const response = await fetch('AddToCart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                console.log('Raw response from AddToCart.php:', text);
                
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse error:', parseError);
                    throw new Error('Server returned invalid JSON: ' + text.substring(0, 100));
                }
                
                console.log('Parsed result:', result);
                
                if (result.success) {
                    successMsg.textContent = result.message;
                    successMsg.style.display = 'block';
                    
                    // Update cart badge if it exists
                    updateCartBadge();
                    
                    // Reset quantity to 1
                    document.getElementById('quantity').value = 1;
                } else {
                    errorMsg.textContent = result.message || 'Unknown error occurred';
                    errorMsg.style.display = 'block';
                }
            } catch (error) {
                console.error('Fetch error:', error);
                errorMsg.textContent = 'Error: ' + error.message;
                errorMsg.style.display = 'block';
            } finally {
                // Re-enable button
                button.disabled = false;
                button.textContent = 'Add to Bag';
            }
        }

        // Handle add to bag for non-logged in users
        function handleAddToBag() {
            if (confirm('You need to log in to add items to your cart. Go to login page?')) {
                window.location.href = "index.php?page=login";
            }
        }

        // Update cart badge
        async function updateCartBadge() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_cart_count');
                
                const response = await fetch('AddToCart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    const allCartCounts = document.querySelectorAll('.cart-count, #cart-badge');
                    allCartCounts.forEach(element => {
                        element.textContent = data.count;
                    });
                }
            } catch (error) {
                console.error('Error updating cart badge:', error);
            }
        }

        // Allow quantity input validation
        document.getElementById('quantity')?.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (value < 1) this.value = 1;
            if (value > 10) this.value = 10;
        });

        // Update cart count when page loads
        if (isLoggedIn) {
            updateCartBadge();
        }
    </script>
</body>
</html>