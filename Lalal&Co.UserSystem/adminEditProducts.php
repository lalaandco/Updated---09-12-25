<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="adminEditProduct.css">
    <title>Document</title>
</head>
<body>
    <div class="header">
        <div class="left-section">
            <div class="lc-logo">
                <a href="adminIndex.php">
                    <img src="images/lcLogo.png" alt="LC Logo">
                </a>
            </div>
            <div class="back-arrow">
                <i class='bx bx-arrow-back'></i>
            </div>
        </div>
        
        <div class="center-section">
            <i class='bx bx-shape-polygon center-icon'></i>
        </div>
        
        <div class="right-section">
            <div class="icon-tags">
                <a href="#">
                    <i class='bx bx-bell'></i>
                    <span class="notification-badge">1</span>
                </a>
                <a href="#"><i class='bx bx-cog'></i></a>
            </div>
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Marjoel Lacdan Ng</span>
                <span style="margin-left: 10px; color: #666;">Admin</span>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <div class="page-title">
                <i class='bx bx-edit'></i>
                <h2>Edit Product</h2>
            </div>
            
            <ul class="category-list">
                <li class="category-item active">On Display</li>
                <li class="category-item">For Him</li>
                <li class="category-item">For Her</li>
                <li class="category-item">Others</li>
                <li class="category-item">Pending</li>
                <li class="category-item">Trash</li>
            </ul>
        </div>

        <div class="content-area">
            <div class="products-grid">
                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image bottle-image"></div>
                    <div class="product-name">Ekayim Yessi</div>
                    <div class="product-quantity">Q : 105</div>
                    <button class="edit-btn">Edit</button>
                </div>

                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image bottle-image"></div>
                    <div class="product-name">Emertxe Iraglyb</div>
                    <div class="product-quantity">Q : 88</div>
                    <button class="edit-btn">Edit</button>
                </div>

                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image bottle-image"></div>
                    <div class="product-name">Kealb Olop</div>
                    <div class="product-quantity">Q : 65</div>
                    <button class="edit-btn">Edit</button>
                </div>

                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image bottle-image"></div>
                    <div class="product-name">Noillim Eno</div>
                    <div class="product-quantity">Q : 34</div>
                    <button class="edit-btn">Edit</button>
                </div>

                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image bottle-image"></div>
                    <div class="product-name">Lenach Ed Uelb</div>
                    <div class="product-quantity">Q : 87</div>
                    <button class="edit-btn">Edit</button>
                </div>

                <div class="product-card">
                    <i class='bx bx-show eye-icon'></i>
                    <div class="product-image lacoste-image"></div>
                    <div class="product-name">Lacoste Red</div>
                    <div class="product-quantity">Q : 152</div>
                    <button class="edit-btn">Edit</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>