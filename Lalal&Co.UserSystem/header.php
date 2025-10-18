<?php
    $isLoggedIn = isset($_SESSION["email"]);
    $page = $_GET['page'] ?? '';
?>
<link rel="stylesheet" href="forIndexs.css">
<style>
    .dropdown {
        margin-top: 40px;
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
    </header>
</section>