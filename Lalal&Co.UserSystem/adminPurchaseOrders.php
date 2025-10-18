<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $response = ['success' => false, 'message' => ''];
        
        try {
            $conn->begin_transaction();
            
            if ($_POST['action'] === 'create_purchase') {
                $supplier_name = $_POST['supplier_name'];
                $purchase_date = $_POST['purchase_date'];
                $notes = $_POST['notes'] ?? '';
                $admin_email = $_SESSION['admin_email'] ?? 'admin';
                
                // Insert purchase order
                $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_name, purchase_date, notes, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $supplier_name, $purchase_date, $notes, $admin_email);
                $stmt->execute();
                $purchase_id = $conn->insert_id;
                
                // Insert purchase items
                $products = json_decode($_POST['products'], true);
                $total_cost = 0;
                
                foreach ($products as $product) {
                    $product_id = $product['product_id'];
                    $quantity = $product['quantity'];
                    $unit_cost = $product['unit_cost'];
                    $subtotal = $quantity * $unit_cost;
                    $total_cost += $subtotal;
                    
                    $stmt = $conn->prepare("INSERT INTO purchase_order_items (purchase_id, product_id, quantity, unit_cost, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiidd", $purchase_id, $product_id, $quantity, $unit_cost, $subtotal);
                    $stmt->execute();
                }
                
                // Update total cost
                $stmt = $conn->prepare("UPDATE purchase_orders SET total_cost = ? WHERE purchase_id = ?");
                $stmt->bind_param("di", $total_cost, $purchase_id);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success_message'] = "Purchase order created successfully!";
                header('Location: adminPurchaseOrders.php');
                exit();
                
            } elseif ($_POST['action'] === 'receive_purchase') {
                $purchase_id = $_POST['purchase_id'];
                
                // Get purchase items
                $items_query = "SELECT * FROM purchase_order_items WHERE purchase_id = ?";
                $stmt = $conn->prepare($items_query);
                $stmt->bind_param("i", $purchase_id);
                $stmt->execute();
                $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                // Add to warehouse inventory
                foreach ($items as $item) {
                    // Get current inventory
                    $stmt = $conn->prepare("SELECT inventory_quantity, display_quantity FROM product_tbl WHERE product_id = ?");
                    $stmt->bind_param("i", $item['product_id']);
                    $stmt->execute();
                    $product = $stmt->get_result()->fetch_assoc();
                    
                    $inventory_before = $product['inventory_quantity'];
                    $display_before = $product['display_quantity'];
                    
                    // Update inventory
                    $stmt = $conn->prepare("UPDATE product_tbl SET inventory_quantity = inventory_quantity + ? WHERE product_id = ?");
                    $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                    $stmt->execute();
                    
                    // Log transaction
                    $inventory_after = $inventory_before + $item['quantity'];
                    $notes = "Received from purchase order #" . $purchase_id;
                    $admin_email = $_SESSION['admin_email'] ?? 'admin';
                    
                    $stmt = $conn->prepare("INSERT INTO inventory_transactions (product_id, transaction_type, quantity, inventory_before, inventory_after, display_before, display_after, notes, admin_email, purchase_id) VALUES (?, 'restock', ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iiiiiissi", $item['product_id'], $item['quantity'], $inventory_before, $inventory_after, $display_before, $display_before, $notes, $admin_email, $purchase_id);
                    $stmt->execute();
                }
                
                // Update purchase status
                $stmt = $conn->prepare("UPDATE purchase_orders SET status = 'received' WHERE purchase_id = ?");
                $stmt->bind_param("i", $purchase_id);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success_message'] = "Purchase order received and inventory updated!";
                header('Location: adminPurchaseOrders.php');
                exit();
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
        }
    }
}

// Get all purchase orders
$purchases_query = "
    SELECT 
        po.*,
        COUNT(poi.item_id) as item_count
    FROM purchase_orders po
    LEFT JOIN purchase_order_items poi ON po.purchase_id = poi.purchase_id
    GROUP BY po.purchase_id
    ORDER BY po.purchase_date DESC, po.purchase_id DESC
