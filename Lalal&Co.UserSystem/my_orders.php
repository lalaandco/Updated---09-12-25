<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

$user_email = $_SESSION['email'];

require_once 'config.php';

// Fetch user's orders with payment verification, cancellation, and refund details
$stmt = $conn->prepare("
    SELECT o.*, 
           pv.verification_id,
           pv.verification_status,
           pv.gcash_transaction_id,
           pv.submitted_at as payment_submitted_at,
           oc.cancellation_id,
           oc.reason as cancel_reason,
           oc.status as cancel_status,
           rf.refund_id,
           rf.reason_description as refund_reason,
           rf.status as refund_status,
           GROUP_CONCAT(CONCAT(oi.product_name, ' (x', oi.quantity, ')') SEPARATOR ', ') as purchased_items,
           COUNT(oi.item_id) as item_count
    FROM orders o 
    LEFT JOIN order_items oi ON o.order_id = oi.order_id 
    LEFT JOIN payment_verifications pv ON o.order_id = pv.order_id
    LEFT JOIN order_cancellations oc ON o.order_id = oc.order_id
    LEFT JOIN order_refunds rf ON o.order_id = rf.order_id
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
    <link rel="stylesheet" href="myOrderStyle.css">
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
                <?php foreach ($orders as $order): 
                    $hasTracking = !empty($order['tracking_number']) && 
                                  strtoupper($order['tracking_number']) !== 'NULL' &&
                                  $order['tracking_number'] !== '0';
                ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-header-top">
                                <div>
                                    <div class="order-id">Order #<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></div>
                                    <div class="order-date"><?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></div>
                                </div>
                                <div>
                                    <div class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
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
                            
                            <!-- GCash Transaction ID Section -->
                            <?php if (!empty($order['gcash_transaction_id'])): ?>
                                <div style="margin-top: 12px; background: white; padding: 12px 14px; border-radius: 8px; border: 1px solid #e8e8e8;">
                                    <div class="status-label">GCash Transaction ID</div>
                                    <div style="font-family: monospace; font-size: 15px; font-weight: 700; color: #2d3748; margin-top: 4px;">
                                        <?php echo htmlspecialchars($order['gcash_transaction_id']); ?>
                                    </div>
                                    <?php if (!empty($order['payment_submitted_at'])): ?>
                                        <div class="status-label" style="margin-top: 8px;">Submitted on</div>
                                        <div style="font-size: 12px; color: #718096;">
                                            <?php echo date('M d, Y - h:i A', strtotime($order['payment_submitted_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- J&T Tracking for Shipped/Delivered Orders -->
                            <?php if (($order['status'] === 'shipped' || $order['status'] === 'delivered') && $hasTracking): ?>
                                <div class="tracking-info">
                                    <div class="tracking-label">J&T Express Tracking Number:</div>
                                    <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                    
                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <div style="background: #d1ecf1; border: 2px solid #17a2b8; padding: 14px 16px; border-radius: 10px; margin-top: 12px;">
                                            <div style="font-weight: 700; color: #0c5460; margin-bottom: 4px;">
                                                <i class='bx bx-check-circle'></i> ORDER DELIVERED
                                            </div>
                                            <div style="font-size: 13px; color: #0c5460;">
                                                Your order has been successfully delivered!
                                            </div>
                                            <?php if (!$order['delivery_confirmed']): ?>
                                                <button class="btn-confirm-delivery" onclick="confirmDelivery(<?php echo $order['order_id']; ?>)">
                                                    <i class='bx bx-check-double'></i> Confirm Delivery
                                                </button>
                                            <?php else: ?>
                                                <div style="margin-top: 8px; font-size: 12px; color: #0c5460;">
                                                    ✓ Delivery confirmed by you on <?php echo date('M d, Y h:i A', strtotime($order['delivery_confirmed_date'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <a href="https://www.jtexpress.ph/trajectoryQuery?waybillNo=<?php echo urlencode($order['tracking_number']); ?>" 
                                           target="_blank" 
                                           class="jnt-track-btn">
                                            <svg style="width: 16px; height: 16px;" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                            </svg>
                                            Track on J&T Express
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (($order['status'] === 'shipped' || $order['status'] === 'delivered') && !$hasTracking): ?>
                                <!-- No tracking available -->
                                <?php if ($order['status'] === 'delivered'): ?>
                                    <div style="background: #d1ecf1; border: 2px solid #17a2b8; padding: 14px 16px; border-radius: 10px; margin-top: 12px;">
                                        <div style="font-weight: 700; color: #0c5460; margin-bottom: 4px;">
                                            <i class='bx bx-check-circle'></i> ORDER DELIVERED
                                        </div>
                                        <div style="font-size: 13px; color: #0c5460;">
                                            Your order has been successfully delivered!
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="background: #fff3cd; padding: 14px 16px; border-radius: 10px; border-left: 4px solid #ffc107; margin-top: 12px;">
                                        <div style="font-weight: 700; color: #856404; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            📍 TRACKING STATUS
                                        </div>
                                        <div style="font-size: 13px; color: #856404; margin-top: 4px;">
                                            Tracking number will be available within 24 hours
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Order not shipped yet -->
                                <div style="background: #f8f9fa; padding: 14px 16px; border-radius: 10px; border-left: 4px solid #6c757d; margin-top: 12px;">
                                    <div style="font-weight: 700; color: #6c757d; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        📍 TRACKING STATUS
                                    </div>
                                    <div style="font-size: 13px; color: #6c757d; margin-top: 4px; font-style: italic;">
                                        Tracking number will be available once your order is shipped via J&T Express
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Cancellation/Refund Status -->
                            <?php if ($order['cancel_status'] === 'pending'): ?>
                                <div class="status-badge-secondary badge-cancel-pending">
                                    ⏳ Cancellation Pending
                                </div>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <div class="status-badge-secondary badge-cancelled">
                                    ✗ Order Cancelled
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['refund_status'] === 'pending'): ?>
                                <div class="status-badge-secondary badge-refund-pending">
                                    ⏳ Refund Request Pending
                                </div>
                            <?php elseif ($order['status'] === 'refunded'): ?>
                                <div class="status-badge-secondary badge-refunded">
                                    ✓ Order Refunded
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-body">
                            <!-- Purchased Items Section with Badge -->
                            <div class="purchased-items-section">
                                <div class="items-header">
                                    <span class="items-label">Purchased Items</span>
                                    <span class="item-count-badge"><?php echo $order['item_count']; ?> item(s)</span>
                                </div>
                                <div class="items-preview">
                                    <?php 
                                    $items = $order_items[$order['order_id']] ?? [];
                                    if (!empty($items)): 
                                        foreach ($items as $item): 
                                    ?>
                                        <div class="item-pill"><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</div>
                                    <?php 
                                        endforeach;
                                    else: 
                                    ?>
                                        <div style="color: #999; font-style: italic; font-size: 14px;">No items found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Order Details Grid (like second image) -->
                            <div class="order-details-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Delivery Address</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($order['address'] . ', ' . $order['city'] . ' ' . $order['postal_code']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Payment Method</div>
                                    <div class="detail-value"><?php echo ucfirst($order['payment_method']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Phone Number</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($order['phone']); ?></div>
                                </div>
                            </div>
                            
                            <div class="order-total">
                                <span class="total-label">Total Amount:</span>
                                <span class="total-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="order-footer">
                            <div class="order-footer-main-actions">
                                <button class="btn-view" onclick="viewOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                    <i class='bx bx-show'></i> View Details
                                </button>
                                
                                <!-- Cancel/Refund Action Buttons -->
                                <div class="action-buttons">
                                    <?php 
                                    $canCancel = $order['verification_status'] !== 'verified' 
                                                && $order['status'] !== 'delivered' 
                                                && $order['status'] !== 'cancelled'
                                                && $order['status'] !== 'refunded'
                                                && $order['cancel_status'] !== 'pending';
                                    
                                    $canRefund = $order['status'] === 'delivered' 
                                                && $order['refund_status'] !== 'pending'
                                                && $order['status'] !== 'refunded';
                                    
                                    if ($canCancel): ?>
                                        <button class="btn-cancel" onclick="openCancelModal(<?php echo $order['order_id']; ?>)">
                                            <i class='bx bx-x-circle'></i> Cancel Order
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($canRefund): ?>
                                        <button class="btn-refund" onclick="openRefundModal(<?php echo $order['order_id']; ?>)">
                                            <i class='bx bx-undo'></i> Request Refund
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($order['status'] === 'delivered' && !empty($items)): ?>
                                <div class="order-footer-rating-actions">
                                    <?php foreach ($items as $item): ?>
                                        <?php if (!isset($product_ratings[$order['order_id']][$item['product_id']])): ?>
                                            <button class="btn-rate" onclick="openRatingModal(<?php echo $order['order_id']; ?>, <?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>')">
                                                <i class='bx bx-star'></i> Rate <?php echo htmlspecialchars($item['product_name']); ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Rating Modal -->
    <div id="ratingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Rate Product</h2>
                <span class="close" onclick="closeRatingModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="ratingForm" method="POST" action="submit_rating.php">
                    <input type="hidden" name="order_id" id="rating_order_id">
                    <input type="hidden" name="product_id" id="rating_product_id">
                    <input type="hidden" name="rating" id="rating_value" value="0">
                    
                    <div class="product-rating-name" id="rating_product_name"></div>
                    
                    <div class="star-rating">
                        <i class='bx bx-star star' data-rating="1"></i>
                        <i class='bx bx-star star' data-rating="2"></i>
                        <i class='bx bx-star star' data-rating="3"></i>
                        <i class='bx bx-star star' data-rating="4"></i>
                        <i class='bx bx-star star' data-rating="5"></i>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Review</label>
                        <textarea name="review_text" class="form-input" rows="4" placeholder="Tell us what you think about this product"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit-rating">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div id="cancelModal" class="modal-overlay">
        <div class="cancel-refund-modal">
            <div class="modal-header-custom">
                <h3><i class='bx bx-x-circle'></i> Cancel Order</h3>
                <button class="close-btn" onclick="closeCancelModal()">&times;</button>
            </div>
            <div class="modal-body-custom">
                <div class="contact-info">
                    <h4>📞 To confirm your cancellation and receive your payment:</h4>
                    <p><strong>Contact No.:</strong> 0923 XXX XXXX</p>
                    <p><strong>Email:</strong> <a href="mailto:lalaandc@gmail.com">lalaandc@gmail.com</a></p>
                    <p><strong>Facebook:</strong> <a href="https://www.facebook.com/lalalperfumery" target="_blank">facebook.com/lalalperfumery</a></p>
                </div>
                
                <form id="cancelForm">
                    <input type="hidden" id="cancel_order_id" name="order_id">
                    
                    <div class="form-group-custom">
                        <label>Reason for Cancellation: *</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="reason" value="Changed my mind" required>
                                <span>Changed my mind</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reason" value="Found a better price elsewhere" required>
                                <span>Found a better price elsewhere</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reason" value="Ordered by mistake" required>
                                <span>Ordered by mistake</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reason" value="Delivery taking too long" required>
                                <span>Delivery taking too long</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="reason" value="Other reasons" required>
                                <span>Other reasons</span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit-modal">
                        <i class='bx bx-check'></i> Submit Cancellation Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Refund Modal -->
    <div id="refundModal" class="modal-overlay">
        <div class="cancel-refund-modal">
            <div class="modal-header-custom">
                <h3><i class='bx bx-undo'></i> Request Refund</h3>
                <button class="close-btn" onclick="closeRefundModal()">&times;</button>
            </div>
            <div class="modal-body-custom">
                <div class="contact-info">
                    <h4>📞 To confirm your refund:</h4>
                    <p><strong>Contact No.:</strong> 0923 XXX XXXX</p>
                    <p><strong>Email:</strong> <a href="mailto:lalaandc@gmail.com">lalaandc@gmail.com</a></p>
                    <p><strong>Facebook:</strong> <a href="https://www.facebook.com/lalalperfumery" target="_blank">facebook.com/lalalperfumery</a></p>
                </div>
                
                <div class="refund-policy">
                    <h4>📋 Refund Policy</h4>
                    <ul>
                        <li>Product must be in original condition</li>
                        <li>Refund requests accepted within 7 days of delivery</li>
                        <li>Video evidence of product condition required</li>
                        <li>Admin will review and contact you within 2-3 business days</li>
                    </ul>
                </div>
                
                <form id="refundForm" enctype="multipart/form-data">
                    <input type="hidden" id="refund_order_id" name="order_id">
                    
                    <div class="form-group-custom">
                        <label>Upload Video Proof: *</label>
                        <div class="file-upload-area" onclick="document.getElementById('refund_video').click()">
                            <i class='bx bx-video' style="font-size: 48px; color: #ccc;"></i>
                            <p>Click to upload video (Max 50MB)</p>
                            <p style="font-size: 12px; color: #999;">MP4, MOV, AVI, or WEBM</p>
                            <input type="file" id="refund_video" name="refund_video" accept="video/*" required>
                        </div>
                        <div id="video_filename" style="margin-top: 10px; color: #667eea; font-weight: 600;"></div>
                    </div>
                    
                    <div class="form-group-custom">
                        <label>Reason for Refund: *</label>
                        <textarea name="reason_description" class="form-control" placeholder="Please describe why you want to return this product..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit-modal">
                        <i class='bx bx-check'></i> Submit Refund Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>© 2025 Lalal & Co. All rights reserved.</p>
    </footer>

    <script>
        const orderItemsData = <?php echo json_encode($order_items); ?>;
        
        document.querySelectorAll('.star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                document.getElementById('rating_value').value = rating;
                
                document.querySelectorAll('.star').forEach(s => {
                    s.classList.remove('bxs-star');
                    s.classList.add('bx-star');
                });
                
                for(let i = 1; i <= rating; i++) {
                    document.querySelector(`[data-rating="${i}"]`).classList.remove('bx-star');
                    document.querySelector(`[data-rating="${i}"]`).classList.add('bxs-star');
                }
            });
        });

        function openRatingModal(orderId, productId, productName) {
            document.getElementById('rating_order_id').value = orderId;
            document.getElementById('rating_product_id').value = productId;
            document.getElementById('rating_product_name').textContent = productName;
            document.getElementById('ratingModal').style.display = 'block';
        }

        function closeRatingModal() {
            document.getElementById('ratingModal').style.display = 'none';
            document.getElementById('ratingForm').reset();
            document.querySelectorAll('.star').forEach(s => {
                s.classList.remove('bxs-star');
                s.classList.add('bx-star');
            });
        }

        function openCancelModal(orderId) {
            document.getElementById('cancel_order_id').value = orderId;
            document.getElementById('cancelModal').classList.add('show');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
            document.getElementById('cancelForm').reset();
        }

        function openRefundModal(orderId) {
            document.getElementById('refund_order_id').value = orderId;
            document.getElementById('refundModal').classList.add('show');
        }

        function closeRefundModal() {
            document.getElementById('refundModal').classList.remove('show');
            document.getElementById('refundForm').reset();
            document.getElementById('video_filename').textContent = '';
        }

        document.getElementById('refund_video').addEventListener('change', function() {
            const filename = this.files[0]?.name || '';
            document.getElementById('video_filename').textContent = filename ? `Selected: ${filename}` : '';
        });

        document.getElementById('cancelForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('cancel_order.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                console.error(error);
            }
        });

        document.getElementById('refundForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            const videoFile = document.getElementById('refund_video').files[0];
            if (!videoFile) {
                alert('Please upload a video file');
                return;
            }
            
            if (videoFile.size > 50 * 1024 * 1024) {
                alert('Video file is too large. Maximum size is 50MB');
                return;
            }
            
            try {
                const response = await fetch('refund_order.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                console.error(error);
            }
        });

        function viewOrder(order) {
            const hasTracking = order.tracking_number && 
                    order.tracking_number.trim() !== '' && 
                    order.tracking_number.toUpperCase() !== 'NULL' &&
                    order.tracking_number !== '0';
            
            let itemsHtml = '<h4 style="margin-top: 20px;">Order Items</h4><div style="border-top: 1px solid #ddd; padding-top: 15px;">';
            
            const orderItems = orderItemsData || {};
            const currentOrderItems = orderItems[order.order_id] || [];
            
            currentOrderItems.forEach(item => {
                itemsHtml += `
                    <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                        <div>
                            <strong>${escapeHtml(item.product_name)}</strong><br>
                            <small>Qty: ${item.quantity} × ₱${parseFloat(item.product_price).toFixed(2)}</small>
                        </div>
                        <div style="font-weight: bold;">₱${parseFloat(item.subtotal).toFixed(2)}</div>
                    </div>
                `;
            });
            
            itemsHtml += '</div>';
            
            let paymentStatusBadge = '';
            if (order.verification_status === 'verified') {
                paymentStatusBadge = '<span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">✓ Verified</span>';
            } else if (order.payment_status === 'pending_verification') {
                paymentStatusBadge = '<span style="background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">⏳ Pending</span>';
            } else {
                paymentStatusBadge = '<span style="background: #f8d7da; color: #721c24; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">✗ Failed</span>';
            }
            
            let transactionInfo = '';
            if (order.gcash_transaction_id) {
                const submittedDate = order.payment_submitted_at ? 
                    new Date(order.payment_submitted_at).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                
                transactionInfo = `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                        <h4>GCash Transaction Details</h4>
                        <p><strong>GCash Transaction ID:</strong> <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-family: monospace;">${escapeHtml(order.gcash_transaction_id)}</code></p>
                        ${submittedDate ? `<p><strong>Submitted on:</strong> ${submittedDate}</p>` : ''}
                    </div>
                `;
            }
            
            let trackingInfo = '';
            if (order.status === 'shipped' || order.status === 'delivered') {
                if (hasTracking) {
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
                                ${escapeHtml(order.tracking_number)}
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
                    if (order.status === 'delivered') {
                        const codMessage = order.payment_method === 'cod' 
                            ? 'This was a Cash on Delivery order. Tracking number was not required for this delivery method.'
                            : 'Tracking number was not assigned for this delivery. Your order was completed successfully.';
                        
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
                        trackingInfo = `
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 15px;">
                                <p style="margin: 0; color: #856404;"><strong>⏳ Tracking Number: Being Processed</strong></p>
                                <p style="margin: 8px 0 0 0; color: #856404; font-size: 12px;">
                                    Your tracking number will be available within 24 hours.
                                </p>
                            </div>
                        `;
                    }
                }
            } else {
                trackingInfo = `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #6c757d;">
                        <p style="margin: 0 0 8px 0; color: #6c757d; font-weight: 600;">📦 TRACKING STATUS</p>
                        <p style="margin: 0; color: #6c757d; font-size: 13px;">
                            Tracking number will be available once your order is shipped via J&T Express.
                        </p>
                    </div>
                `;
            }
            
            const orderDate = new Date(order.order_date).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #333;">Order Information</h4>
                        <p><strong>Order Date:</strong> ${orderDate}</p>
                        <p><strong>Order Status:</strong> <span style="background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600;">${order.status.toUpperCase()}</span></p>
                        <p><strong>Payment Status:</strong> ${paymentStatusBadge}</p>
                        <p><strong>Payment Method:</strong> ${order.payment_method ? order.payment_method.toUpperCase() : 'N/A'}</p>
                        <p><strong>Total Amount:</strong> <span style="font-weight: bold; font-size: 1.1rem; color: #28a745;">₱${parseFloat(order.total_amount).toFixed(2)}</span></p>
                        ${trackingInfo}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-bottom: 15px; color: #333;">Delivery Address</h4>
                        <p><strong>Full Name:</strong> ${escapeHtml(order.full_name || 'N/A')}</p>
                        <p><strong>Phone Number:</strong> ${escapeHtml(order.phone || 'N/A')}</p>
                        <p><strong>Address:</strong> ${escapeHtml(order.address || 'N/A')}</p>
                        <p><strong>City:</strong> ${escapeHtml(order.city || 'N/A')} ${escapeHtml(order.postal_code || '')}</p>
                        
                        <div style="margin-top: 15px; padding: 12px; background: #fff; border-radius: 6px; border-left: 3px solid #e74c3c;">
                            <strong style="color: #e74c3c;">📦 Courier Information</strong><br>
                            <span style="font-size: 14px; color: #333;">J&T Express</span>
                        </div>
                    </div>
                </div>
                
                ${transactionInfo}
                
                ${order.order_notes ? `
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
                    <h4 style="margin-bottom: 10px; color: #333;">Order Notes</h4>
                    <p style="margin: 0; font-style: italic;">${escapeHtml(order.order_notes)}</p>
                </div>
                ` : ''}
                
                ${itemsHtml}
            `;
            
            document.getElementById('orderModal').style.display = 'block';
        }

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
            }
            if (event.target.id === 'orderModal' || event.target.id === 'ratingModal') {
                event.target.style.display = 'none';
            }
        }

        async function confirmDelivery(orderId) {
            if (!confirm('Are you sure you want to confirm the delivery of this order?')) {
                return;
            }
            
            try {
                const response = await fetch('confirm_delivery.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Thank you for confirming your delivery!');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                console.error(error);
            }
        }
    </script>
</body>
</html>