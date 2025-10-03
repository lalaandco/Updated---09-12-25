<?php
session_start();
$isLoggedIn = isset($_SESSION["email"]);
$page = $_GET['page'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet"href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="forIndex.css">
    <?php if ($page === 'login'): ?>
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <link rel="stylesheet" href="login.css">
    <?php endif; ?>
    <title>La Gal & Co. Official Store</title>
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
                        <?php if ($isLoggedIn): ?>
                            <!-- Logged in search (no form, just input) -->
                            <input type="text" placeholder="Search" class="search-input">
                            <div class="search-icon icon">
                                <svg viewBox="0 0 24 24">
                                    <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 
                                             6.5 6.5 0 1 0 9.5 16c1.61 0 
                                             3.09-.59 4.23-1.57l.27.28v.79l5 
                                             4.99L20.49 19l-4.99-5zm-6 
                                             0C7.01 14 5 11.99 5 9.5S7.01 
                                             5 9.5 5 14 7.01 14 9.5 11.99 
                                             14 9.5 14z"/>
                                </svg>
                            </div>
                        <?php else: ?>
                            <!-- Guest search form -->
                            <form action="" method="get" class="search-form">
                                <input type="text" name="query" placeholder="Search" class="search-input" required>
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
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                        <!-- Logged in user menu -->
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

                            <!-- Dropdown info -->
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
                        <!-- Guest login link -->
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
                            <!-- Truck icon SVG for tracking orders -->
                            <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                                <path d="M3 3h13v13H3V3zm15 3h3l1 4v6h-2a2 2 0 0 1-4 0h-2a2 2 0 0 1-4 0H7a2 2 0 0 1-4 0H1V3h2v13h1a2 2 0 0 1 4 0h2a2 2 0 0 1 4 0h2a2 2 0 0 1 4 0h1v-5.5L20 6z"/>
                            </svg>
                        </div>
                    </a>
                    
                    <a href="AddToCart.php">
                        <div class="icon cart-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
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
                <!-- remove onerror and use correct relative path -->
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
            // show login/register fragment inside the content area when requested
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
                    <!-- Card 1 - Direct link to For Him -->
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

                    <!-- Card 2 - Direct link to For Her -->
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

                    <!-- Card 3 - Direct link to Others -->
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
                <!-- Product 1 - Now fully clickable -->
                <a href="buyProduct.php?id=1" class="product-link">
                    <div class="box">
                        <div class="box-img">
                            <img src="images/ForHim.png">
                        </div>
                        <h3>Cnalb Tnom</h3>
                        <div class="inbox">
                            <span class="price">₱360.00</span>
                        
                            <button class="add-to-cart-btn" onclick="addToCart(event, 1)">
                                <i class='bx bx-cart-add'></i>
                            </button>
                        </div>
                        <div class="rating">
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                        </div>
                    </div>
                </a>

                <!-- Product 2 - Now fully clickable -->
                <a href="buyProduct.php?id=2" class="product-link">
                    <div class="box">
                        <div class="box-img">
                            <img src="images/ForHer.png">
                        </div>
                        <h3>Lenahc Ecnahc</h3>
                        <div class="inbox">
                            <span class="price">₱480.00</span>
                            <button class="add-to-cart-btn" onclick="addToCart(event, 2)">
                                <i class='bx bx-cart-add'></i>
                            </button>
                        </div>
                        <div class="rating">
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                        </div>
                    </div>
                </a>

                <!-- Product 3 - Now fully clickable -->
                <a href="buyProduct.php?id=3" class="product-link">
                    <div class="box">
                        <div class="box-img">
                            <img src="images/ForHim.png">
                        </div>
                        <h3>Deerc Sutneva</h3>
                        <div class="inbox">
                            <span class="price">₱360.00</span>
                            <button class="add-to-cart-btn" onclick="addToCart(event, 3)">
                                <i class='bx bx-cart-add'></i>
                            </button>
                        </div>
                        <div class="rating">
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i> 
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                        </div>
                    </div>
                </a>

                <!-- Product 4 - Now fully clickable -->
                <a href="buyProduct.php?id=4" class="product-link">
                    <div class="box">
                        <div class="box-img">
                            <img src="images/ForHer.png">
                        </div>
                        <h3>Ecal Allinav</h3>
                        <div class="inbox">
                            <span class="price">₱460.00</span>
                            <button class="add-to-cart-btn" data-product-id="1" onclick="addToCart(event, 1)"><i class='bx bx-cart-add'></i></button>
                        </div>
                        <div class="rating">
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                            <i class='bx bxs-star'></i>
                        </div>
                    </div>
                </a>
            </div>
        </section>

    </section>

    <section class="cta-content">
        <div class="cta"> <!-- Add 'perfume-theme' class for brown theme -->
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
        // Toggle dropdown when clicking user icon
        const userMenu = document.getElementById('userMenu');
        if (userMenu) {
            userMenu.addEventListener('click', () => {
                userMenu.classList.toggle('active');
            });

            // Close dropdown when clicking outside
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
    // For logged-in users, use AJAX to add to cart
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
        console.log('Raw response:', text); // Debug log
        try {
            const data = JSON.parse(text);
            if (data.success) {
                alert('Product added to cart!');
                updateCartCount(); // Update the cart badge
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
    // For guests, show login prompt
    if (confirm('You need to log in to add items to your cart. Go to login page?')) {
        window.location.href = 'login.php';
    }
    <?php endif; ?>
}

// Function to update cart count (improved)
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
        // Set default count to 0 if there's an error
        const cartBadges = document.querySelectorAll('.cart-count');
        cartBadges.forEach(badge => {
            badge.textContent = '0';
        });
    }
}
    </script>
    

    <script src="scriptIndex.js"></script>
</body>
</html>