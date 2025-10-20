<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Handle walk-in sale submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $conn->begin_transaction();
        
        $customer_name = $_POST['customer_name'] ?? 'Walk-in Customer';
        $payment_method = $_POST['payment_method'] ?? '';
        $products_json = $_POST['products'] ?? '[]';
        
        if (empty($payment_method)) {
            throw new Exception('Payment method is required');
        }
        
        $products = json_decode($products_json, true);
        if (!$products || count($products) === 0) {
            throw new Exception('No products selected');
        }
        
        $total_amount = 0;
        
        // Verify warehouse stock availability
        foreach ($products as $item) {
            $product_id = intval($item['product_id']);
            $quantity = intval($item['quantity']);
            
            if ($quantity <= 0) {
                throw new Exception('Invalid quantity for product');
            }
            
            $stock_stmt = $conn->prepare("SELECT inventory_quantity, product_name FROM product_tbl WHERE product_id = ? FOR UPDATE");
            $stock_stmt->bind_param("i", $product_id);
            $stock_stmt->execute();
            $stock_result = $stock_stmt->get_result();
            
            if (!$stock_row = $stock_result->fetch_assoc()) {
                throw new Exception('Product not found');
            }
            
            if ($stock_row['inventory_quantity'] < $quantity) {
                throw new Exception("Insufficient warehouse stock for {$stock_row['product_name']}. Available: {$stock_row['inventory_quantity']}");
            }
            
            $total_amount += floatval($item['price']) * $quantity;
            $stock_stmt->close();
        }
        
        // Create walk-in sale record
        $sale_stmt = $conn->prepare("INSERT INTO walkin_sales (customer_name, payment_method, total_amount, sale_date, created_by) VALUES (?, ?, ?, NOW(), ?)");
        $admin_email = $_SESSION['admin_email'] ?? $_SESSION['name'] ?? 'admin';
        $sale_stmt->bind_param("ssds", $customer_name, $payment_method, $total_amount, $admin_email);
        
        if (!$sale_stmt->execute()) {
            throw new Exception('Failed to create sale record');
        }
        
        $sale_id = $conn->insert_id;
        $sale_stmt->close();
        
        // Process each product - DECREASE WAREHOUSE ONLY
        foreach ($products as $item) {
            $product_id = intval($item['product_id']);
            $quantity = intval($item['quantity']);
            $price = floatval($item['price']);
            $product_name = $item['product_name'];
            
            // Insert sale item
            $item_stmt = $conn->prepare("INSERT INTO walkin_sale_items (sale_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
            $subtotal = $price * $quantity;
            $item_stmt->bind_param("iisidi", $sale_id, $product_id, $product_name, $quantity, $price, $subtotal);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to record sale item: {$product_name}");
            }
            $item_stmt->close();
            
            // Get current warehouse quantity before update
            $qty_stmt = $conn->prepare("SELECT inventory_quantity FROM product_tbl WHERE product_id = ?");
            $qty_stmt->bind_param("i", $product_id);
            $qty_stmt->execute();
            $qty_result = $qty_stmt->get_result();
            $qty_row = $qty_result->fetch_assoc();
            $qty_stmt->close();
            
            $warehouse_before = $qty_row['inventory_quantity'];
            $warehouse_after = $warehouse_before - $quantity;
            
            // DECREASE WAREHOUSE ONLY - display stays same
            $update_stmt = $conn->prepare("UPDATE product_tbl SET inventory_quantity = inventory_quantity - ? WHERE product_id = ?");
            $update_stmt->bind_param("ii", $quantity, $product_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update stock for: {$product_name}");
            }
            $update_stmt->close();
            
            // Log transaction
            $trans_stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes) VALUES (?, 'walk_in_purchase', ?, ?, ?, ?, ?, ?)");
            $negative_qty = -$quantity;
            $notes = "Walk-in purchase - Sale #{$sale_id}";
            $display_qty = 0; // Display doesn't change for walk-in sales
            $trans_stmt->bind_param("iiiiiss", $product_id, $negative_qty, $warehouse_before, $warehouse_after, $display_qty, $display_qty, $notes);
            $trans_stmt->execute();
            $trans_stmt->close();
        }
        
        $conn->commit();
        $_SESSION['success_message'] = "Walk-in sale completed! Sale #" . str_pad($sale_id, 8, '0', STR_PAD_LEFT) . " - Total: ₱" . number_format($total_amount, 2);
        header('Location: adminWalkInSales.php');
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: adminWalkInSales.php');
        exit();
    }
}

