<?php
session_start();
require_once 'config.php';
$isLoggedIn = isset($_SESSION["email"]);

// Get all products grouped by category with ratings
$forHimProducts = $conn->query("
    SELECT 
        product_id,
        product_name,
        product_price,
        image_path,
        category,
        display_quantity,
        average_rating,
        total_ratings
    FROM product_tbl 
    WHERE category = 'ForHim' 
    ORDER BY product_id ASC
");

$forHerProducts = $conn->query("
    SELECT 
        product_id,
        product_name,
        product_price,
        image_path,
        category,
        display_quantity,
        average_rating,
        total_ratings
    FROM product_tbl 
    WHERE category = 'ForHer' 
    ORDER BY product_id ASC
");

$othersProducts = $conn->query("
    SELECT 
        product_id,
        product_name,
        product_price,
        image_path,
        category,
        display_quantity,
        average_rating,
        total_ratings
    FROM product_tbl 
    WHERE category = 'Others' 
    ORDER BY product_id ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Categories - La Gal & Co.</title>
    <link rel="stylesheet" href="productCategories.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Rating Styles for Category Page */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 10px 0;
            font-size: 14px;
        }
        
        .product-rating .stars {
            color: #ffc107;
            font-size: 16px;
            display: flex;
            gap: 2px;
        }
        
        .product-rating .star-icon {
            color: #ddd;
        }
        
        .product-rating .star-icon.filled {
            color: #ffc107;
        }
        
        .product-rating .rating-text {
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }
        
        .product-rating .rating-count {
            color: #999;
            font-size: 12px;
        }
        
        .box {
            position: relative;
        }
        
        .out-of-stock-badge, .low-stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 10;
        }
        
        .out-of-stock-badge {
            background: #f44336;
            color: white;
        }
        
        .low-stock-badge {
            background: #ff9800;
            color: white;
        }
        
        
        .box.out-of-stock::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <section class="product" id="product">
        <div class="select-category">
            <a href="#ForHimCollection" class="category-link active">For Him</a>
            <a href="#ForHerCollection" class="category-link">For Her</a>
            <a href="#OthersCollection" class="category-link">Others</a>
        </div>

        <!-- FOR HIM COLLECTION -->
        <section id="ForHimCollection">
            <div class="middle-text">
                <h2><span>For Him Collection</span></h2>
            </div>
            <div class="product-contents">
                <?php while ($product = $forHimProducts->fetch_assoc()): ?>
                    <?php 
                    $isOutOfStock = $product['display_quantity'] <= 0;
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
                            <?php endif; ?>
                            
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            
                            <!-- Product Rating Display -->
                            <?php if ($product['total_ratings'] > 0): ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon <?php echo $i <= round($product['average_rating']) ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo number_format($product['average_rating'], 1); ?></span>
                                <span class="rating-count">(<?php echo number_format($product['total_ratings']); ?>)</span>
                            </div>
                            <?php else: ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">No ratings yet</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- FOR HER COLLECTION -->
        <section id="ForHerCollection">
            <div class="middle-text">
                <h2><span>For Her Collection</span></h2>
            </div>
            <div class="product-contents">
                <?php while ($product = $forHerProducts->fetch_assoc()): ?>
                    <?php 
                    $isOutOfStock = $product['display_quantity'] <= 0;
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
                            <?php endif; ?>
                            
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            
                            <!-- Product Rating Display -->
                            <?php if ($product['total_ratings'] > 0): ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon <?php echo $i <= round($product['average_rating']) ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo number_format($product['average_rating'], 1); ?></span>
                                <span class="rating-count">(<?php echo number_format($product['total_ratings']); ?>)</span>
                            </div>
                            <?php else: ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">No ratings yet</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>

        <!-- OTHERS COLLECTION -->
        <section id="OthersCollection">
            <div class="middle-text">
                <h2><span>Others Collection</span></h2>
            </div>
            <div class="product-contents">
                <?php while ($product = $othersProducts->fetch_assoc()): ?>
                    <?php 
                    $isOutOfStock = $product['display_quantity'] <= 0;
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
                            <?php endif; ?>
                            
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            
                            <!-- Product Rating Display -->
                            <?php if ($product['total_ratings'] > 0): ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon <?php echo $i <= round($product['average_rating']) ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo number_format($product['average_rating'], 1); ?></span>
                                <span class="rating-count">(<?php echo number_format($product['total_ratings']); ?>)</span>
                            </div>
                            <?php else: ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">No ratings yet</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categoryLinks = document.querySelectorAll('.category-link');
            const categorySections = document.querySelectorAll('#ForHimCollection, #ForHerCollection, #OthersCollection');
            
            function hideAllSections() {
                categorySections.forEach(section => {
                    section.style.display = 'none';
                });
            }
            
            function removeActiveClasses() {
                categoryLinks.forEach(link => {
                    link.classList.remove('active');
                });
            }
            
            function showSection(targetId) {
                const targetSection = document.querySelector(targetId);
                if (targetSection) {
                    hideAllSections();
                    removeActiveClasses();
                    targetSection.style.display = 'block';
                    const correspondingLink = document.querySelector(`a[href="${targetId}"]`);
                    if (correspondingLink) {
                        correspondingLink.classList.add('active');
                    }
                }
            }
            
            function handleInitialHash() {
                const hash = window.location.hash;
                if (hash && (hash === '#ForHimCollection' || hash === '#ForHerCollection' || hash === '#OthersCollection')) {
                    showSection(hash);
                } else {
                    showSection('#ForHimCollection');
                }
            }
            
            handleInitialHash();
            
            categoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    if (href.startsWith('#')) {
                        e.preventDefault();
                        window.history.pushState(null, null, href);
                        showSection(href);
                    }
                });
            });
            
            window.addEventListener('hashchange', function() {
                handleInitialHash();
            });
        });
    </script>
</body>
</html>