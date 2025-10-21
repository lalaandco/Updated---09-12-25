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
    <link rel="stylesheet" href="myOrders.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <title>My Orders - Lalal & Co.</title>
    <style>
        /* Cancel and Refund Button Styles */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn-cancel, .btn-refund {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-cancel {
            background: #ff5252;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #ff1744;
            transform: translateY(-2px);
        }
        
        .btn-refund {
            background: #ff9800;
            color: white;
        }
        
        .btn-refund:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }
        
        /* Modal Overlay */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        /* Cancel/Refund Modal */
        .cancel-refund-modal {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header-custom h3 {
            margin: 0;
            font-size: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
        }
        
        .modal-body-custom {
            padding: 25px;
        }
        
        .contact-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .contact-info h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .contact-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .contact-info a {
            color: #667eea;
            text-decoration: none;
        }
        
        .form-group-custom {
            margin-bottom: 20px;
        }
        
        .form-group-custom label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .radio-option:hover {
            background: #e9ecef;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 10px;
            cursor: pointer;
        }
        
        textarea.form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        
        textarea.form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-area:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        
        .file-upload-area input[type="file"] {
            display: none;
        }
        
        .btn-submit-modal {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-submit-modal:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .refund-policy {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }
        
        .refund-policy h4 {
            margin: 0 0 10px 0;
            color: #856404;
        }
        
        .refund-policy ul {
            margin: 0;
            padding-left: 20px;
            color: #856404;
            font-size: 13px;
        }
        
        .status-badge-secondary {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .badge-cancel-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-refund-pending {
            background: #ffe0b2;
            color: #e65100;
        }
        
        .badge-cancelled {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-refunded {
            background: #e8f5e9;
            color: #2e7d32;
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
                            
                            <!-- Payment Transaction ID -->
                            <?php if (!empty($order['gcash_transaction_id'])): ?>
                                <div style="margin-top: 12px;">
                                    <div class="status-label">GCash Transaction ID</div>
                                    <div class="transaction-id">
                                        <code><?php echo htmlspecialchars($order['gcash_transaction_id']); ?></code>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-body">
                            <div class="order-items">
                                <div class="items-label">Items Ordered:</div>
                                <ul class="item-list">
                                    <?php 
                                    $items = $order_items[$order['order_id']] ?? [];
                                    foreach ($items as $item): 
                                    ?>
                                        <li><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <div class="order-total">
                                <span class="total-label">Total Amount:</span>
                                <span class="total-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="order-footer">
                            <button class="btn-view" onclick="viewOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                <i class='bx bx-show'></i> View Details
                            </button>
                            
                            <!-- Cancel/Refund Action Buttons -->
                            <div class="action-buttons">
                                <?php 
                                // Show CANCEL button only if:
                                // 1. Payment NOT yet verified by admin
                                // 2. Order NOT delivered
                                // 3. No existing cancellation request
                                // 4. Order not already cancelled or refunded
                                $canCancel = $order['verification_status'] !== 'verified' 
                                            && $order['status'] !== 'delivered' 
                                            && $order['status'] !== 'cancelled'
                                            && $order['status'] !== 'refunded'
                                            && $order['cancel_status'] !== 'pending';
                                
                                // Show REFUND button only if:
                                // 1. Order is delivered
                                // 2. No existing refund request
                                // 3. Order not already refunded
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
                            
                            <?php if ($order['status'] === 'delivered' && !empty($items)): ?>
                                <?php foreach ($items as $item): ?>
                                    <?php if (!isset($product_ratings[$order['order_id']][$item['product_id']])): ?>
                                        <button class="btn-rate" onclick="openRatingModal(<?php echo $order['order_id']; ?>, <?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>')">
                                            <i class='bx bx-star'></i> Rate <?php echo htmlspecialchars($item['product_name']); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal (existing) -->
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

    <!-- Rating Modal (existing) -->
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
                        <label>Review Title</label>
                        <input type="text" name="review_title" class="form-input" placeholder="Sum up your experience" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Review</label>
                        <textarea name="review_text" class="form-input" rows="4" placeholder="Tell us what you think about this product" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit-rating">Submit Rating</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Order Modal -->
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

    <!-- Refund Order Modal -->
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
        // Existing rating functionality
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

        // Cancel Modal Functions
        function openCancelModal(orderId) {
            document.getElementById('cancel_order_id').value = orderId;
            document.getElementById('cancelModal').classList.add('show');
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
            document.getElementById('cancelForm').reset();
        }

        // Refund Modal Functions
        function openRefundModal(orderId) {
            document.getElementById('refund_order_id').value = orderId;
            document.getElementById('refundModal').classList.add('show');
        }

        function closeRefundModal() {
            document.getElementById('refundModal').classList.remove('show');
            document.getElementById('refundForm').reset();
            document.getElementById('video_filename').textContent = '';
        }

        // File upload display
        document.getElementById('refund_video').addEventListener('change', function() {
            const filename = this.files[0]?.name || '';
            document.getElementById('video_filename').textContent = filename ? `Selected: ${filename}` : '';
        });

        // Cancel Form Submission
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

        // Refund Form Submission
        document.getElementById('refundForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Validate video file
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

        // View Order Details (existing functionality)
        function viewOrder(order) {
            const hasTracking = order.tracking_number && 
                    order.tracking_number.trim() !== '' && 
                    order.tracking_number.toUpperCase() !== 'NULL' &&
                    order.tracking_number !== '0';
            
            let itemsHtml = '<h4 style="margin-top: 20px;">Order Items</h4><div style="border-top: 1px solid #ddd; padding-top: 15px;">';
            
            <?php foreach ($orders as $o): ?>
                if (order.order_id === <?php echo $o['order_id']; ?>) {
                    <?php 
                    $items = $order_items[$o['order_id']] ?? [];
                    foreach ($items as $item): 
                    ?>
                        itemsHtml += `
                            <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
                                <div>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong><br>
                                    <small>Qty: <?php echo $item['quantity']; ?> × ₱<?php echo number_format($item['product_price'], 2); ?></small>
                                </div>
                                <div style="font-weight: bold;">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                        `;
                    <?php endforeach; ?>
                }
            <?php endforeach; ?>
            
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
                transactionInfo = `
                    <p><strong>GCash Transaction ID:</strong> <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px;">${order.gcash_transaction_id}</code></p>
                    ${order.payment_submitted_at ? `<p><strong>Submitted:</strong> ${new Date(order.payment_submitted_at).toLocaleDateString()}</p>` : ''}
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

        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('show');
            }
            if (event.target.id === 'orderModal' || event.target.id === 'ratingModal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>