// Get all products for sale
$products_query = "SELECT product_id, product_name, product_price, inventory_quantity, display_quantity, category FROM product_tbl ORDER BY product_name ASC";
$all_products = $conn->query($products_query)->fetch_all(MYSQLI_ASSOC);

renderAdminHeader()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminAddStyle.css">
    <link rel="stylesheet" href="adminWalkInSales.css">
    <title>Walk-In Customer Sales - POS</title>
</head>
<body>
    <div class="walkin-container">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="product-selector">
            <h3><i class='bx bxs-book-content'></i> Select Products</h3>
            
            <div style="margin-bottom: 15px;">
                <button class="category-btn active" onclick="filterCategory('all')">All</button>
                <button class="category-btn" onclick="filterCategory('ForHim')">For Him</button>
                <button class="category-btn" onclick="filterCategory('ForHer')">For Her</button>
                <button class="category-btn" onclick="filterCategory('Others')">Others</button>
            </div>

            <div class="product-list" id="product-list">
                <?php foreach ($all_products as $product): ?>
                    <button type="button" class="product-btn" data-category="<?php echo $product['display_quantity'] > 0 ? 'in-stock' : 'out-of-stock'; ?>" data-product-category="<?php echo htmlspecialchars($product['category'] ?? 'Others'); ?>" data-product-id="<?php echo $product['product_id']; ?>"  onclick="addToCart(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                        <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                        <div class="product-price">₱<?php echo number_format($product['product_price'], 2); ?></div>
                        <div class="product-stock">Warehouse: <?php echo $product['inventory_quantity']; ?></div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pos-panel">
            <div class="cart-section">
                <div class="cart-header">
                    <h3>Shopping Cart</h3>
                    <button onclick="clearCart()" style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">Clear</button>
                </div>
                <div id="cart-items"></div>
                <div class="cart-total">Total: ₱<span id="cart-total">0.00</span></div>
            </div>

            <div class="payment-section">
                <h3><i class='bx bx-credit-card'></i> Complete Sale</h3>
                <form id="saleForm" method="POST" onsubmit="submitSale(event)">
                    <input type="hidden" name="action" value="create_walkin_sale">
                    <input type="hidden" name="products" id="productsData">

                    <div class="form-group">
                        <label>Customer Name (Optional)</label>
                        <input type="text" name="customer_name" placeholder="Walk-in Customer">
                    </div>

                    <div class="form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="">Select Payment</option>
                            <option value="gcash">GCash</option>
                            <option value="cod">CASH</option>
                        </select>
                    </div>

                    <button type="submit" class="checkout-btn" id="submitBtn">
                        <i class='bx bx-check'></i> Complete Sale
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let cart = [];

function addToCart(product) {
    console.log('Adding product:', product);
    
    const qty = prompt(`Add ${product.product_name} (Max: ${product.inventory_quantity}):`, '1');
    
    if (qty === null) return;
    
    const quantity = parseInt(qty);
    if (isNaN(quantity) || quantity <= 0 || quantity > product.inventory_quantity) {
        alert('Invalid quantity');
        return;
    }

    const existing = cart.find(item => item.product_id === product.product_id);
    if (existing) {
        existing.quantity += quantity;
    } else {
        cart.push({
            product_id: product.product_id,
            product_name: product.product_name,
            price: parseFloat(product.product_price),
            quantity: quantity
        });
    }

    console.log('Cart updated:', cart);
    renderCart();
}

