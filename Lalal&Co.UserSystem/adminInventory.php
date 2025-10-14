<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.php');
    exit();
}

// Handle inventory operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $conn->begin_transaction();
        
        if ($_POST['action'] === 'add_inventory') {
            // Add stock to warehouse inventory
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }
            
            // Get current quantities
            $stmt = $conn->prepare("SELECT inventory_quantity, display_quantity FROM product_tbl WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
            
            $inventory_before = $product['inventory_quantity'];
            $display_before = $product['display_quantity'];
            
            // Update inventory quantity
            $stmt = $conn->prepare("UPDATE product_tbl SET inventory_quantity = inventory_quantity + ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();
            $stmt->close();
            
            // Log transaction
            $stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes, admin_email) VALUES (?, 'restock', ?, ?, ?, ?, ?, ?, ?)");
            $inventory_after = $inventory_before + $quantity;
            $notes = "Added {$quantity} units to warehouse inventory";
            $admin_email = $_SESSION['admin_email'] ?? 'admin';
            $stmt->bind_param("iiiiiiss", $product_id, $quantity, $inventory_before, $inventory_after, $display_before, $display_before, $notes, $admin_email);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $response = ['success' => true, 'message' => "Successfully added {$quantity} units to inventory"];
            
        } elseif ($_POST['action'] === 'move_to_display') {
            // Move stock from inventory to display
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }
            
            // Get current quantities
            $stmt = $conn->prepare("SELECT inventory_quantity, display_quantity, product_name FROM product_tbl WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $stmt->close();
            
            if ($product['inventory_quantity'] < $quantity) {
                throw new Exception("Insufficient inventory stock. Only {$product['inventory_quantity']} units available");
            }
            
            $inventory_before = $product['inventory_quantity'];
            $display_before = $product['display_quantity'];
            
            // Move quantity from inventory to display
            $stmt = $conn->prepare("UPDATE product_tbl SET inventory_quantity = inventory_quantity - ?, display_quantity = display_quantity + ? WHERE product_id = ?");
            $stmt->bind_param("iii", $quantity, $quantity, $product_id);
            $stmt->execute();
            $stmt->close();
            
            // Log transaction
            $stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes, admin_email) VALUES (?, 'move_to_display', ?, ?, ?, ?, ?, ?, ?)");
            $inventory_after = $inventory_before - $quantity;
            $display_after = $display_before + $quantity;
            $notes = "Moved {$quantity} units from inventory to display";
            $admin_email = $_SESSION['admin_email'] ?? 'admin';
            $stmt->bind_param("iiiiiiss", $product_id, $quantity, $inventory_before, $inventory_after, $display_before, $display_after, $notes, $admin_email);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $response = ['success' => true, 'message' => "Successfully moved {$quantity} units to display"];
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    
    // Return JSON for AJAX requests
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    } else {
        $_SESSION[$response['success'] ? 'success_message' : 'error_message'] = $response['message'];
        header('Location: adminInventory.php');
        exit();
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="adminEditProducts.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminInventories.css">
    <title>Inventory Management - La Gal & Co.</title>
</head>
<body>
    <div class="header">
        <div class="left-section">
            <div class="lc-logo">
                <a href="adminIndex.php">
                    <img src="images/lcLogo.png" alt="LC Logo">
                </a>
            </div>
            <a href="adminIndex.php" style="color: #666; text-decoration: none;">
                <i class='bx bx-arrow-back'></i> Back to Dashboard
            </a>
        </div>
        
        <div class="center-section">
            <i class='bx bx-shape-polygon'></i>
        </div>
        
        <div class="right-section">
            <div class="icon-tags">
                <a href="#"><i class='bx bx-bell'></i><span class="notification-badge">1</span></a>
                <a href="#"><i class='bx bx-cog'></i></a>
            </div>
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Admin</span>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <div class="page-title">
                <i class='bx bx-package'></i>
                <h2>Inventory Management</h2>
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
                <h1><i class='bx bx-package'></i> Inventory Management</h1>
                <p>Manage warehouse and display stock levels</p>
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
                    <span class="stat-label">Warehouse Units</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php echo array_sum(array_column($products, 'display_quantity')); ?>
                    </span>
                    <span class="stat-label">Display Units</span>
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

            <div class="inventory-list">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <?php 
                        $displayQty = $product['display_quantity'];
                        $inventoryQty = $product['inventory_quantity'];
                        
                        if ($displayQty == 0) {
                            $stockClass = 'status-out';
                            $stockText = 'Out of Stock';
                        } elseif ($displayQty < 20) {
                            $stockClass = 'status-low';
                            $stockText = 'Low Stock';
                        } else {
                            $stockClass = 'status-good';
                            $stockText = 'In Stock';
                        }
                        ?>
                        <div class="inventory-card">
                            <div class="stock-status <?php echo $stockClass; ?>">
                                <?php echo $stockText; ?>
                            </div>
                            
                            <div class="product-header">
                                <div class="product-image">
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="others-image">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: white;" >
                                            <i class='bx bx-package'></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-price">₱<?php echo number_format($product['product_price'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="stock-sections">
                                <div class="stock-section">
                                    <div class="section-title">Warehouse Stock</div>
                                    <div class="stock-amount"><?php echo $inventoryQty; ?></div>
                                    <div class="stock-label">Units Available</div>
                                </div>
                                <div class="stock-section">
                                    <div class="section-title">Display Stock</div>
                                    <div class="stock-amount"><?php echo $displayQty; ?></div>
                                    <div class="stock-label">Units on Display</div>
                                </div>
                            </div>
                            
                            <div class="inventory-actions">
                                <button class="btn-inventory btn-add-stock" onclick='openAddStockModal(<?php echo json_encode($product); ?>)'>
                                    <i class='bx bx-plus'></i> Add to Warehouse
                                </button>
                                <button class="btn-inventory btn-move-stock" onclick='openMoveStockModal(<?php echo json_encode($product); ?>)'>
                                    <i class='bx bx-transfer'></i> Move to Display
                                </button>
                            </div>
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

    <!-- Add Stock Modal -->
    <div id="addStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-plus-circle'></i> Add Stock to Warehouse</h2>
                <span class="close-modal" onclick="closeModal('addStockModal')">&times;</span>
            </div>
            <form id="addStockForm" onsubmit="handleAddStock(event)">
                <input type="hidden" name="product_id" id="add_product_id">
                <input type="hidden" name="action" value="add_inventory">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="add_product_name" readonly>
                </div>

                <div class="form-group">
                    <label>Current Warehouse Stock</label>
                    <input type="text" id="add_current_inventory" readonly>
                </div>

                <div class="form-group">
                    <label>Quantity to Add</label>
                    <input type="number" name="quantity" id="add_quantity" min="1" required>
                </div>

                <div class="quick-add-section">
                    <h3>Quick Add</h3>
                    <div class="quick-add-buttons">
                        <button type="button" class="quick-btn" onclick="setAddQuantity(10)">+10</button>
                        <button type="button" class="quick-btn" onclick="setAddQuantity(25)">+25</button>
                        <button type="button" class="quick-btn" onclick="setAddQuantity(50)">+50</button>
                        <button type="button" class="quick-btn" onclick="setAddQuantity(100)">+100</button>
                        <button type="button" class="quick-btn" onclick="setAddQuantity(200)">+200</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Add to Warehouse</button>
            </form>
        </div>
    </div>

    <!-- Move Stock Modal -->
    <div id="moveStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-transfer'></i> Move Stock to Display</h2>
                <span class="close-modal" onclick="closeModal('moveStockModal')">&times;</span>
            </div>
            <form id="moveStockForm" onsubmit="handleMoveStock(event)">
                <input type="hidden" name="product_id" id="move_product_id">
                <input type="hidden" name="action" value="move_to_display">
                <input type="hidden" name="ajax" value="1">
                
                <div class="form-group">
                    <label>Product</label>
                    <input type="text" id="move_product_name" readonly>
                </div>

                <div class="form-group">
                    <label>Available Warehouse Stock</label>
                    <input type="text" id="move_current_inventory" readonly>
                </div>

                <div class="form-group">
                    <label>Current Display Stock</label>
                    <input type="text" id="move_current_display" readonly>
                </div>

                <div class="form-group">
                    <label>Quantity to Move</label>
                    <input type="number" name="quantity" id="move_quantity" min="1" required>
                </div>

                <div class="quick-add-section">
                    <h3>Quick Select</h3>
                    <div class="quick-add-buttons">
                        <button type="button" class="quick-btn" onclick="setMoveQuantity(5)">5</button>
                        <button type="button" class="quick-btn" onclick="setMoveQuantity(10)">10</button>
                        <button type="button" class="quick-btn" onclick="setMoveQuantity(20)">20</button>
                        <button type="button" class="quick-btn" onclick="setMoveQuantity(50)">50</button>
                        <button type="button" class="quick-btn" onclick="setMoveAllQuantity()">All</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Move to Display</button>
            </form>
        </div>
    </div>

    <script>
        let currentInventoryQty = 0;

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

        function openAddStockModal(product) {
            document.getElementById('add_product_id').value = product.product_id;
            document.getElementById('add_product_name').value = product.product_name;
            document.getElementById('add_current_inventory').value = product.inventory_quantity + ' units';
            document.getElementById('add_quantity').value = '';
            document.getElementById('addStockModal').style.display = 'block';
        }

        function openMoveStockModal(product) {
            currentInventoryQty = product.inventory_quantity;
            document.getElementById('move_product_id').value = product.product_id;
            document.getElementById('move_product_name').value = product.product_name;
            document.getElementById('move_current_inventory').value = product.inventory_quantity + ' units';
            document.getElementById('move_current_display').value = product.display_quantity + ' units';
            document.getElementById('move_quantity').value = '';
            document.getElementById('move_quantity').max = product.inventory_quantity;
            document.getElementById('moveStockModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function setAddQuantity(amount) {
            document.getElementById('add_quantity').value = amount;
        }

        function setMoveQuantity(amount) {
            document.getElementById('move_quantity').value = amount;
        }

        function setMoveAllQuantity() {
            document.getElementById('move_quantity').value = currentInventoryQty;
        }

        async function handleAddStock(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch('adminInventory.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function handleMoveStock(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch('adminInventory.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
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