";
$purchases = $conn->query($purchases_query)->fetch_all(MYSQLI_ASSOC);

// Get all products for dropdown
$products_query = "SELECT product_id, product_name, product_price FROM product_tbl ORDER BY product_name";
$products = $conn->query($products_query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <link rel="stylesheet" href="adminPurchaseOrders.css">
    <title>Purchase Orders - Admin Panel</title>
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
            <div class="welcome-text">PURCHASE ORDERS</div>
        </div>
        
        <div class="right-section">
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Admin</span>
            </div>
        </div>
    </div>

    <div class="purchase-container">
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

        <div class="action-bar">
            <h2><i class='bx bx-package'></i> Purchase Orders Management</h2>
            <button class="btn-create" onclick="openCreateModal()">
                <i class='bx bx-plus-circle'></i> Create Purchase Order
            </button>
        </div>

        <div class="purchases-table">
            <table>
                <thead>
                    <tr>
                        <th>PO #</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total Cost</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                <i class='bx bx-package' style="font-size: 48px; display: block; margin-bottom: 10px;"></i>
                                No purchase orders found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $purchase): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($purchase['purchase_id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo htmlspecialchars($purchase['supplier_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($purchase['purchase_date'])); ?></td>
                                <td><?php echo $purchase['item_count']; ?> items</td>
                                <td><strong>₱<?php echo number_format($purchase['total_cost'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $purchase['status']; ?>">
                                        <?php echo ucfirst($purchase['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($purchase['created_by']); ?></td>
                                <td>
                                    <div class="action-btns">
                                        <button class="btn-sm btn-view" onclick='viewPurchase(<?php echo $purchase['purchase_id']; ?>)'>
                                            <i class='bx bx-show'></i> View
                                        </button>
                                        <?php if ($purchase['status'] === 'pending'): ?>
                                            <button class="btn-sm btn-receive" onclick='receivePurchase(<?php echo $purchase['purchase_id']; ?>)'>
                                                <i class='bx bx-check'></i> Receive
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create Purchase Order Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class='bx bx-plus-circle'></i> Create Purchase Order</h2>
                <span class="close-modal" onclick="closeModal('createModal')">&times;</span>
            </div>
            <form method="POST" onsubmit="return submitPurchaseOrder(event)">
                <input type="hidden" name="action" value="create_purchase">
                <input type="hidden" name="products" id="productsData">
                
                <div class="form-group">
                    <label>Supplier Name *</label>
                    <input type="text" name="supplier_name" required>
                </div>

                <div class="form-group">
                    <label>Purchase Date *</label>
                    <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>

                <div class="products-section">
                    <h3>Products</h3>
                    <div id="productsList">
                        <div class="product-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Product</label>
                                <select class="product-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Quantity</label>
                                <input type="number" class="product-quantity" min="1" value="1" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Unit Cost (₱)</label>
                                <input type="number" class="product-cost" step="0.01" min="0" value="0" required>
                            </div>
                            <button type="button" class="btn-remove" onclick="removeProduct(this)" style="display: none;">
                                <i class='bx bx-trash'></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-product" onclick="addProductRow()">
                        <i class='bx bx-plus'></i> Add Another Product
                    </button>
                </div>

                <div class="total-section">
                    <div>Total Cost: <span class="total-amount" id="totalCost">₱0.00</span></div>
                </div>

                <div style="margin-top: 30px; text-align: right;">
                    <button type="submit" class="btn-create">
                        <i class='bx bx-check'></i> Create Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Purchase Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="viewTitle">Purchase Order Details</h2>
                <span class="close-modal" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div id="viewBody"></div>
        </div>
    </div>

    <script>
        const productsData = <?php echo json_encode($products); ?>;

        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
            updateTotal();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function addProductRow() {
            const productsList = document.getElementById('productsList');
            const newRow = document.createElement('div');
            newRow.className = 'product-row';
            newRow.innerHTML = `
                <div class="form-group" style="margin-bottom: 0;">
                    <select class="product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['product_id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="number" class="product-quantity" min="1" value="1" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="number" class="product-cost" step="0.01" min="0" value="0" required>
                </div>
                <button type="button" class="btn-remove" onclick="removeProduct(this)">
                    <i class='bx bx-trash'></i>
                </button>
            `;
            productsList.appendChild(newRow);
            
            // Add event listeners to new inputs
            newRow.querySelector('.product-quantity').addEventListener('input', updateTotal);
            newRow.querySelector('.product-cost').addEventListener('input', updateTotal);
        }

        function removeProduct(btn) {
            btn.closest('.product-row').remove();
            updateTotal();
        }

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.product-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.product-quantity').value) || 0;
                const cost = parseFloat(row.querySelector('.product-cost').value) || 0;
                total += qty * cost;
            });
            document.getElementById('totalCost').textContent = '₱' + total.toFixed(2);
        }

        function submitPurchaseOrder(event) {
            event.preventDefault();
            
            const products = [];
            let isValid = true;
            
            document.querySelectorAll('.product-row').forEach(row => {
                const select = row.querySelector('.product-select');
                const quantity = row.querySelector('.product-quantity');
                const cost = row.querySelector('.product-cost');
                
                if (select.value && quantity.value && cost.value) {
                    products.push({
                        product_id: parseInt(select.value),
                        quantity: parseInt(quantity.value),
                        unit_cost: parseFloat(cost.value)
                    });
                } else {
                    isValid = false;
                }
            });
            
            if (!isValid || products.length === 0) {
                alert('Please fill in all product details');
                return false;
            }
            
            document.getElementById('productsData').value = JSON.stringify(products);
            event.target.submit();
            return true;
        }

        async function viewPurchase(purchaseId) {
            try {
                const response = await fetch(`get_purchase_details.php?purchase_id=${purchaseId}`);
                const data = await response.json();
                
                if (data.success) {
                    const purchase = data.purchase;
                    const items = data.items;
                    
                    document.getElementById('viewTitle').textContent = `Purchase Order #${String(purchase.purchase_id).padStart(6, '0')}`;
                    
                    let itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
                    itemsHtml += '<thead><tr style="background: #f8f9fa;"><th style="padding: 10px; text-align: left;">Product</th><th style="padding: 10px;">Qty</th><th style="padding: 10px;">Unit Cost</th><th style="padding: 10px;">Subtotal</th></tr></thead><tbody>';
                    
                    items.forEach(item => {
                        itemsHtml += `
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding: 10px;">${item.product_name}</td>
                                <td style="padding: 10px; text-align: center;">${item.quantity}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.unit_cost).toFixed(2)}</td>
                                <td style="padding: 10px; text-align: center;">₱${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    itemsHtml += '</tbody></table>';
                    
                    document.getElementById('viewBody').innerHTML = `
                        <div style="margin-bottom: 20px;">
                            <p><strong>Supplier:</strong> ${purchase.supplier_name}</p>
                            <p><strong>Date:</strong> ${new Date(purchase.purchase_date).toLocaleDateString()}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${purchase.status}">${purchase.status.toUpperCase()}</span></p>
                            <p><strong>Created By:</strong> ${purchase.created_by}</p>
                            ${purchase.notes ? `<p><strong>Notes:</strong> ${purchase.notes}</p>` : ''}
                        </div>
                        
                        <h4>Items</h4>
                        ${itemsHtml}
                        
                        <div style="text-align: right; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <strong style="font-size: 20px;">Total: ₱${parseFloat(purchase.total_cost).toFixed(2)}</strong>
                        </div>
                    `;
                    
                    document.getElementById('viewModal').style.display = 'block';
                }
            } catch (error) {
                alert('Error loading purchase details');
            }
        }

        function receivePurchase(purchaseId) {
            if (confirm('Mark this purchase order as received? This will add items to warehouse inventory.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="receive_purchase">
                    <input type="hidden" name="purchase_id" value="${purchaseId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add event listeners for live total calculation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.product-quantity, .product-cost').forEach(input => {
                input.addEventListener('input', updateTotal);
            });
        });

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.animation = 'slideIn 0.3s reverse';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>