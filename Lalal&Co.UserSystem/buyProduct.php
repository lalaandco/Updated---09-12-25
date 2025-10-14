<?php
session_start();
require_once 'config.php';
require_once 'productConfig.php'; 
$isLoggedIn = isset($_SESSION["email"]);

// Get product stock from database
    $product_stock = 0;
    $inventory_stock = 0;

    if (isset($_GET['id'])) {
        $product_id = $_GET['id'];
        
        // Get display_quantity (customer-facing stock) and inventory_quantity (warehouse)
        $stock_stmt = $conn->prepare("SELECT display_quantity, inventory_quantity FROM product_tbl WHERE product_id = ?");
        $stock_stmt->bind_param("s", $product_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();

        if ($stock_row = $stock_result->fetch_assoc()) {
            $product_stock = $stock_row['display_quantity']; // Only show display quantity to customers
            $inventory_stock = $stock_row['inventory_quantity']; // Warehouse stock (not shown to customers)
        }
        
        $stock_stmt->close();

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_SESSION['product_name'] ?? 'Product') ?> - La Gal & Co.</title>
    <link rel="stylesheet" href="buyingProducts.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="page">
        <div class="product">
            <div class="image-panel">
                <img src="<?= htmlspecialchars($_SESSION['image'] ?? 'images/default.png') ?>" alt="<?= htmlspecialchars($_SESSION['product_name'] ?? 'Product') ?>">
            </div>

            <div class="details">
                <h1 class="title"><?= htmlspecialchars($_SESSION['product_name'] ?? 'Unknown Product') ?></h1>
                <p class="price"><?= htmlspecialchars($_SESSION['product_price'] ?? '₱0.00') ?></p>

                <div class="stars">
                    ⭐ ⭐ ⭐ ⭐ ⭐ <span class="muted">4.9 | 1k sold | 740 ratings <br></span>
                    <span class="muted">Shipping Fee: ₱25</span>
                </div>

                <!-- Stock Information -->
                <div class="stock-info <?php 
                    if ($product_stock == 0) echo 'out-of-stock';
                    elseif ($product_stock <= 5) echo 'low-stock';
                    else echo 'in-stock';
                ?>">
                    <span class="stock-count">
                        <?php if ($product_stock == 0): ?>
                            ❌ Out of Stock
                        <?php elseif ($product_stock <= 5): ?>
                            ⚠️ Only <?= $product_stock ?> left in stock!
                        <?php else: ?>
                            ✅ In Stock: <?= $product_stock ?> available
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($isLoggedIn): ?>
                    <div class="ship">
                        <span class="muted">Shipping to:</span> <?= htmlspecialchars($_SESSION['address'] ?? 'No address provided') ?>
                    </div>
                    
                    <!-- Quantity selector -->
                    <div class="quantity-selector">
                        <label for="quantity">Quantity:</label>
                        <button type="button" onclick="changeQuantity(-1)" <?= $product_stock == 0 ? 'disabled' : '' ?>>-</button>
                        <input type="number" id="quantity" value="1" min="1" max="<?= $product_stock ?>" <?= $product_stock == 0 ? 'disabled' : '' ?>>
                        <button type="button" onclick="changeQuantity(1)" <?= $product_stock == 0 ? 'disabled' : '' ?>>+</button>
                    </div>
                    
                    <div id="success-message" class="success-message"></div>
                    <div id="error-message" class="error-message"></div>
                    
                    <button class="btn" id="add-to-cart-btn" onclick="addToCart()" <?= $product_stock == 0 ? 'disabled' : '' ?>>
                        <?= $product_stock == 0 ? 'Out of Stock' : 'Add to Bag' ?>
                    </button>
                    
                    <p class="note"><?= htmlspecialchars($_SESSION['product_description'] ?? 'No description available.') ?></p>
                <?php else: ?>
                    <button class="btn" onclick="handleAddToBag()" <?= $product_stock == 0 ? 'disabled' : '' ?>>
                        <?= $product_stock == 0 ? 'Out of Stock' : 'Add to Bag' ?>
                    </button>
                    
                    <p class="note"><b>Top notes:</b> Lavender, Pineapple, Bergamot, Lemon Verbena</p>
                    <p class="note"><b>Middle notes:</b> Red Apple, Dried Fruits, Oak Moss, Geranium, Rose</p>
                    <p class="note"><b>Base notes:</b> Tonka Bean, Sandalwood</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const isLoggedIn = <?= json_encode($isLoggedIn) ?>;
        const productStock = <?= $product_stock ?>;
        const productData = {
            id: <?= json_encode($_GET['id'] ?? '') ?>,
            name: <?= json_encode($_SESSION['product_name'] ?? 'Unknown Product') ?>,
            price: <?= json_encode(preg_replace('/[^\d.]/', '', $_SESSION['product_price'] ?? '0')) ?>,
            image: <?= json_encode($_SESSION['image'] ?? 'images/default.png') ?>,
            stock: productStock
        };

        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let currentValue = parseInt(quantityInput.value);
            let newValue = currentValue + change;
            
            if (newValue < 1) newValue = 1;
            if (newValue > productStock) {
                newValue = productStock;
                showError(`Only ${productStock} items available in stock`);
            }
            
            quantityInput.value = newValue;
        }

        function showError(message) {
            const errorMsg = document.getElementById('error-message');
            errorMsg.textContent = message;
            errorMsg.style.display = 'block';
            setTimeout(() => {
                errorMsg.style.display = 'none';
            }, 3000);
        }

        function showSuccess(message) {
            const successMsg = document.getElementById('success-message');
            successMsg.textContent = message;
            successMsg.style.display = 'block';
            setTimeout(() => {
                successMsg.style.display = 'none';
            }, 3000);
        }

        async function addToCart() {
            if (!isLoggedIn) {
                handleAddToBag();
                return;
            }

            if (productStock == 0) {
                showError('This product is out of stock');
                return;
            }

            const button = document.getElementById('add-to-cart-btn');
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (quantity > productStock) {
                showError(`Only ${productStock} items available in stock`);
                return;
            }
            
            button.disabled = true;
            button.textContent = 'Adding...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'add_to_cart');
                formData.append('product_id', productData.id);
                formData.append('quantity', quantity);
                
                const response = await fetch('AddToCart.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                const result = JSON.parse(text);
                
                if (result.success) {
                    showSuccess(result.message);
                    updateCartBadge();
                    document.getElementById('quantity').value = 1;
                    
                    // Reload page to update stock display
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showError(result.message || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showError('Error: ' + error.message);
            } finally {
                button.disabled = false;
                button.textContent = productStock == 0 ? 'Out of Stock' : 'Add to Bag';
            }
        }

        function handleAddToBag() {
            if (productStock == 0) {
                alert('This product is out of stock');
                return;
            }
            if (confirm('You need to log in to add items to your cart. Go to login page?')) {
                window.location.href = "index.php?page=login";
            }
        }

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

        document.getElementById('quantity')?.addEventListener('change', function() {
            let value = parseInt(this.value);
            if (value < 1) this.value = 1;
            if (value > productStock) {
                this.value = productStock;
                showError(`Only ${productStock} items available in stock`);
            }
        });

        if (isLoggedIn) {
            updateCartBadge();
        }
    </script>
</body>
</html>