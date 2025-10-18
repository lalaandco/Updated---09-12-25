<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

$user_email = $_SESSION['email'];

require_once 'config.php';

// Fetch user's orders with payment verification details
$stmt = $conn->prepare("
    SELECT o.*, 
           pv.verification_id,
           pv.verification_status,
           pv.gcash_transaction_id,
           pv.submitted_at as payment_submitted_at,
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as purchased_items,
           COUNT(oi.item_id) as item_count
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id 
    LEFT JOIN payment_verifications pv ON o.order_id = pv.order_id
    WHERE o.user_email = ? 
    GROUP BY o.order_id 
    ORDER BY o.order_date DESC
");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch order items with ratings
$order_items = [];
$product_ratings = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'order_id');
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    $items_stmt = $conn->prepare("
        SELECT oi.order_id, oi.product_id, oi.product_name, oi.quantity, oi.product_price, oi.subtotal,
               pr.rating_id, pr.rating, pr.review_title, pr.review_text, pr.created_at as rated_at
        FROM order_items oi
        LEFT JOIN product_ratings pr ON oi.order_id = pr.order_id AND oi.product_id = pr.product_id
        WHERE oi.order_id IN ($placeholders) 
        ORDER BY oi.order_id, oi.item_id
    ");
    $items_stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $order_items[$item['order_id']][] = $item;
        if ($item['rating_id']) {
            $product_ratings[$item['order_id']][$item['product_id']] = [
                'rating' => $item['rating'],
                'review_title' => $item['review_title'],
                'review_text' => $item['review_text'],
                'rated_at' => $item['rated_at']
            ];
        }
    }
    $items_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="my_orders.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>My Orders - Lalal & Co.</title>
    <style>
        .payment-status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }
        
        .payment-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .order-status-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .status-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            border-left: 3px solid #0066cc;
        }
        
        .status-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .status-value {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .transaction-id {
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 8px;
        }
        
        /* Rating Styles */
        .rating-section {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
        }
        
        .rating-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .rating-item:last-child {
            border-bottom: none;
        }
        
        .product-rating-info {
            flex: 1;
        }
        
        .product-rating-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stars {
            display: inline-flex;
            gap: 2px;
        }
        
        .star {
            color: #ddd;
            font-size: 18px;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .star.filled {
            color: #ffc107;
        }
        
        .star:hover,
        .star.hover {
            color: #ffc107;
        }
        
        .rate-btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .rate-btn:hover {
            transform: translateY(-2px);
        }
        
        .rated-badge {
            background: #28a745;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .rating-date {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        
        /* Rating Modal */
        .rating-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .rating-modal.active {
            display: flex;
        }
        
        .rating-modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .rating-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        
        .rating-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close-rating-modal {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        
        .rating-form-group {
            margin-bottom: 20px;
        }
        
        .rating-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .star-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .star-selector .star {
            font-size: 32px;
        }
        
        .rating-form-group input[type="text"],
        .rating-form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .rating-form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .submit-rating-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .submit-rating-btn:hover {
            transform: translateY(-2px);
        }
        
        .alert-message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .rating-info-text {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-header">
        <a href="index.php" class="back-btn">
            <i>←</i> Back to Home
        </a>
        <h1>My Orders</h1>
        <p>Track your purchases and order history</p>
    </div>
    
    <div class="container">
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h2 class="empty-title">No orders yet</h2>
                <p class="empty-subtitle">You haven't placed any orders. Start shopping to see your order history here.</p>
                <a href="index.php" class="shop-now-btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-header-top">
                                <div>
                                    <div class="order-id">Order #<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date"><?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></div>
                                </div>
                                <div>
                                    <div class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Status Section -->
                            <div class="order-status-section" style="margin-top: 12px;">
                                <div class="status-item">
                                    <div class="status-label">Order Status</div>
                                    <div class="status-value"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></div>
                                </div>
                                <div class="status-item">
                                    <div class="status-label">Payment Status</div>
                                    <div class="status-value">
                                        <?php 
                                        $payment_status = $order['payment_status'];
                                        $status_text = ucfirst(str_replace('_', ' ', $payment_status));
                                        echo $status_text;
                                        ?>
                                    </div>
                                    <span class="payment-status-badge payment-<?php 
                                        echo $payment_status === 'verified' ? 'verified' : 
                                            ($payment_status === 'pending_verification' ? 'pending' : 'failed'); 
                                    ?>">
                                        <?php 
                                        echo $payment_status === 'verified' ? '✓ Verified' : 
                                            ($payment_status === 'pending_verification' ? '⏳ Awaiting Verification' : 
                                            ($payment_status === 'failed' ? '✗ Failed' : 'Cancelled'));
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Payment Transaction ID -->
                            <?php if (!empty($order['gcash_transaction_id'])): ?>
                                <div style="margin-top: 12px;">
                                    <div class="status-label">GCash Transaction ID</div>
                                    <div class="transaction-id">
                                        <?php echo htmlspecialchars($order['gcash_transaction_id']); ?>
                                    </div>
                                    <?php if (!empty($order['payment_submitted_at'])): ?>
                                        <div class="status-label" style="margin-top: 8px;">Submitted on</div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo date('M d, Y - h:i A', strtotime($order['payment_submitted_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php if ($order['payment_status'] === 'pending_verification'): ?>
                                    <div style="margin-top: 12px; padding: 12px; background: #fffbea; border-radius: 6px; border-left: 3px solid #ff9800;">
                                        <div class="status-label">⏳ Payment Awaiting Submission</div>
                                        <div style="font-size: 13px; color: #856404; margin-top: 4px;">
                                            Please complete your payment and submit the proof within 24 hours.
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-body">
                            <!-- Purchased Items Section -->
                            <div class="purchased-items">
                                <div class="items-label">
                                    Purchased Items 
                                    <span class="item-count-badge"><?php echo $order['item_count']; ?> item(s)</span>
                                </div>
                                <div class="items-list">
                                    <?php if (isset($order_items[$order['order_id']])): ?>
                                        <div class="quick-items-preview">
                                            <?php foreach ($order_items[$order['order_id']] as $item): ?>
                                                <div class="item-preview" title="₱<?php echo number_format($item['product_price'], 2); ?> each">
                                                    <?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #999; font-style: italic;">
                                            No items found for this order.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Rating Section - Only show if order is delivered -->
                            <?php if ($order['status'] === 'delivered' && isset($order_items[$order['order_id']])): ?>
                                <div class="rating-section">
                                    <h4 style="margin: 0 0 15px 0; color: #333;">
                                        <i class='bx bx-star'></i> Rate Your Purchase
                                    </h4>
                                    <?php foreach ($order_items[$order['order_id']] as $item): ?>
                                        <?php 
                                        $has_rating = isset($product_ratings[$order['order_id']][$item['product_id']]);
                                        $rating_data = $has_rating ? $product_ratings[$order['order_id']][$item['product_id']] : null;
                                        ?>
                                        <div class="rating-item">
                                            <div class="product-rating-info">
                                                <div class="product-rating-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <?php if ($has_rating): ?>
                                                    <div class="rating-display">
                                                        <div class="stars">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <span class="star <?php echo $i <= $rating_data['rating'] ? 'filled' : ''; ?>">★</span>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span class="rated-badge">Rated</span>
                                                    </div>
                                                    <?php if ($rating_data['review_title']): ?>
                                                        <div style="margin-top: 8px; font-size: 13px;">
                                                            <strong><?php echo htmlspecialchars($rating_data['review_title']); ?></strong>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($rating_data['review_text']): ?>
                                                        <div style="margin-top: 4px; font-size: 12px; color: #666;">
                                                            <?php echo htmlspecialchars($rating_data['review_text']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="rating-date">
                                                        Rated on <?php echo date('M d, Y', strtotime($rating_data['rated_at'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="rating-info-text">
                                                        Share your experience with this product
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="rate-btn" onclick="openRatingModal(<?php echo $order['order_id']; ?>, <?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars(addslashes($item['product_name'])); ?>', <?php echo $has_rating ? $rating_data['rating'] : 0; ?>, '<?php echo $has_rating ? htmlspecialchars(addslashes($rating_data['review_title'] ?? '')) : ''; ?>', '<?php echo $has_rating ? htmlspecialchars(addslashes($rating_data['review_text'] ?? '')) : ''; ?>')">
                                                <?php echo $has_rating ? 'Edit Rating' : 'Rate Product'; ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="order-info">
                                <div class="info-group">
                                    <div class="info-label">Delivery Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['address'] . ', ' . $order['city']); ?></div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Payment Method</div>
                                    <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                                </div>
                                
                                <div class="info-group">
                                    <div class="info-label">Phone Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                            </div>
                            
                            <?php if ($order['tracking_number']): ?>
                                <div class="tracking-info">
                                    <div class="tracking-label">Tracking Number:</div>
                                    <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="order-total">
                                Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                            
                            <button class="view-details-btn" onclick="viewOrderDetails(<?php echo $order['order_id']; ?>)">
                                View Full Order Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; margin: 5% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                <h3 id="modalTitle">Order Details</h3>
                <span onclick="closeModal()" style="font-size: 24px; cursor: pointer; color: #999;">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Order details will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Rating Modal -->
    <div id="ratingModal" class="rating-modal">
        <div class="rating-modal-content">
            <div class="rating-modal-header">
                <h3>Rate Product</h3>
                <span class="close-rating-modal" onclick="closeRatingModal()">&times;</span>
            </div>
            <div id="ratingAlertSuccess" class="alert-message alert-success"></div>
            <div id="ratingAlertError" class="alert-message alert-error"></div>
            <form id="ratingForm" onsubmit="submitRating(event)">
                <input type="hidden" id="rating_order_id" name="order_id">
                <input type="hidden" id="rating_product_id" name="product_id">
                <input type="hidden" id="rating_value" name="rating" value="0">
                
                <div class="rating-form-group">
                    <label>Product</label>
                    <div id="rating_product_name" style="font-weight: 600; color: #333; padding: 12px; background: #f8f9fa; border-radius: 6px;"></div>
                </div>
                
                <div class="rating-form-group">
                    <label>Your Rating *</label>
                    <div class="star-selector">
                        <span class="star" data-rating="1" onclick="setRating(1)">★</span>
                        <span class="star" data-rating="2" onclick="setRating(2)">★</span>
                        <span class="star" data-rating="3" onclick="setRating(3)">★</span>
                        <span class="star" data-rating="4" onclick="setRating(4)">★</span>
                        <span class="star" data-rating="5" onclick="setRating(5)">★</span>
                    </div>
                    <div id="rating_text" style="font-size: 13px; color: #666;"></div>
                </div>
                
                <div class="rating-form-group">
                    <label>Review Title (Optional)</label>
                    <input type="text" id="review_title" name="review_title" placeholder="Summarize your experience" maxlength="255">
                </div>
                
                <div class="rating-form-group">
                    <label>Your Review (Optional)</label>
                    <textarea id="review_text" name="review_text" placeholder="Tell others about your experience with this product..." maxlength="1000"></textarea>
                </div>
                
                <button type="submit" class="submit-rating-btn">
                    <i class='bx bx-check-circle'></i> Submit Rating
                </button>
            </form>
        </div>
    </div>
    
    <script>
        const orders = <?php echo json_encode($orders); ?>;
        const orderItems = <?php echo json_encode($order_items); ?>;
        
        // Rating Modal Functions
        function openRatingModal(orderId, productId, productName, currentRating, reviewTitle, reviewText) {
            document.getElementById('rating_order_id').value = orderId;
            document.getElementById('rating_product_id').value = productId;
            document.getElementById('rating_product_name').textContent = productName;
            document.getElementById('review_title').value = reviewTitle || '';
            document.getElementById('review_text').value = reviewText || '';
            
            // Set current rating
            if (currentRating > 0) {
                setRating(currentRating);
            } else {
                setRating(0);
            }
            
            // Clear alerts
            document.getElementById('ratingAlertSuccess').style.display = 'none';
            document.getElementById('ratingAlertError').style.display = 'none';
            
            document.getElementById('ratingModal').classList.add('active');
        }
        
        function closeRatingModal() {
            document.getElementById('ratingModal').classList.remove('active');
        }
        
        function setRating(rating) {
            document.getElementById('rating_value').value = rating;
            
            const stars = document.querySelectorAll('.star-selector .star');
            const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
            
            stars.forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('filled');
                } else {
                    star.classList.remove('filled');
                }
            });
            
            document.getElementById('rating_text').textContent = rating > 0 ? ratingTexts[rating] : 'Please select a rating';
        }
        
        // Star hover effect
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-selector .star');
            stars.forEach(star => {
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.dataset.rating);
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.add('hover');
                        } else {
                            s.classList.remove('hover');
                        }
                    });
                });
                
                star.addEventListener('mouseleave', function() {
                    stars.forEach(s => s.classList.remove('hover'));
                });
            });
        });
        
        async function submitRating(event) {
            event.preventDefault();
            
            const rating = parseInt(document.getElementById('rating_value').value);
            if (rating === 0) {
                showRatingAlert('error', 'Please select a rating');
                return;
            }
            
            const formData = new FormData(event.target);
            
            try {
                const response = await fetch('submit_rating.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showRatingAlert('success', data.message);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showRatingAlert('error', data.message);
                }
            } catch (error) {
                showRatingAlert('error', 'Error submitting rating. Please try again.');
            }
        }
        
        function showRatingAlert(type, message) {
            const successAlert = document.getElementById('ratingAlertSuccess');
            const errorAlert = document.getElementById('ratingAlertError');
            
            if (type === 'success') {
                successAlert.textContent = message;
                successAlert.style.display = 'block';
                errorAlert.style.display = 'none';
            } else {
                errorAlert.textContent = message;
                errorAlert.style.display = 'block';
                successAlert.style.display = 'none';
            }
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const orderModal = document.getElementById('orderModal');
            const ratingModal = document.getElementById('ratingModal');
            
            if (event.target === orderModal) {
                orderModal.style.display = 'none';
            }
            if (event.target === ratingModal) {
                ratingModal.classList.remove('active');
            }
        }
        
        function viewOrderDetails(orderId) {
            const order = orders.find(o => o.order_id == orderId);
            if (!order) return;
            
            document.getElementById('modalTitle').textContent = `Order #${String(order.order_id).padStart(8, '0')} Details`;
            
            const items = orderItems[orderId] || [];
            let itemsHtml = '<h4>Order Items</h4>';
            
            if (items.length > 0) {
                items.forEach(item => {
                    itemsHtml += `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #eee; border-radius: 6px; margin-bottom: 10px; background: #fafafa;">
                            <div style="flex: 1;">
                                <strong style="font-size: 1rem;">${item.product_name}</strong><br>
                                <small style="color: #666;">Quantity: ${item.quantity} × ₱${parseFloat(item.product_price).toFixed(2)}</small>
                            </div>
                            <div style="font-weight: bold; font-size: 1.1rem; color: #28a745;">₱${parseFloat(item.subtotal).toFixed(2)}</div>
                        </div>
                    `;
                });
                
                const itemsTotal = items.reduce((sum, item) => sum + parseFloat(item.subtotal), 0);
                itemsHtml += `
                    <div style="text-align: right; margin-top: 15px; padding-top: 15px; border-top: 2px solid #eee;">
                        <strong style="font-size: 1.1rem;">Items Total: ₱${itemsTotal.toFixed(2)}</strong>
                    </div>
                `;
            } else {
                itemsHtml += '<p style="color: #999; font-style: italic;">No items found for this order.</p>';
            }
            
            let paymentStatusBadge = '';
            if (order.payment_status === 'verified') {
                paymentStatusBadge = '<span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">✓ Verified</span>';
            } else if (order.payment_status === 'pending_verification') {
                paymentStatusBadge = '<span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">⏳ Awaiting Verification</span>';
            } else {
                paymentStatusBadge = '<span style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">✗ Failed</span>';
            }
            
            let transactionInfo = '';
            if (order.gcash_transaction_id) {
                transactionInfo = `
                    <p><strong>GCash Transaction:</strong> <code style="background: #f5f5f5; padding: 4px 8px; border-radius: 3px; font-family: monospace;">${order.gcash_transaction_id}</code></p>
                    ${order.payment_submitted_at ? `<p><strong>Submitted:</strong> ${new Date(order.payment_submitted_at).toLocaleDateString()}</p>` : ''}
                `;
            }
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Order Information</h4>
                        <p><strong>Date:</strong> ${new Date(order.order_date).toLocaleDateString()}</p>
                        <p><strong>Order Status:</strong> <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 3px; font-size: 0.9rem;">${order.status.toUpperCase()}</span></p>
                        <p><strong>Payment Status:</strong> ${paymentStatusBadge}</p>
                        <p><strong>Payment Method:</strong> ${order.payment_method.toUpperCase()}</p>
                        <p><strong>Total:</strong> ₱${parseFloat(order.total_amount).toFixed(2)}</p>
                        ${order.tracking_number ? `<p><strong>Tracking:</strong> ${order.tracking_number}</p>` : ''}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Delivery Address</h4>
                        <p><strong>Name:</strong> ${order.full_name}</p>
                        <p><strong>Phone:</strong> ${order.phone}</p>
                        <p><strong>Address:</strong> ${order.address}</p>
                        <p><strong>City:</strong> ${order.city} ${order.postal_code}</p>
                    </div>
                </div>
                
                ${transactionInfo ? `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4>Payment Verification</h4>
                    ${transactionInfo}
                </div>
                ` : ''}
                
                ${order.order_notes ? `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                    <h4>Order Notes</h4>
                    <p>${order.order_notes}</p>
                </div>
                ` : ''}
                
                <div>
                    ${itemsHtml}
                </div>
            `;
            
            document.getElementById('orderModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
    </script>
</body>
</html>