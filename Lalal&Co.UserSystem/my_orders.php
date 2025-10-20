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
    <link rel="stylesheet" href="myOrders.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>My Orders - Lalal & Co.</title>
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
                <div class="empty-icon"><i class='bx bx-package' ></i></div>
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
                            
                            <?php 
                            // Clean check for tracking number
                            $has_tracking = !empty($order['tracking_number']) && 
                                trim($order['tracking_number']) !== '' && 
                                strtoupper($order['tracking_number']) !== 'NULL' &&
                                $order['tracking_number'] !== '0';
                            ?>

                            <?php if ($order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
                            <?php if ($has_tracking): ?>
                                <!-- ORDER HAS VALID TRACKING NUMBER -->
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <!-- SHOW DELIVERED STATUS FIRST -->
                                    <div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 8px; margin-top: 15px; margin-bottom: 15px;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                            <span style="font-size: 24px;"><i class='bx bx-check'></i></span>
                                            <div style="font-size: 16px; font-weight: bold; color: #155724;">ORDER DELIVERED</div>
                                        </div>
                                        <p style="margin: 0; color: #155724; font-size: 13px;">
                                            Your order has been successfully delivered! Thank you for shopping with us.
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- THEN SHOW TRACKING INFO -->
                                <div class="tracking-info">
                                    <div class="tracking-label">J&T Express Tracking Number</div>
                                    <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                    <a href="https://www.jtexpress.ph/trajectoryQuery?waybillNo=<?php echo urlencode($order['tracking_number']); ?>" 
                                    target="_blank" 
                                    class="jnt-track-btn">
                                        <svg class="jnt-logo" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        Track on J&T Express
                                    </a>
                                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                        <?php if ($order['status'] === 'delivered'): ?>
                                            You can still view the delivery history using the tracking number above
                                        <?php else: ?>
                                            Click the button above to track your package on J&T Express website
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- NO TRACKING NUMBER AVAILABLE -->
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <!-- DELIVERED WITHOUT TRACKING (COD orders or old orders) -->
                                    <div class="tracking-not-ready" style="background: #d1ecf1; border-left-color: #17a2b8; border: 2px solid #17a2b8; margin-top: 15px;">
                                        <div class="tracking-label"><i class='bx bx-check'></i> ORDER DELIVERED</div>
                                        <div style="color: #0c5460; font-size: 14px; margin-top: 8px;">
                                            <strong>Your order has been successfully delivered!</strong>
                                        </div>
                                        <p style="color: #0c5460; font-size: 12px; margin-top: 8px; margin-bottom: 0;">
                                            <?php if ($order['payment_method'] === 'cod'): ?>
                                                This was a Cash on Delivery order. Tracking number was not required for this delivery method.
                                            <?php else: ?>
                                                Tracking number was not assigned for this delivery. Your order was completed successfully. If you have any questions, please contact our support team.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <!-- SHIPPED BUT NO TRACKING YET -->
                                    <div class="tracking-pending-info" style="margin-top: 15px;">
                                        <div class="tracking-label">⏳ Tracking Number</div>
                                        <div style="color: #856404; font-size: 13px; margin-top: 5px;">
                                            <strong>Your tracking number is being processed</strong>
                                        </div>
                                        <p style="color: #856404; font-size: 12px; margin-top: 8px; margin-bottom: 0;">
                                            Our team is currently getting your waybill from J&T Express branch. 
                                            Your tracking number will be available within 24 hours.
                                        </p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- ORDER NOT SHIPPED YET (pending/confirmed/processing) -->
                            <div class="tracking-not-ready" style="margin-top: 15px;">
                                <div class="tracking-label">📍 Tracking Status</div>
                                <div style="color: #6c757d; font-size: 13px; margin-top: 5px; font-style: italic;">
                                    Tracking number will be available once your order is shipped via J&T Express
                                </div>
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
            
            // UPDATED: J&T Tracking display in modal
            const hasTracking = order.tracking_number && 
                    order.tracking_number.trim() !== '' && 
                    order.tracking_number.toUpperCase() !== 'NULL' &&
                    order.tracking_number !== '0';

            let trackingInfo = '';
            if (order.status === 'shipped' || order.status === 'delivered') {
                if (hasTracking) {
                    // HAS VALID TRACKING NUMBER
                    let deliveredBanner = '';
                    if (order.status === 'delivered') {
                        deliveredBanner = `
                            <div style="background: #d4edda; border: 2px solid #28a745; padding: 15px; border-radius: 8px; margin-top: 15px; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="font-size: 24px;">✅</span>
                                    <div style="font-size: 16px; font-weight: bold; color: #155724;">ORDER DELIVERED</div>
                                </div>
                                <p style="margin: 0; color: #155724; font-size: 13px;">
                                    Your order has been successfully delivered! Thank you for shopping with us.
                                </p>
                            </div>
                        `;
                    }
                    
                    const trackMessage = order.status === 'delivered' 
                        ? 'You can still view the delivery history using the tracking number above'
                        : 'Click to track your package delivery status on J&T Express website';
                    
                    trackingInfo = `
                        ${deliveredBanner}
                        <div style="background: #fff5f5; padding: 15px; border-radius: 8px; border: 2px solid #e74c3c; margin-top: 15px;">
                            <p style="margin: 0 0 8px 0;"><strong>📦 J&T Express Tracking Number:</strong></p>
                            <p style="margin: 0 0 12px 0; font-family: monospace; font-size: 16px; font-weight: bold; color: #e74c3c;">
                                ${order.tracking_number}
                            </p>
                            <a href="https://www.jtexpress.ph/trajectoryQuery?waybillNo=${encodeURIComponent(order.tracking_number)}" 
                            target="_blank" 
                            style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600;">
                                <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                Track on J&T Express
                            </a>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                ${trackMessage}
                            </p>
                        </div>
                    `;
                } else {
                    // NO TRACKING NUMBER
                    if (order.status === 'delivered') {
                        // DELIVERED WITHOUT TRACKING
                        const codMessage = order.payment_method === 'cod' 
                            ? 'This was a Cash on Delivery order. Tracking number was not required for this delivery method.'
                            : 'Tracking number was not assigned for this delivery. Your order was completed successfully. If you have any questions, please contact our support team.';
                        
                        trackingInfo = `
                            <div style="background: #d1ecf1; border: 2px solid #17a2b8; padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <p style="margin: 0 0 8px 0; color: #0c5460;"><strong>✅ ORDER DELIVERED</strong></p>
                                <p style="margin: 0 0 8px 0; color: #0c5460; font-weight: 600;">
                                    Your order has been successfully delivered!
                                </p>
                                <p style="margin: 0; color: #0c5460; font-size: 12px;">
                                    ${codMessage}
                                </p>
                            </div>
                        `;
                    } else {
                        // SHIPPED BUT NO TRACKING YET
                        trackingInfo = `
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 15px;">
                                <p style="margin: 0; color: #856404;"><strong>⏳ Tracking Number: Being Processed</strong></p>
                                <p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">
                                    Our team is currently getting your waybill from J&T Express branch. Your tracking number will be available within 24 hours.
                                </p>
                            </div>
                        `;
                    }
                }
            } else {
                // NOT SHIPPED YET
                trackingInfo = `
                    <p style="margin-top: 10px; color: #6c757d; font-style: italic; font-size: 13px;">
                        📍 J&T Express tracking number will be provided once your order is shipped
                    </p>
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
                        ${trackingInfo}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                        <h4>Delivery Address</h4>
                        <p><strong>Name:</strong> ${order.full_name}</p>
                        <p><strong>Phone:</strong> ${order.phone}</p>
                        <p><strong>Address:</strong> ${order.address}</p>
                        <p><strong>City:</strong> ${order.city} ${order.postal_code}</p>
                        <p style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 4px; border-left: 3px solid #e74c3c;">
                            <strong style="color: #e74c3c;">📦 Courier:</strong><br>
                            <span style="font-size: 14px; color: #333;">J&T Express</span>
                        </p>
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