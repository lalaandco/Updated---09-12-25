<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_product') {
        $product_id = $_POST['product_id'];
        $product_name = $_POST['product_name'];
        $product_description = $_POST['product_description'];
        $product_price = $_POST['product_price'];
        
        // FIXED: Update only product info, not quantities
        $stmt = $conn->prepare("UPDATE product_tbl SET product_name = ?, product_description = ?, product_price = ? WHERE product_id = ?");
        $stmt->bind_param("ssdi", $product_name, $product_description, $product_price, $product_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Product updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating product.";
        }
        $stmt->close();
    }
}

// Get all products with inventory details
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : 'all';

$query = "SELECT * FROM product_tbl WHERE 1=1";

if (!empty($search)) {
    $search_param = "%{$search}%";
    $query .= " AND product_name LIKE ?";
}

// Category filtering based on category field
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
    <title>Edit Products - Inventory Management</title>
</head>
<body>

    <div class="main-container">
        <div class="sidebar">
            <div class="page-title">
                <i class='bx bx-edit'></i>
                <h2>Edit Product</h2>
            </div>
            
            <ul class="category-list">
                <li class="category-item <?php echo $category === 'all' ? 'active' : ''; ?>" onclick="filterCategory('all')">All Products</li>
                <li class="category-item <?php echo $category === 'ForHim' ? 'active' : ''; ?>" onclick="filterCategory('ForHim')">For Him</li>
                <li class="category-item <?php echo $category === 'ForHer' ? 'active' : ''; ?>" onclick="filterCategory('ForHer')">For Her</li>
                <li class="category-item <?php echo $category === 'Others' ? 'active' : ''; ?>" onclick="filterCategory('Others')">Others</li>
            </ul>
        </div>

        <div class="content-area">
            <div class="search-bar">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search products by name..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="searchProducts(event)">
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $displayQty = $product['display_quantity'];
                        $inventoryQty = $product['inventory_quantity'];
                        $stockClass = '';
                        $stockBadge = '';
                        
                        // Use display quantity for stock status (what customers see)
                        if ($displayQty == 0) {
                            $stockClass = 'out-of-stock';
                            $stockBadge = '<span class="stock-badge badge-out">OUT OF STOCK</span>';
                        } elseif ($displayQty < 20) {
                            $stockClass = 'low-stock';
                            $stockBadge = '<span class="stock-badge badge-low">LOW STOCK</span>';
                        } else {
                            $stockBadge = '<span class="stock-badge badge-good">IN STOCK</span>';
                        }
                        ?>
                        <div class="product-card <?php echo $stockClass; ?>">
                            <?php echo $stockBadge; ?>
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
                            
                            <!-- ADDED: Quantity Information -->
                            <div class="quantity-info">
                                <div class="quantity-item">
                                    <span class="quantity-label">Warehouse</span>
                                    <span class="quantity-value warehouse-qty"><?php echo $inventoryQty; ?></span>
                                </div>
                                <div class="quantity-item">
                                    <span class="quantity-label">Display</span>
                                    <span class="quantity-value display-qty"><?php echo $displayQty; ?></span>
                                </div>
                            </div>
                            
                            <button class="edit-btn" onclick='openEditModal(<?php echo json_encode($product); ?>)'>Edit Product</button>
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

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Product Details</h2>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form id="editForm" method="POST" action="">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="product_name" id="edit_product_name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="product_description" id="edit_product_description" required></textarea>
                </div>

                <div class="form-group">
                    <label>Price (₱)</label>
                    <input type="number" name="product_price" id="edit_product_price" step="0.01" required>
                </div>

                <!-- REMOVED: Quantity editing from modal since quantities are managed in Inventory Management -->
                <div class="quantity-info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="text-align: center;">
                        <div style="margin-bottom: 10px;">
                            <span style="font-size: 14px; color: #666;">Warehouse Stock: </span>
                            <span style="font-size: 18px; font-weight: bold; color: #1976d2;" id="edit_warehouse_qty">0</span>
                        </div>
                        <div>
                            <span style="font-size: 14px; color: #666;">Display Stock: </span>
                            <span style="font-size: 18px; font-weight: bold; color: #7b1fa2;" id="edit_display_qty">0</span>
                        </div>
                    </div>
                </div>


                <button type="submit" class="btn-primary">Update Product Details</button>
            </form>
        </div>
    </div>

    <script>
        function filterCategory(category) {
            const currentSearch = document.getElementById('searchInput').value;
            window.location.href = `adminEditProducts.php?category=${category}&search=${encodeURIComponent(currentSearch)}`;
        }

        function searchProducts(event) {
            if (event.key === 'Enter') {
                const search = document.getElementById('searchInput').value;
                const urlParams = new URLSearchParams(window.location.search);
                const category = urlParams.get('category') || 'all';
                window.location.href = `adminEditProducts.php?category=${category}&search=${encodeURIComponent(search)}`;
            }
        }

        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.product_id;
            document.getElementById('edit_product_name').value = product.product_name;
            document.getElementById('edit_product_description').value = product.product_description;
            document.getElementById('edit_product_price').value = product.product_price;
            
            // Show current quantities (read-only)
            document.getElementById('edit_warehouse_qty').textContent = product.inventory_quantity;
            document.getElementById('edit_display_qty').textContent = product.display_quantity;
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Auto-hide alerts after 5 seconds
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