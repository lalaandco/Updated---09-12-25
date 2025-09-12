<?php
    session_start();
    $isLoggedIn = isset($_SESSION["email"]);
    $page = $_GET['page'] ?? '';
?>
<link rel="stylesheet" href="styleIndex.css">
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
                            <div class="logout"><a href="php/logout.php">Logout</a></div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Guest login link -->
                    <a href="php/login.php">
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

                <div class="icon">
                    <svg viewBox="0 0 24 24">   
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                </div>
                
                    <a href="AddToCart.php">
                        <div class="icon cart-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                            </svg>
                            <span class="cart-count">0</span>
                        </div>
                    </a>
            </div>
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
    </header>
</section>