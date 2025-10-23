<?php
// Add this at the VERY TOP of header.php (before any other code)

// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION["email"]);
$page = $_GET['page'] ?? '';

// Initialize user notifications if logged in
if ($isLoggedIn) {
    require_once 'config.php';
    require_once 'user_notifications.php';
    
    // Use the existing $conn from config.php
    try {
        $userNotifications = new UserNotifications($conn, $_SESSION["email"]);
        $notificationCount = $userNotifications->getTotalCount();
    } catch (Exception $e) {
        error_log("Error initializing notifications: " . $e->getMessage());
        $notificationCount = 0;
        $userNotifications = null;
    }
}
?>
<link rel="stylesheet" href="forIndexs.css">
<style>
    .dropdown {
        margin-top: 40px;
    }
    
    /* Notification Bell Styles */
    .notification-bell {
        position: relative;
        cursor: pointer;
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #f44336;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    
    .notification-dropdown {
        position: fixed;
        top: 100px;
        right: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        max-width: 420px;
        width: calc(100vw - 40px);
        max-height: 600px;
        overflow: hidden;
        display: none;
        z-index: 9999;
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .notification-dropdown.show {
        display: flex;
        flex-direction: column;
    }
    
    .notification-dropdown-header {
        padding: 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: black;
        color: white;
        flex-shrink: 0;
    }
    
    .notification-dropdown-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    
    .close-notifications {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: white;
        line-height: 1;
        transition: transform 0.2s;
    }
    
    .close-notifications:hover {
        transform: rotate(90deg);
    }
    
    .notification-list {
        overflow-y: auto;
        flex: 1;
        max-height: calc(600px - 140px);
    }
    
    .notification-item {
        padding: 16px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        color: inherit;
        position: relative;
        background: white;
    }
    
    .notification-item:hover {
        background: #f8f9fa;
        transform: translateX(-3px);
    }
    
    .notification-item.unread {
        background: linear-gradient(to right, #e3f2fd 0%, #ffffff 100%);
        border-left: 4px solid #2196F3;
    }
    
    .notification-item.success {
        border-left: 4px solid #4CAF50;
    }
    
    .notification-item.warning {
        border-left: 4px solid #ff9800;
    }
    
    .notification-item.error {
        border-left: 4px solid #f44336;
    }
    
    .notification-icon {
        font-size: 28px;
        min-width: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-item.success .notification-icon {
        color: #4CAF50;
    }
    
    .notification-item.info .notification-icon {
        color: #2196F3;
    }
    
    .notification-item.warning .notification-icon {
        color: #ff9800;
    }
    
    .notification-item.error .notification-icon {
        color: #f44336;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-content h4 {
        margin: 0 0 6px 0;
        font-size: 15px;
        font-weight: 600;
        color: #333;
        line-height: 1.3;
    }
    
    .notification-content p {
        margin: 0;
        font-size: 13px;
        color: #666;
        line-height: 1.5;
    }
    
    .notification-time {
        font-size: 11px;
        color: #999;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .notification-mark-read {
        position: absolute;
        top: 12px;
        right: 12px;
        background: rgba(76, 175, 80, 0.1);
        border: none;
        color: #4CAF50;
        cursor: pointer;
        font-size: 16px;
        padding: 6px;
        border-radius: 50%;
        transition: all 0.3s;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-mark-read:hover {
        background: #4CAF50;
        color: white;
        transform: scale(1.1);
    }
    
    .notification-empty {
        padding: 60px 20px;
        text-align: center;
        color: #999;
    }
    
    .notification-empty i {
        font-size: 64px;
        display: block;
        margin-bottom: 16px;
        color: #ddd;
    }
    
    .notification-empty h3 {
        margin: 0 0 8px 0;
        color: #666;
        font-size: 18px;
    }
    
    .notification-empty p {
        margin: 0;
        font-size: 14px;
    }
    
    .notification-footer {
        padding: 14px 20px;
        text-align: center;
        border-top: 1px solid #e0e0e0;
        background: #f8f9fa;
        flex-shrink: 0;
    }
    
    .notification-footer a {
        color: #202125ff;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: color 0.3s;
    }
    
    .notification-footer a:hover {
        color: #242325ff;
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .notification-dropdown {
            max-width: calc(100vw - 20px);
            right: 10px;
            top: 80px;
        }
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
                    <!-- Notification Bell -->
                    <div class="notification-bell" id="notificationBell">
                        <div class="icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/>
                            </svg>
                        </div>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-dropdown-header">
                            <h3>Notifications (<?php echo $notificationCount; ?>)</h3>
                            <button class="close-notifications" onclick="closeUserNotifications()">×</button>
                        </div>
                        
                        <div class="notification-list" id="notificationsList">
                            <?php 
                            $notifications = $userNotifications->getNotifications();
                            if (empty($notifications)): 
                            ?>
                                <div class="notification-empty">
                                    <i class='bx bx-bell-off'></i>
                                    <h3>All caught up!</h3>
                                    <p>You have no new notifications</p>
                                    <p style="font-size: 12px; margin-top: 12px; color: #999;">We'll notify you when something happens 😊</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="notification-item unread <?php echo $notif['severity']; ?>" 
                                         data-notification-id="<?php echo $notif['notification_id']; ?>"
                                         data-link="<?php echo $notif['link']; ?>">
                                        <button class="notification-mark-read" 
                                                onclick="markUserNotificationRead(event, <?php echo $notif['notification_id']; ?>)" 
                                                title="Mark as read">
                                            <i class='bx bx-check'></i>
                                        </button>
                                        
                                        <div class="notification-icon">
                                            <i class="bx <?php echo $notif['icon']; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                                            <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                            <div class="notification-time">
                                                <i class='bx bx-time-five'></i>
                                                <?php echo userTimeAgo($notif['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-footer">
                            <a href="notification_history.php">
                                <i class='bx bx-history'></i> View All Notifications
                            </a>
                        </div>
                    </div>
                    
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
        
        // Notification Bell Functionality
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        
        if (notificationBell && notificationDropdown) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });
        }
        
        function closeUserNotifications() {
            notificationDropdown.classList.remove('show');
        }
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (notificationDropdown && !notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                closeUserNotifications();
            }
        });
        
        // Handle notification item clicks
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't navigate if clicking the mark-as-read button
                if (e.target.closest('.notification-mark-read')) {
                    return;
                }
                
                const link = this.getAttribute('data-link');
                const notificationId = this.getAttribute('data-notification-id');
                
                // Mark as read and navigate
                if (notificationId) {
                    markUserNotificationRead(e, parseInt(notificationId), link);
                } else if (link) {
                    window.location.href = link;
                }
            });
        });
        
        // Mark user notification as read
        function markUserNotificationRead(event, notificationId, redirectUrl = null) {
            event.stopPropagation();
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            fetch('mark_user_notification_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification from UI
                    const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notifElement) {
                        notifElement.style.transition = 'all 0.3s';
                        notifElement.style.opacity = '0';
                        notifElement.style.transform = 'translateX(20px)';
                        
                        setTimeout(() => {
                            notifElement.remove();
                            
                            // Update badge count
                            updateUserNotificationCount();
                            
                            // Check if list is empty
                            const remainingNotifs = document.querySelectorAll('.notification-item').length;
                            if (remainingNotifs === 0) {
                                document.getElementById('notificationsList').innerHTML = `
                                    <div class="notification-empty">
                                        <i class='bx bx-bell-off'></i>
                                        <h3>All caught up!</h3>
                                        <p>You have no new notifications</p>
                                        <p style="font-size: 12px; margin-top: 12px; color: #999;">We'll notify you when something happens 😊</p>
                                    </div>
                                `;
                            }
                            
                            // Redirect if URL provided
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            }
                        }, 300);
                    }
                } else {
                    console.error('Failed to mark notification as read:', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        // Update notification badge count
        function updateUserNotificationCount() {
            const remainingCount = document.querySelectorAll('.notification-item').length;
            const badge = document.querySelector('.notification-badge');
            const headerCount = document.querySelector('.notification-dropdown-header h3');
            
            if (remainingCount === 0) {
                if (badge) badge.remove();
                if (headerCount) headerCount.textContent = '🔔 Notifications (0)';
            } else {
                if (badge) badge.textContent = remainingCount;
                if (headerCount) headerCount.textContent = `🔔 Notifications (${remainingCount})`;
            }
        }
        
        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('get_user_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > <?php echo $notificationCount; ?>) {
                        location.reload(); // Reload if new notifications
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }, 30000);
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