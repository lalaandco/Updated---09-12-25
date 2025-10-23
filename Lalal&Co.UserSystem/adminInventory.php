<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Get all products with inventory details
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';

$query = "SELECT * FROM product_tbl WHERE 1=1";

if (!empty($search)) {
    $search_param = "%{$search}%";
    $query .= " AND product_name LIKE ?";
}

if ($category === 'ForHim') {
    $query .= " AND category = 'ForHim'";
} elseif ($category === 'ForHer') {
    $query .= " AND category = 'ForHer'";
} elseif ($category === 'Others') {
    $query .= " AND category = 'Others'";
}

$query .= " ORDER BY product_id ASC";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("s", $search_param);
}
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

renderAdminHeader()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="adminEditProducts.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminInventory.css">
    <title>Inventory Management - Lalal & Co.</title>
</head>
<body>
    <div class="main-container">
        <div class="sidebar">
            <div class="page-title">
                <i class='bx bx-package'></i>
                <h2>Inventory</h2>
            </div>
            
            <ul class="category-list">
                <li class="category-item <?php echo $category === 'all' ? 'active' : ''; ?>" onclick="filterCategory('all')">All Products</li>
                <li class="category-item <?php echo $category === 'ForHim' ? 'active' : ''; ?>" onclick="filterCategory('ForHim')">For Him</li>
                <li class="category-item <?php echo $category === 'ForHer' ? 'active' : ''; ?>" onclick="filterCategory('ForHer')">For Her</li>
                <li class="category-item <?php echo $category === 'Others' ? 'active' : ''; ?>" onclick="filterCategory('Others')">Others</li>
            </ul>
        </div>

        <div class="content-area">
            <div class="inventory-header">
                <h1><i class='bx bx-package'></i> Inventory Overview</h1>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <span class="stat-number"><?php echo count($products); ?></span>
                    <span class="stat-label">Total Products</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php echo array_sum(array_column($products, 'inventory_quantity')); ?>
                    </span>
                    <span class="stat-label">Storage Stock</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php echo array_sum(array_column($products, 'display_quantity')); ?>
                    </span>
                    <span class="stat-label">Display Stock</span>
                </div>
                <div class="stat-card">
                    <a href="adminPurchaseOrders.php" style="text-decoration: none; color: inherit; display: block;">
                        <span class="stat-number" >
                            <i class='bx bx-receipt'></i>
                        </span>
                        <span class="stat-label" >Create Purchase Order</span>
                    </a>
                </div>
            </div>

            <div class="search-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchProducts(event)">
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Products Grid (matching EditProducts layout) -->
            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $displayQty = $product['display_quantity'];
                        $inventoryQty = $product['inventory_quantity'];
                        
                        if ($displayQty == 0) {
                            $stockClass = 'badge-out';
                            $stockText = 'OUT OF STOCK';
                        } elseif ($displayQty < 20) {
                            $stockClass = 'badge-low';
                            $stockText = 'LOW STOCK';
                        } else {
                            $stockClass = 'badge-good';
                            $stockText = 'IN STOCK';
                        }
                        ?>
                        <div class="product-card">
                            <span class="stock-badge <?php echo $stockClass; ?>">
                                <?php echo $stockText; ?>
                            </span>
                            
                            <div class="product-image">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-img">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px;">
                                        <i class='bx bx-package'></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                            <div class="product-price">₱<?php echo number_format($product['product_price'], 2); ?></div>
                            
                            <div class="quantity-info">
                                <div class="quantity-item">
                                    <span class="quantity-label">Stock-in</span>
                                    <span class="quantity-value warehouse-qty"><?php echo $inventoryQty; ?></span>
                                </div>
                                <div class="quantity-item">
                                    <span class="quantity-label">Display</span>
                                    <span class="quantity-value display-qty"><?php echo $displayQty; ?></span>
                                </div>
                            </div>
                            
                            <!-- CHANGED: Redirect to Purchase Orders instead of modal -->
                            <button class="edit-btn" onclick="createPurchaseOrder(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>')">
                                <i class='bx bx-receipt'></i> Order from Supplier
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-search-alt'></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function filterCategory(category) {
            const currentSearch = document.getElementById('searchInput').value;
            window.location.href = `adminInventory.php?category=${category}&search=${encodeURIComponent(currentSearch)}`;
        }

        function searchProducts(event) {
            if (event.key === 'Enter') {
                const search = document.getElementById('searchInput').value;
                const urlParams = new URLSearchParams(window.location.search);
                const category = urlParams.get('category') || 'all';
                window.location.href = `adminInventory.php?category=${category}&search=${encodeURIComponent(search)}`;
            }
        }

        // Redirect to Purchase Orders page with pre-selected product
        function createPurchaseOrder(productId, productName) {
            // Store product info in sessionStorage to pre-populate in purchase order form
            sessionStorage.setItem('preselected_product_id', productId);
            sessionStorage.setItem('preselected_product_name', productName);
            
            // Redirect to purchase orders page
            window.location.href = 'adminPurchaseOrders.php?action=create&product_id=' + productId;
        }

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideIn 0.3s reverse';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>