function removeFromCart(productId) {
    console.log('=== REMOVE FROM CART START ===');
    console.log('Removing product ID:', productId, 'Type:', typeof productId);
    console.log('Cart before removal:', JSON.parse(JSON.stringify(cart)));
    
    // Store original length
    const originalLength = cart.length;
    
    // IMPORTANT: Convert to number for comparison
    const numProductId = parseInt(productId);
    
    // Remove item from cart array - compare as numbers
    cart = cart.filter(item => {
        const itemId = parseInt(item.product_id);
        console.log(`Comparing ${itemId} !== ${numProductId}:`, itemId !== numProductId);
        return itemId !== numProductId;
    });
    
    console.log('Cart after removal:', JSON.parse(JSON.stringify(cart)));
    console.log('Items removed:', originalLength - cart.length);
    console.log('=== REMOVE FROM CART END ===');
    
    // Force immediate update of both cart display and totals
    renderCart();
}

function filterCategory(category) {
    console.log('Filtering by category:', category);
    
    const buttons = document.querySelectorAll('.category-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const products = document.querySelectorAll('.product-btn');
    console.log('Total products:', products.length);
    
    let visibleCount = 0;
    products.forEach(product => {
        const productCat = product.dataset.productCategory;
        console.log('Product category:', productCat);
        
        if (category === 'all') {
            product.style.display = 'block';
            visibleCount++;
        } else if (productCat && productCat.toLowerCase() === category.toLowerCase()) {
            product.style.display = 'block';
            visibleCount++;
        } else {
            product.style.display = 'none';
        }
    });
    console.log('Visible products:', visibleCount);
}

function updateQty(productId, newQty) {
    const qty = parseInt(newQty);
    if (isNaN(qty) || qty <= 0) {
        removeFromCart(productId);
        return;
    }

    const item = cart.find(i => i.product_id === productId);
    if (item) {
        // Get the product's max inventory
        const productElement = document.querySelector(`.product-btn[data-product-id="${productId}"]`);
        const maxInventory = parseInt(productElement.querySelector('.product-stock').textContent.match(/\d+/)[0]);
        
        // Validate quantity against inventory
        if (qty > maxInventory) {
            alert(`Maximum available quantity is ${maxInventory}`);
            renderCart(); // Reset to previous valid state
            return;
        }
        
        item.quantity = qty;
        renderCart();
        updateFormData();
    }
}

function updateFormData() {
    // Update the hidden form field with current cart data
    const formField = document.getElementById('productsData');
    if (formField) {
        formField.value = JSON.stringify(cart);
    }
}

function clearCart() {
    if (cart.length > 0) {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            renderCart();
            updateFormData();
        }
    }
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const totalElement = document.getElementById('cart-total');
    
    if (!container || !totalElement) {
        console.error('Cart container or total element not found!');
        return;
    }
    
    console.log('Rendering cart with items:', cart);
    console.log('Cart length:', cart.length);
    
    // Force clear the container first
    container.innerHTML = '';
    
    let total = 0;

    if (cart.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No items added</p>';
        totalElement.textContent = '0.00';
        updateFormData();
        return;
    }

    // Build HTML string
    let cartHTML = '';
    cart.forEach(item => {
        const subtotal = item.price * item.quantity;
        total += subtotal;
        cartHTML += `
            <div class="cart-item" data-product-id="${item.product_id}">
                <div class="item-name">${item.product_name}</div>
                <div style="color: #666;">₱${item.price.toFixed(2)}</div>
                <input type="number" 
                    class="qty-input" 
                    value="${item.quantity}" 
                    min="1" 
                    max="9999"
                    onchange="updateQty(${item.product_id}, this.value)">
                <button 
                    type="button" 
                    class="remove-item-btn" 
                    onclick="removeFromCart(${item.product_id})">×</button>
            </div>
        `;
    });

    // Set the HTML
    container.innerHTML = cartHTML;
    totalElement.textContent = total.toFixed(2);
    
    console.log('Cart rendered. Total items:', cart.length);
    console.log('Total amount:', total.toFixed(2));
    
    updateFormData();
}

function submitSale(event) {
    event.preventDefault();

    if (cart.length === 0) {
        alert('Please add items to the cart');
        return;
    }

    document.getElementById('productsData').value = JSON.stringify(cart);
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';
    document.getElementById('saleForm').submit();
}

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing cart...');
    renderCart();
    updateFormData();
});
    </script>
</body>
</html>