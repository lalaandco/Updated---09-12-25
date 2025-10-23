<?php
session_start();

if (!isset($_SESSION["email"])) {
    header("Location: index.php?page=login");
    exit();
}

// Check if we have pending order data
if (!isset($_SESSION['pending_order'])) {
    header("Location: AddToCart.php");
    exit();
}

require_once 'config.php';

$pending_order = $_SESSION['pending_order'];
$order_total = $pending_order['total_amount'];

$error_message = null;

// Handle payment submission - ONLY SAVE PAYMENT PROOF, DON'T CREATE ORDER YET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $transaction_id = trim($_POST['gcash_transaction_id'] ?? '');
    
    if (empty($transaction_id)) {
        $error_message = "Please enter your GCash Transaction ID";
    } elseif (strlen($transaction_id) < 8) {
        $error_message = "Transaction ID must be at least 8 characters";
    } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] != 0) {
        $error_message = "Please upload a payment screenshot";
    } else {
        $file = $_FILES['screenshot'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed_types)) {
            $error_message = "Invalid file type. Please upload JPEG, PNG, or GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error_message = "File too large. Maximum 5MB allowed";
        } else {
            $upload_dir = 'uploads/payment_proofs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = time() . '_' . basename($file['name']);
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                try {
                    $conn->begin_transaction();
                    
                    // ✅ Step 1: Create the order with "pending" payment status
                    $order_stmt = $conn->prepare("
                        INSERT INTO orders (
                            user_email, full_name, phone, address, city, postal_code, 
                            payment_method, total_amount, order_notes, order_date, status, payment_status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 'pending_verification')
                    ");
                    $order_stmt->bind_param(
                        "sssssssds", 
                        $pending_order['user_email'],
                        $pending_order['full_name'],
                        $pending_order['phone'],
                        $pending_order['address'],
                        $pending_order['city'],
                        $pending_order['postal_code'],
                        $pending_order['payment_method'],
                        $pending_order['total_amount'],
                        $pending_order['order_notes']
                    );
                    
                    if (!$order_stmt->execute()) {
                        throw new Exception('Failed to create order');
                    }
                    
                    $order_id = $conn->insert_id;
                    $order_stmt->close();
                    
                    // ✅ Step 2: Insert order items (but DON'T deduct stock yet)
                    $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

                    foreach ($pending_order['selected_items'] as $item) {
                        $product_id = intval($item['id']);
                        $product_name = $item['name'];
                        $product_price = floatval($item['price']);
                        $quantity = intval($item['quantity']);
                        $subtotal = $product_price * $quantity;
                        
                        $item_stmt->bind_param("iisdid", $order_id, $product_id, $product_name, $product_price, $quantity, $subtotal);
                        if (!$item_stmt->execute()) {
                            throw new Exception('Failed to insert order item for: ' . $product_name);
                        }
                    }
                    $item_stmt->close();
                    
                    // ✅ Step 3: Save payment verification info for admin review
                    $insert_stmt = $conn->prepare("INSERT INTO payment_verifications (order_id, gcash_transaction_id, screenshot_path, verification_status) VALUES (?, ?, ?, 'pending')");
                    $insert_stmt->bind_param("iss", $order_id, $transaction_id, $filepath);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                    
                    $conn->commit();
                    
                    // ✅ Clear session data (but DON'T clear cart yet - wait for admin verification)
                    unset($_SESSION['pending_order']);
                    
                    // Redirect to success page
                    header("Location: afterCheckout.php");
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    @unlink($filepath);
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
                <p><strong>Pending Order</strong></p>
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
                    <input style="width: 100%; padding: 12px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem;"
                    type="text" id="gcash_transaction_id" name="gcash_transaction_id" required 
                           placeholder="Enter your GCash transaction reference (e.g., TXN123456789)" class="input-field">
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
                <p style="margin-top: 15px; font-size: 13px; color: #666; text-align: center;">
                    ⏳ Your order will be confirmed after admin verifies your payment (usually within 24 hours)
                </p>
            </form>
        </div>
    </div>

    <div class="fullscreen-preview" id="fullscreen-preview">
        <button class="close-preview" onclick="closeFullscreen()">×</button>
        <img id="fullscreen-img" src="#" alt="Full Screen Preview">
    </div>

    <footer>
        <p>© 2025 Lalal & Co. All rights reserved.</p>
    </footer>

    <script>
        function previewImage(file) {
            if (file) {
                const reader = new FileReader();
                const previewContainer = document.getElementById('preview-container');
                const previewImg = document.getElementById('preview-img');
                const fullscreenImg = document.getElementById('fullscreen-img');

                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    fullscreenImg.src = e.target.result;
                    previewContainer.style.display = 'block';
                };

                reader.readAsDataURL(file);
            }
        }

        // Preview when file is selected
        document.getElementById('screenshot').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                previewImage(file);
            } else {
                document.getElementById('preview-container').style.display = 'none';
            }
        });

        // Open fullscreen preview when clicking on preview image
        document.getElementById('preview-img').addEventListener('click', function() {
            document.getElementById('fullscreen-preview').classList.add('active');
        });

        // Close fullscreen preview
        function closeFullscreen() {
            document.getElementById('fullscreen-preview').classList.remove('active');
        }

        // Close fullscreen preview when clicking outside the image
        document.getElementById('fullscreen-preview').addEventListener('click', function(e) {
            if (e.target === this) {
                closeFullscreen();
            }
        });

        // Close fullscreen preview with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('fullscreen-preview').classList.contains('active')) {
                closeFullscreen();
            }
        });

        // Form validation
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