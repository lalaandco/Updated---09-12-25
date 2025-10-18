<?php
session_start();

if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

if (!isset($_SESSION['order_id'])) {
    header("Location: AddToCart.php");
    exit();
}

require_once 'config.php';

$order_id = $_SESSION['order_id'];
$order_total = $_SESSION['order_total'] ?? 0;

// Get order details
$order_stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_email = ?");
$order_stmt->bind_param("is", $order_id, $_SESSION['email']);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    header("Location: AddToCart.php");
    exit();
}

$error_message = null;
$success_message = null;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $transaction_id = trim($_POST['gcash_transaction_id'] ?? '');
    
    // Validate input
    if (empty($transaction_id)) {
        $error_message = "Please enter your GCash Transaction ID";
    } elseif (strlen($transaction_id) < 8) {
        $error_message = "Transaction ID must be at least 8 characters";
    } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] != 0) {
        $error_message = "Please upload a payment screenshot";
    } else {
        // Validate file
        $file = $_FILES['screenshot'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Invalid file type. Please upload JPEG, PNG, or GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error_message = "File too large. Maximum 5MB allowed";
        } else {
            // Save screenshot
            $upload_dir = 'uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Create unique filename
            $filename = 'order_' . $order_id . '_' . time() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    $conn->begin_transaction();
                    
                    // Check if verification already exists
                    $check_stmt = $conn->prepare("SELECT verification_id FROM payment_verifications WHERE order_id = ?");
                    $check_stmt->bind_param("i", $order_id);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($existing) {
                        // Update existing verification
                        $update_stmt = $conn->prepare("UPDATE payment_verifications SET gcash_transaction_id = ?, screenshot_path = ?, verification_status = 'pending', submitted_at = NOW() WHERE order_id = ?");
                        $update_stmt->bind_param("ssi", $transaction_id, $filepath, $order_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    } else {
                        // Insert new verification
                        $insert_stmt = $conn->prepare("INSERT INTO payment_verifications (order_id, gcash_transaction_id, screenshot_path, verification_status) VALUES (?, ?, ?, 'pending')");
                        $insert_stmt->bind_param("iss", $order_id, $transaction_id, $filepath);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    
                    // Update order payment_status
                    $order_update = $conn->prepare("UPDATE orders SET payment_status = 'pending_verification' WHERE order_id = ?");
                    $order_update->bind_param("i", $order_id);
                    $order_update->execute();
                    $order_update->close();
                    
                    $conn->commit();
                    
                    // Clear session
                    unset($_SESSION['order_id']);
                    unset($_SESSION['order_total']);
                    unset($_SESSION['selectedItems']);
                    unset($_SESSION['checkoutTotal']);
                    unset($_SESSION['cart']);
                    
                    // Redirect to afterCheckout page
                    header("Location: afterCheckout.php");
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    @unlink($filepath); // Delete uploaded file if DB insert fails
                    $error_message = "Error saving payment information: " . htmlspecialchars($e->getMessage());
                }
            } else {
                $error_message = "Error uploading screenshot. Please try again.";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="payments.css">
    <title>GCash Payment - Lalal & Co.</title>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="payment-container">
        <div class="payment-image">
            <p style="margin-top: 15px; text-align: center;">Please scan the QR code using your <span class="gcash-brand">GCash</span> app</p>
            <img src="images/gcashPaymentQR.jpg" alt="GCash QR Code">
        </div>
        
        <div class="payment-content">
            <h2><span class="gcash-brand">GCash</span> Payment</h2>
            
            <div class="order-info-box">
                <p><strong>Order #<?php echo str_pad($order_id, 8, '0', STR_PAD_LEFT); ?></strong></p>
                <p>Total Amount: <strong>₱<?php echo number_format($order_total, 2); ?></strong></p>
            </div>
            
            <div class="gcash-info">
                <p><strong>GCash Account Details:</strong></p>
                <p>Name: Martin Fredirico Bechayda</p>
                <p>Mobile No.: +63 961 272 4768</p>
                <p>User ID: XXXXXWLKMR2</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <div class="payment-instructions">
                <strong>Payment Steps:</strong><br>
                1. Open your <span class="gcash-brand">GCash</span> app and select "Send Money"<br>
                2. Enter the recipient mobile number or scan the QR code above<br>
                3. Enter the payment amount: <strong>₱<?php echo number_format($order_total, 2); ?></strong><br>
                4. Complete the transaction<br>
                5. Fill in the form below with your transaction details
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="gcash_transaction_id">GCash Transaction ID/Reference Number *</label>
                    <input style="
                    width: 100%;
                    padding: 12px;
                    margin-top: 5px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    font-size: 1rem;"
                    type="text" id="gcash_transaction_id" name="gcash_transaction_id" required 
                           placeholder="Enter your GCash transaction reference (e.g., TXN123456789)" class="input-field" >
                    <small style="color: #666;">You can find this in your GCash app transaction history</small>
                </div>
                
                <div class="form-group">
                    <label for="screenshot">Upload Payment Screenshot *</label>
                    <div class="file-upload">
                        <input type="file" id="screenshot" name="screenshot" accept="image/*" required>
                        <button type="button" id="preview-btn" class="preview-button">Preview Screenshot</button>
                    </div>
                    <small style="color: #666;">Upload a clear screenshot showing the completed transaction from your GCash app</small>
                </div>
                
                <div class="preview-container" id="preview-container">
                    <p><strong>Screenshot Preview:</strong></p>
                    <img id="preview-img" src="#" alt="Screenshot Preview">
                </div>
                
                <input type="hidden" name="submit_payment" value="1">
                <button type="submit" class="btn-submit">Submit Payment Proof</button>
            </form>
        </div>
    </div>

    <footer>
        <p>© 2025 Lalal & Co. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('preview-btn').addEventListener('click', function() {
            const screenshotInput = document.getElementById('screenshot');
            if (screenshotInput.files && screenshotInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('preview-container').style.display = 'block';
                };
                reader.readAsDataURL(screenshotInput.files[0]);
            } else {
                alert('Please select a screenshot file first');
            }
        });

        // Auto-preview on file selection
        document.getElementById('screenshot').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('preview-container').style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Form validation on submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const transactionId = document.getElementById('gcash_transaction_id').value.trim();
            const screenshot = document.getElementById('screenshot').files[0];

            if (!transactionId) {
                e.preventDefault();
                alert('Please enter your GCash Transaction ID');
                return;
            }

            if (transactionId.length < 8) {
                e.preventDefault();
                alert('Transaction ID must be at least 8 characters');
                return;
            }

            if (!screenshot) {
                e.preventDefault();
                alert('Please upload a screenshot');
                return;
            }

            const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!validTypes.includes(screenshot.type)) {
                e.preventDefault();
                alert('Invalid file type. Please upload JPEG, PNG, or GIF');
                return;
            }

            if (screenshot.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('File too large. Maximum 5MB allowed');
                return;
            }
        });
    </script>
</body>
</html>