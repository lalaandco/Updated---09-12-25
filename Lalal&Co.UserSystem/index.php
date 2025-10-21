<?php
session_start();
$isLoggedIn = isset($_SESSION["email"]);
$page = $_GET['page'] ?? '';

// Fetch top 4 best-selling products with ratings
require_once 'config.php';
$best_sellers_query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.product_price,
        p.image_path,
        p.category,
        p.average_rating,
        p.total_ratings,
        COALESCE(SUM(oi.quantity), 0) as total_sold
    FROM product_tbl p
    LEFT JOIN order_items oi ON p.product_id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status != 'cancelled'
    GROUP BY p.product_id, p.product_name, p.product_price, p.image_path, p.category, p.average_rating, p.total_ratings
    ORDER BY total_sold DESC, p.product_id ASC
    LIMIT 4
";
$best_sellers_result = $conn->query($best_sellers_query);
$best_sellers = [];
if ($best_sellers_result && $best_sellers_result->num_rows > 0) {
    while ($row = $best_sellers_result->fetch_assoc()) {
        $best_sellers[] = $row;
    }
}

// If less than 4 products, fill with random products
if (count($best_sellers) < 4) {
    $existing_ids = array_column($best_sellers, 'product_id');
    $id_list = implode(',', $existing_ids ?: [0]);
    
    $additional_query = "
        SELECT 
            product_id,
            product_name,
            product_price,
            image_path,
            category,
            average_rating,
            total_ratings,
            0 as total_sold
        FROM product_tbl
        WHERE product_id NOT IN ($id_list)
        ORDER BY RAND()
        LIMIT " . (4 - count($best_sellers));
    
    $additional_result = $conn->query($additional_query);
    if ($additional_result) {
        while ($row = $additional_result->fetch_assoc()) {
            $best_sellers[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="forIndexs.css">
    <style>
        .box-img img.others-image {
            height: 300px; 
            object-fit: contain;
            background-color: #f8f9fa; 
            padding: 15px;
            box-sizing: border-box;
            width: 100%;
        }
        
        /* Rating Styles for Product Cards */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 8px 0;
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
    </style>
    <?php if ($page === 'login'): ?>
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <link rel="stylesheet" href="login.css">
    <?php endif; ?>
    <title>Lalal & Co. Official Store</title>
</head>
<body>  
    
    <section id="mainheader-section">
        <header class="header">
            <?php if ($isLoggedIn): ?>
                <div class="welcome-user">
                    <h1 id="welcome" >Welcome, <span><?= strtoupper($_SESSION['name']); ?></span></h1>
                </div>
            <?php endif; ?>
            
            <div class="main-header">
                <div class="left-section">
                    <a href="index.php"><img src="images/lcLogo.png" alt="LG Logo" class="logo-small" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"></a>
                    <div class="logo-placeholder logo-small-placeholder" style="display:none;">LG</div>
                </div>
                
                <div class="center-section">
                    <a href="index.php"><img src="images/homepage_title.png" alt="Lalal & Co" class="logo-main" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"></a>
                    <div class="logo-placeholder logo-main-placeholder" style="display:none;">LALAL & CO.</div>
                    
                </div>
                
                <div class="right-section">
                    <div class="search-container">
                        <form action="userSearchProducts.php" method="get" class="search-form">
                            <input type="text" name="query" placeholder="Search products..." class="search-input" required>
                            <button type="submit" class="search-icon icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 
                                            0 0 0 16 9.5 
                                            6.5 6.5 0 1 0 9.5 16
                                            c1.61 0 3.09-.59 4.23-1.57l.27.28v.79
                                            l5 4.99L20.49 19l-4.99-5zm-6 
                                            0C7.01 14 5 11.99 5 9.5
                                            S7.01 5 9.5 5 
                                            14 7.01 14 9.5 
                                            11.99 14 9.5 14z"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                        <div class="user-menu" id="userMenu">
                            <div class="icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 
                                             12s4.48 10 10 10 10-4.48 
                                             10-10S17.52 2 12 2zm0 3c1.66 
                                             0 3 1.34 3 3s-1.34 3-3 
                                             3-3-1.34-3-3 1.34-3 
                                             3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 
                                             4-3.08 6-3.08 1.99 0 
                                             5.97 1.09 6 3.08-1.29 1.94-3.5 
                                             3.22-6 3.22z"/>
                                </svg>
                            </div>

                            <div class="dropdown">
                                <a href="edit.php"><strong>Edit Profile</strong></a>
                                <div><strong>Name:</strong> <?= $_SESSION["name"] ?? "Guest"; ?></div>
                                <div><strong>Address:</strong> <?= $_SESSION["address"] ?? "No Address"; ?></div>
                                <div><strong>Contact Number:</strong> <?= $_SESSION["contact-number"] ?? "00000000000"; ?></div>
                                <div><strong>Email:</strong> <?= $_SESSION["email"] ?? "Guest@gmail.com"; ?></div>
                                <div class="logout-container">
                                    <a href="logout.php" class="logout">Logout</a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php">
                            <div class="icon">
                                <svg viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 
                                        10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 
                                        3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 
                                        1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22
                                        .03-1.99 4-3.08 6-3.08 1.99 0 
                                        5.97 1.09 6 3.08-1.29 1.94-3.5 
                                        3.22-6 3.22z"/>
                                </svg>
                            </div>
                        </a>
                    <?php endif; ?>

                    <a href="my_orders.php<?php if ($isLoggedIn) echo '?page=orders'; ?>">
                        <div class="icon">
                            <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                                <path d="M3 3h13v13H3V3zm15 3h3l1 4v6h-2a2 2 0 0 1-4 0h-2a2 2 0 0 1-4 0H7a2 2 0 0 1-4 0H1V3h2v13h1a2 2 0 0 1 4 0h2a2 2 0 0 1 4 0h2a2 2 0 0 1 4 0h1v-5.5L20 6z"/>
                            </svg>
                        </div>
                    </a>
                    
                    <a href="AddToCart.php">
                        <div class="icon cart-icon">
                            <svg viewBox="0 0 24 24">
                                 <path d="M18 6h-2.5a3.5 3.5 0 0 0-7 0H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2zm-6-2a1.5 1.5 0 0 1 1.5 1.5V6h-3v-.5A1.5 1.5 0 0 1 12 4zm6 16H6V8h12v12z"/>
                            </svg>
                            <span class="cart-count">0</span>
                        </div>
                    </a>
                </div>
            </div>
        </header>
    </section>
        
    <section id="hero">
        <div class="main-home">
            <div class="hero-image">
                <img src="images/perfume.png" alt="Perfume bottle" class="hero-perfume">
            </div>

            <div class="hero-text">
                <h1 class="perfume-title">&nbsp;PERFUME
                <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;that
                <br>&nbsp;&nbsp;&nbsp;&nbsp;completes
                <br>YOU.</h1>
                <a href="#product" target><button class="button">SHOP NOW</button></a>
            </div>
        </div>
    </section>

     <section id="content-section">
        <div class="content">
            <?php
            $page = $_GET['page'] ?? '';
            if ($page === 'login') {
                include __DIR__ . '/login.php';
            } 
            elseif($page === 'cart') {
                include __DIR__ . '/AddToCart.php';
            }
            elseif($page === 'editProfile') {
                include __DIR__ . '/edit.php';
            }
            ?>
        </div>

            <section id="feature">
                <div class="middle-text">
                    <h2>FEATURED PRODUCTS</h2>
                    <p>Discover our exclusive selection of perfumes, handpicked for you.</p>
                </div>

                <div class="feature-content">
                    <div class="row">
                        <div class="main-row">
                            <div class="row-text">
                                <h6>Explore new Perfumes</h6>
                                <div class="card-title">FOR HIM COLLECTIONS</div>
                                <a href="productCategories.php#ForHimCollection" class="row-btn">Show me all</a>
                            </div>
                            <div class="row-img">
                                <img src="images/ForHim.png" alt="perfume for him" class="img1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="main-row">
                            <div class="row-text">
                                <h6>Explore new Perfumes</h6>
                                <div class="card-title">FOR HER COLLECTIONS</div>
                                <a href="productCategories.php#ForHerCollection" class="row-btn">Show me all</a>
                            </div>
                            <div class="row-img">
                                <img src="images/ForHer.png" alt="perfume for her" class="img1">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="main-row">
                            <div class="row-text">
                                <h6>Explore new Perfumes</h6>
                                <div class="card-title">OTHER COLLECTIONS</div>
                                <a href="productCategories.php#OthersCollection" class="row-btn">Show me all</a>
                            </div>
                            <div class="row-img">
                                <img src="images/ForOthers.png" alt="perfume for others" class="img1">
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        
        <section class="product" id="product">
            <div class="middle-text">
                <h2><span>Best Selling of the month</span></h2>
            </div>

            <div class="product-content">
                <?php if (!empty($best_sellers)): ?>
                    <?php foreach ($best_sellers as $product): ?>
                        <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                            <div class="box">
                                <div class="box-img">
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                                </div>
                                <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                <div class="inbox">
                                    <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                                    <button class="add-to-cart-btn" onclick="addToCart(event, <?php echo $product['product_id']; ?>)">
                                        <i class='bx bx-cart-add'></i>
                                    </button>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; width: 100%; padding: 40px;">No products available at the moment.</p>
                <?php endif; ?>
            </div>
        </section>

    </section>

    <section class="cta-content">
        <div class="cta">
            <div class="cta-text">
                <a href="#" class="logo"><img src="images/homepage_title.png" alt="cta-image"></a>
                <h2>Discover Your Signature Scent</h2>
                <p>Join thousands of satisfied customers who found their perfect fragrance with us.</p>
                <a href="#feature" class="btn">Explore Collection</a>
            </div>
        </div>
    </section>

    <section class="contact">
        <div class="main-contact">
            <div class="contact-content">
                <h5>Getting Started</h5>
                <li><a href="#">Release Notes</a></li>
                <li><a href="#">Upgrade Guide</a></li>
                <li><a href="#">Browser Support</a></li>
                <li><a href="#">Dark Mode</a></li>
            </div>

            <div class="contact-content">
                <h5>Explore</h5>
                <li><a href="#">Prototyping</a></li>
                <li><a href="#">Design System</a></li>
                <li><a href="#">Pricing</a></li>
                <li><a href="#">Security</a></li>
            </div>

            <div class="contact-content">
                <h5>Resources</h5>
                <li><a href="#">Best Practices</a></li>
                <li><a href="#">Support</a></li>
                <li><a href="#">Developers</a></li>
                <li><a href="#">Learn Design</a></li>
            </div>

            <div class="contact-content">
                <h5>Community</h5>
                <li><a href="#">Discussion Forums</a></li>
                <li><a href="#">Code of Conduct</a></li>
                <li><a href="#">Contributing</a></li>
                <li><a href="#">API Reference</a></li>
            </div>
        </div>
    </section>

    <div class="end-text">
        <p>© 2025 LaLal & Co. All rights reserved.</p>
    </div>

    <?php if ($isLoggedIn): ?>
    <script>
        const userMenu = document.getElementById('userMenu');
        if (userMenu) {
            userMenu.addEventListener('click', () => {
                userMenu.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (!userMenu.contains(e.target)) {
                    userMenu.classList.remove('active');
                }
            });
        }
    </script>
    <?php endif; ?>

    <script>
        function addToCart(event, productId) {
    event.preventDefault();
    event.stopPropagation();
    
    <?php if ($isLoggedIn): ?>
    const formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    
    fetch('AddToCart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Product added to cart!');
                updateCartCount();
            } else {
                alert('Error adding product to cart: ' + data.message);
            }
        } catch (error) {
            console.error('JSON Parse error:', error);
            console.log('Response was:', text);
            alert('Server error occurred');
        }
    })
    .catch(error => {
        console.error('Network Error:', error);
        alert('Network error occurred');
    });
    <?php else: ?>
    if (confirm('You need to log in to add items to your cart. Go to login page?')) {
        window.location.href = 'login.php';
    }
    <?php endif; ?>
}

