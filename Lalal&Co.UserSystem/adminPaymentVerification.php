<?php
session_start();
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: adminLogin.php');
    exit();
}

$message = '';
$error = '';

// Handle payment verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $verification_id = intval($_POST['verification_id']);
    $action = $_POST['action']; // 'verify' or 'reject'
    $admin_notes = $_POST['admin_notes'] ?? '';
    $order_id = intval($_POST['order_id']);
    
    try {
        $conn->begin_transaction();
        
        if ($action === 'verify') {
            // Update payment verification status
            $verify_stmt = $conn->prepare("
                UPDATE payment_verifications 
                SET verification_status = 'verified', 
                    verified_at = NOW(), 
                    verified_by = ?,
                    admin_notes = ?
                WHERE verification_id = ?
            ");
            $verify_stmt->bind_param("ssi", $_SESSION['admin_email'], $admin_notes, $verification_id);
            $verify_stmt->execute();
            $verify_stmt->close();
            
            // Update order payment status
            $order_stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'verified', 
                    payment_verified_at = NOW()
                WHERE order_id = ?
            ");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_stmt->close();
            
            $message = "Payment verified successfully!";
            
        } else if ($action === 'reject') {
            // Update payment verification status
            $reject_stmt = $conn->prepare("
                UPDATE payment_verifications 
                SET verification_status = 'failed', 
                    verified_at = NOW(), 
                    verified_by = ?,
                    admin_notes = ?
                WHERE verification_id = ?
            ");
            $reject_stmt->bind_param("ssi", $_SESSION['admin_email'], $admin_notes, $verification_id);
            $reject_stmt->execute();
            $reject_stmt->close();
            
            // Update order payment status
            $order_stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'failed'
                WHERE order_id = ?
            ");
            $order_stmt->bind_param("i", $order_id);
            $order_stmt->execute();
            $order_stmt->close();
            
            $message = "Payment rejected. Admin notes saved.";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error: " . htmlspecialchars($e->getMessage());
    }
}

// Get pending payment verifications
$pending_stmt = $conn->prepare("
    SELECT 
        pv.*,
        o.order_id,
        o.full_name,
        o.user_email,
        o.total_amount,
        o.order_date,
        o.status as order_status
    FROM payment_verifications pv
    JOIN orders o ON pv.order_id = o.order_id
    WHERE pv.verification_status = 'pending'
    ORDER BY pv.submitted_at DESC
");
$pending_stmt->execute();
$pending_verifications = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pending_stmt->close();

// Get verified/rejected verifications for history
$history_stmt = $conn->prepare("
    SELECT 
        pv.*,
        o.order_id,
        o.full_name,
        o.total_amount,
        o.order_date
    FROM payment_verifications pv
    JOIN orders o ON pv.order_id = o.order_id
    WHERE pv.verification_status IN ('verified', 'failed')
    ORDER BY pv.verified_at DESC
    LIMIT 20
");
$history_stmt->execute();
$verification_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminStyle.css">
    <title>Payment Verification - Admin Panel</title>
    <style>
        .verification-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .verification-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .verification-header {
            padding: 20px;
            border-bottom: 2px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .verification-header h3 {
            margin: 0;
            color: #333;
        }
        
        .order-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .verification-body {
            padding: 20px;
        }
        
        .verification-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .detail-value {
            font-size: 14px;
            color: #333;
            word-break: break-all;
        }
        
        .screenshot-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .screenshot-section img {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        
        .verification-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-verify {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-verify:hover {
            background: #45a049;
        }
        
        .btn-reject {
            background: #f44336;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn-reject:hover {
            background: #da190b;
        }
        
        .admin-notes-field {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            font-family: Arial, sans-serif;
            resize: vertical;
            margin-top: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
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
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            background: #2196F3;
            color: white;
        }
        
        .tab-btn:hover {
            background: #e0e0e0;
        }
        
        .tab-btn.active:hover {
            background: #1976d2;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
        }
        
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
        }
        
        .history-table tr:hover {
            background: #f8f9fa;
        }
        
        .pending-count {
            background: #ff9800;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
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
            <div class="welcome-text">PAYMENT VERIFICATION</div>
        </div>
        
        <div class="right-section">
            <div class="profile-section">
                <img src="images/profile.png" alt="Profile">
                <span>Admin</span>
            </div>
        </div>
    </div>

    <div class="verification-container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('pending')">
                <i class='bx bx-time'></i> Pending Verification 
                <?php if (!empty($pending_verifications)): ?>
                    <span class="pending-count"><?php echo count($pending_verifications); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab('history')">
                <i class='bx bx-history'></i> Verification History
            </button>
        </div>
        
        <!-- Pending Verifications Tab -->
        <div id="pending" class="tab-content active">
            <?php if (empty($pending_verifications)): ?>
                <div class="verification-card">
                    <div class="verification-body" style="text-align: center; padding: 60px 20px;">
                        <i class='bx bx-check-circle' style="font-size: 48px; color: #4CAF50;"></i>
                        <h3 style="margin-top: 15px; color: #333;">All Payments Verified!</h3>
                        <p style="color: #666;">There are no pending payment verifications at this time.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pending_verifications as $verification): ?>
                    <div class="verification-card">
                        <div class="verification-header">
                            <div>
                                <h3>Order #<?php echo str_pad($verification['order_id'], 8, '0', STR_PAD_LEFT); ?></h3>
                                <p style="margin: 8px 0 0 0; color: #666; font-size: 13px;">
                                    Customer: <?php echo htmlspecialchars($verification['full_name']); ?> 
                                    (<?php echo htmlspecialchars($verification['user_email']); ?>)
                                </p>
                            </div>
                            <span class="status-badge status-pending">⏳ Pending</span>
                        </div>
                        
                        <div class="verification-body">
                            <div class="verification-details">
                                <div class="detail-item">
                                    <div class="detail-label">Order Date</div>
                                    <div class="detail-value"><?php echo date('M d, Y - h:i A', strtotime($verification['order_date'])); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Order Amount</div>
                                    <div class="detail-value" style="color: #4CAF50; font-weight: 600;">₱<?php echo number_format($verification['total_amount'], 2); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">GCash Transaction ID</div>
                                    <div class="detail-value" style="font-family: monospace; background: #f5f5f5; padding: 6px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($verification['gcash_transaction_id']); ?>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">Submitted On</div>
                                    <div class="detail-value"><?php echo date('M d, Y - h:i A', strtotime($verification['submitted_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="screenshot-section">
                                <div class="detail-label">Payment Screenshot</div>
                                <img src="<?php echo htmlspecialchars($verification['screenshot_path']); ?>" alt="Payment Screenshot">
                            </div>
                            
                            <form method="POST">
                                <input type="hidden" name="verification_id" value="<?php echo $verification['verification_id']; ?>">
                                <input type="hidden" name="order_id" value="<?php echo $verification['order_id']; ?>">
                                <input type="hidden" name="verify_payment" value="1">
                                
                                <div class="detail-item">
                                    <div class="detail-label">Admin Notes (Optional)</div>
                                    <textarea name="admin_notes" class="admin-notes-field" rows="3" placeholder="Add any verification notes..."></textarea>
                                </div>
                                
                                <div class="verification-actions">
                                    <button type="submit" name="action" value="verify" class="btn-verify">
                                        <i class='bx bx-check'></i> Verify Payment
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn-reject" onclick="return confirm('Are you sure you want to reject this payment? The customer will need to resubmit.');">
                                        <i class='bx bx-x'></i> Reject Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Verification History Tab -->
        <div id="history" class="tab-content">
            <div class="verification-card">
                <div class="verification-header">
                    <h3>Recent Verification History</h3>
                </div>
                
                <?php if (empty($verification_history)): ?>
                    <div class="verification-body" style="text-align: center; padding: 40px 20px;">
                        <p style="color: #666;">No verification history yet.</p>
                    </div>
                <?php else: ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verification_history as $history): ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($history['order_id'], 8, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td><?php echo htmlspecialchars($history['full_name']); ?></td>
                                    <td>₱<?php echo number_format($history['total_amount'], 2); ?></td>
                                    <td>
                                        <code style="background: #f5f5f5; padding: 4px 6px; border-radius: 3px; font-size: 12px;">
                                            <?php echo htmlspecialchars($history['gcash_transaction_id']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $history['verification_status'] === 'verified' ? 'status-verified' : 'status-rejected'; ?>">
                                            <?php echo $history['verification_status'] === 'verified' ? '✓ Verified' : '✗ Rejected'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($history['verified_by'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($history['verified_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all buttons
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>