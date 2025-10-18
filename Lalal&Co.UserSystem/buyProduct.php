<?php
session_start();
require_once 'config.php';
require_once 'productConfig.php'; 
$isLoggedIn = isset($_SESSION["email"]);

// Get product stock and rating from database
$product_stock = 0;
$inventory_stock = 0;
$average_rating = 0;
$total_ratings = 0;
$rating_breakdown = [];

if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    
    // Get product details including ratings
    $product_stmt = $conn->prepare("
        SELECT 
            display_quantity, 
            inventory_quantity,
            average_rating,
            total_ratings,
            rating_5_star,
            rating_4_star,
            rating_3_star,
            rating_2_star,
            rating_1_star
        FROM product_tbl 
        WHERE product_id = ?
    ");
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();

    if ($product_row = $product_result->fetch_assoc()) {
        $product_stock = $product_row['display_quantity'];
        $inventory_stock = $product_row['inventory_quantity'];
        $average_rating = floatval($product_row['average_rating']);
        $total_ratings = intval($product_row['total_ratings']);
        $rating_breakdown = [
            5 => intval($product_row['rating_5_star']),
            4 => intval($product_row['rating_4_star']),
            3 => intval($product_row['rating_3_star']),
            2 => intval($product_row['rating_2_star']),
            1 => intval($product_row['rating_1_star'])
        ];
    }
    $product_stmt->close();
    
    // Get recent reviews
    $reviews_stmt = $conn->prepare("
        SELECT 
            pr.rating,
            pr.review_title,
            pr.review_text,
            pr.created_at,
            u.name as user_name
        FROM product_ratings pr
        JOIN users u ON pr.user_email = u.email
        WHERE pr.product_id = ?
        ORDER BY pr.created_at DESC
        LIMIT 10
    ");
    $reviews_stmt->bind_param("i", $product_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
    $reviews_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_SESSION['product_name'] ?? 'Product') ?> - La Gal & Co.</title>
    <link rel="stylesheet" href="buyingProducts.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .success-message, .error-message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            display: none;
            animation: slideIn 0.3s ease-in-out;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stock-info {
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: 600;
        }
        
        .stock-info.in-stock {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .stock-info.low-stock {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .stock-info.out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }
        
        .quantity-selector button {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            font-size: 20px;
            border-radius: 5px;
        }
        
        .quantity-selector button:hover:not(:disabled) {
            background: #f0f0f0;
        }
        
        .quantity-selector button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .quantity-selector input {
            width: 60px;
            height: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        /* Rating Display Styles */
        .rating-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .rating-summary {
            display: flex;
            gap: 30px;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .rating-score {
            text-align: center;
        }
        
        .rating-number {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            line-height: 1;
        }
        
        .rating-stars-large {
            color: #ffc107;
            font-size: 24px;
            margin: 8px 0;
        }
        
        .rating-count {
            color: #666;
            font-size: 14px;
        }
        
        .rating-breakdown {
            flex: 1;
        }
        
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        
        .rating-bar-label {
            min-width: 60px;
            font-size: 14px;
            color: #666;
        }
        
        .rating-bar {
            flex: 1;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: #ffc107;
            transition: width 0.3s;
        }
        
        .rating-bar-count {
            min-width: 40px;
            text-align: right;
            font-size: 13px;
            color: #666;
        }
        
        .reviews-container {
            margin-top: 30px;
        }
        
        .reviews-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        
        .review-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #333;
        }
        
        .review-date {
            font-size: 12px;
            color: #999;
        }
        
        .review-stars {
            color: #ffc107;
            font-size: 16px;
        }
        
        .review-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .review-text {
            color: #666;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .verified-purchase {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .no-reviews i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .stars-display {
            display: inline-flex;
            gap: 2px;
        }
        
        .star-icon {
            color: #ddd;
        }
        
        .star-icon.filled {
            color: #ffc107;
        }
    </style>
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

                <!-- Rating Display -->
                <div class="stars">
                    <?php if ($total_ratings > 0): ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div class="stars-display">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star-icon <?php echo $i <= round($average_rating) ? 'filled' : ''; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <span style="font-weight: 600; color: #333;"><?php echo number_format($average_rating, 1); ?></span>
                            <span class="muted">(<?php echo number_format($total_ratings); ?> ratings)</span>
                        </div>
                    <?php else: ?>
                        <span class="muted">No ratings yet</span>
                    <?php endif; ?>
                    <br>
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
                    
                    <p class="note"><?= htmlspecialchars($_SESSION['product_description'] ?? 'No description available.') ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ratings and Reviews Section -->
        <?php if ($total_ratings > 0 || !empty($reviews)): ?>
        <div class="rating-section">
            <h2 style="margin: 0 0 20px 0; color: #333;">Customer Ratings & Reviews</h2>
            
            <div class="rating-summary">
                <div class="rating-score">
                    <div class="rating-number"><?php echo number_format($average_rating, 1); ?></div>
                    <div class="rating-stars-large">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?php echo $i <= round($average_rating) ? 'filled' : ''; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-count"><?php echo number_format($total_ratings); ?> ratings</div>
                </div>
                
                <div class="rating-breakdown">
                    <?php for ($star = 5; $star >= 1; $star--): ?>
                        <?php 
                        $count = $rating_breakdown[$star];
                        $percentage = $total_ratings > 0 ? ($count / $total_ratings) * 100 : 0;
                        ?>
                        <div class="rating-bar-item">
                            <div class="rating-bar-label">
                                <?php echo $star; ?> <span style="color: #ffc107;">★</span>
                            </div>
                            <div class="rating-bar">
                                <div class="rating-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="rating-bar-count"><?php echo $count; ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php if (!empty($reviews)): ?>
            <div class="reviews-container">
                <div class="reviews-header">
                    <i class='bx bx-message-rounded-dots'></i> Customer Reviews
                </div>
                
                <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">
                                <?php echo strtoupper(substr($review['user_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div class="reviewer-name">
                                    <?php echo htmlspecialchars($review['user_name']); ?>
                                    <span class="verified-purchase">✓ Verified Purchase</span>
                                </div>
                                <div class="review-date">
                                    <?php echo date('F d, Y', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="<?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($review['review_title'])): ?>
                    <div class="review-title">
                        <?php echo htmlspecialchars($review['review_title']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($review['review_text'])): ?>
                    <div class="review-text">
                        <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php elseif ($total_ratings == 0): ?>
        <div class="rating-section">
            <div class="no-reviews">
                <i class='bx bx-star'></i>
                <h3>No Reviews Yet</h3>
                <p>Be the first to review this product!</p>
            </div>
        </div>
        <?php endif; ?>
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
            if (!quantityInput) return;
            
            let currentValue = parseInt(quantityInput.value) || 1;
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
            if (!errorMsg) return;
            
            errorMsg.textContent = message;
            errorMsg.style.display = 'block';
            setTimeout(() => {
                errorMsg.style.display = 'none';
            }, 3000);
        }

        function showSuccess(message) {
            const successMsg = document.getElementById('success-message');
            if (!successMsg) return;
            
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
            const quantityInput = document.getElementById('quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
            
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const text = await response.text();
                console.log('Response:', text);
                
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Server returned:', text);
                    showError('Invalid server response. Check console for details.');
                    throw new Error('Invalid server response');
                }
                
                if (result.success) {
                    showSuccess(result.message);
                    await updateCartBadge();
                    
                    if (quantityInput) {
                        quantityInput.value = 1;
                    }
                    
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
                button.disabled = productStock == 0;
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
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
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

        const quantityInput = document.getElementById('quantity');
        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                let value = parseInt(this.value) || 1;
                if (value < 1) this.value = 1;
                if (value > productStock) {
                    this.value = productStock;
                    showError(`Only ${productStock} items available in stock`);
                }
            });
        }

        if (isLoggedIn) {
            updateCartBadge();
        }
    </script>
</body>
</html>