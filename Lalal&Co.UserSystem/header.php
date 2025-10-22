<?php
    $isLoggedIn = isset($_SESSION["email"]);
    $page = $_GET['page'] ?? '';
?>
<link rel="stylesheet" href="forIndexs.css">
<style>
    .dropdown {
        margin-top: 40px;
    }
    
    /* Search Autocomplete Styles */
    .search-container {
        position: relative;
    }
    
    .search-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 400px;
        overflow-y: auto;
        display: none;
        z-index: 1000;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .search-suggestions.active {
        display: block;
    }
    
    .suggestion-item {
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: background 0.2s;
    }
    
    .suggestion-item:hover {
        background: #f8f9fa;
    }
    
    .suggestion-item:last-child {
        border-bottom: none;
    }
    
    .suggestion-image {
        width: 50px;
        height: 50px;
        object-fit: contain;
        border-radius: 4px;
        background: #f8f9fa;
        padding: 5px;
    }
    
    .suggestion-details {
        flex: 1;
    }
    
    .suggestion-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }
    
    .suggestion-price {
        color: #4CAF50;
        font-weight: 700;
    }
    
    .suggestion-category {
        font-size: 12px;
        color: #666;
        margin-left: 8px;
    }
    
    .no-suggestions {
        padding: 20px;
        text-align: center;
        color: #666;
    }
    
    .search-loading {
        padding: 15px;
        text-align: center;
        color: #666;
    }
</style>
<section id="mainheader-section">
    <header class="header">
        <?php if ($isLoggedIn): ?>
            <div class="welcome-user">
                <h1 id="welcome" style="font">Welcome, <span><?= strtoupper($_SESSION['name']); ?></span></h1>
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
                    <form action="userSearchProducts.php" method="get" class="search-form" id="searchForm">
                        <input 
                            type="text" 
                            name="query" 
                            id="searchInput"
                            placeholder="Search products..." 
                            class="search-input" 
                            autocomplete="off"
                            required>
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
                    <div class="search-suggestions" id="searchSuggestions"></div>
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
        </section>


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
        // Search Autocomplete Functionality
        const searchInput = document.getElementById('searchInput');
        const searchSuggestions = document.getElementById('searchSuggestions');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchSuggestions.classList.remove('active');
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetchSuggestions(query);
            }, 300); // Debounce for 300ms
        });
        
        async function fetchSuggestions(query) {
            try {
                searchSuggestions.innerHTML = '<div class="search-loading">Searching...</div>';
                searchSuggestions.classList.add('active');
                
                const response = await fetch(`search_suggestions.php?query=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success && data.products.length > 0) {
                    displaySuggestions(data.products);
                } else {
                    searchSuggestions.innerHTML = '<div class="no-suggestions">No products found</div>';
                }
            } catch (error) {
                console.error('Search error:', error);
                searchSuggestions.innerHTML = '<div class="no-suggestions">Search error occurred</div>';
            }
        }
        
        function displaySuggestions(products) {
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
            
            searchSuggestions.innerHTML = html;
        }
        
        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.classList.remove('active');
            }
        });
        
        // Show suggestions when focusing on input
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 2) {
                fetchSuggestions(this.value.trim());
            }
        });
    </script>
    </header>
</section>