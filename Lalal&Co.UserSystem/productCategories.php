<?php
session_start();
require_once 'config.php';
$isLoggedIn = isset($_SESSION["email"]);

// Get all products grouped by category
$forHimProducts = $conn->query("SELECT * FROM product_tbl WHERE category = 'ForHim' ORDER BY product_id ASC");
$forHerProducts = $conn->query("SELECT * FROM product_tbl WHERE category = 'ForHer' ORDER BY product_id ASC");
$othersProducts = $conn->query("SELECT * FROM product_tbl WHERE category = 'Others' ORDER BY product_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Categories - La Gal & Co.</title>
    <link rel="stylesheet" href="productCategories.css">
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
                    $isOutOfStock = $product['display_quantity'] <= 0;  // Changed from product_quantity
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;  // Changed from product_quantity
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
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
                    $isOutOfStock = $product['display_quantity'] <= 0;  // Changed from product_quantity
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;  // Changed from product_quantity
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
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
                    $isOutOfStock = $product['display_quantity'] <= 0;  // Changed from product_quantity
                    $isLowStock = $product['display_quantity'] > 0 && $product['display_quantity'] < 20;  // Changed from product_quantity
                    ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box <?php echo $isOutOfStock ? 'out-of-stock' : ''; ?>">
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                            </div>
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                            </div>
                            <?php if ($isOutOfStock): ?>
                                <div class="out-of-stock-badge">Out of Stock</div>
                            <?php elseif ($isLowStock): ?>
                                <div class="low-stock-badge">Low Stock!</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
    </section>

    <script>
        // Your existing JavaScript remains the same
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