async function updateCartCount() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_cart_count');
        
        const response = await fetch('AddToCart.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            const text = await response.text();
            const data = JSON.parse(text);
            if (data.success) {
                const cartBadges = document.querySelectorAll('.cart-count');
                cartBadges.forEach(badge => {
                    badge.textContent = data.count;
                });
            }
        }
    } catch (error) {
        console.error('Error updating cart count:', error);
        const cartBadges = document.querySelectorAll('.cart-count');
        cartBadges.forEach(badge => {
            badge.textContent = '0';
        });
    }
}


const searchInputIndex = document.getElementById('searchInputIndex');
if (searchInputIndex) {
    const searchContainer = searchInputIndex.closest('.search-container');
    let suggestionsDiv = searchContainer.querySelector('.search-suggestions');
    
    // Create suggestions div if it doesn't exist
    if (!suggestionsDiv) {
        suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'search-suggestions';
        suggestionsDiv.id = 'searchSuggestionsIndex';
        searchContainer.appendChild(suggestionsDiv);
    }
    
    let searchTimeout;
    
    searchInputIndex.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            suggestionsDiv.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetchSuggestionsIndex(query, suggestionsDiv);
        }, 300);
    });
    
    async function fetchSuggestionsIndex(query, container) {
        try {
            container.innerHTML = '<div class="search-loading">Searching...</div>';
            container.classList.add('active');
            
            const response = await fetch(`search_suggestions.php?query=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.products.length > 0) {
                displaySuggestionsIndex(data.products, container);
            } else {
                container.innerHTML = '<div class="no-suggestions">No products found</div>';
            }
        } catch (error) {
            console.error('Search error:', error);
            container.innerHTML = '<div class="no-suggestions">Search error occurred</div>';
        }
    }
    
    function displaySuggestionsIndex(products, container) {
        const html = products.map(product => `
            <a href="buyProduct.php?id=${product.product_id}" class="suggestion-item" style="text-decoration: none; color: inherit;">
                <img src="${product.image_path}" alt="${product.product_name}" class="suggestion-image">
                <div class="suggestion-details">
                    <div class="suggestion-name">
                        ${product.product_name}
                        <span class="suggestion-category">${product.category}</span>
                    </div>
                    <div class="suggestion-price">₱${parseFloat(product.product_price).toFixed(2)}</div>
                </div>
            </a>
        `).join('');
        
        container.innerHTML = html;
    }
    
    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInputIndex.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.classList.remove('active');
        }
    });
    
    searchInputIndex.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            fetchSuggestionsIndex(this.value.trim(), suggestionsDiv);
        }
    });
}
    </script>

    

    <script src="scriptIndex.js"></script>
</body>
</html>