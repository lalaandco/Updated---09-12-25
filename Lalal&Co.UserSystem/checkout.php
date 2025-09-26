<?php  
if (basename($_SERVER['PHP_SELF']) == 'checkout.php') {
        header("Location: AddToCart.php?page=checkout");
        exit;
    }

$isLoggedIn = isset($_SESSION["email"]);

$userEmail = $_SESSION["email"] ?? '';
$userAddress = $_SESSION["address"] ?? '';
$userContact = $_SESSION["contact-number"] ?? '';
?>      

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKOUT</title>
    <link rel="stylesheet" href="checkout.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="overlay"></div>
    <?php if ($isLoggedIn): ?>
        <div class="delivery-address">
            <div class="back-button">
                <a href="index.php">
                    <i class='bx bx-window-close'></i>
                </a>
            </div>  
            <h2>DELIVERY ADDRESS    </h2>
            <hr>
            <p><strong><?=$userEmail?></strong> (<?= $userContact?>)  <?= $userAddress?>  </p>
            <h4>PRODUCT ORDERED</h4>
            <h5>
                <ul>
                    <li>PRICE</li>
                    <li>QUANTITY</li>
                    <li>SUB TOTAL</li>
                </ul>
            </h5>

            <form action="checkout.php" method="post" id="checkoutForm">
                <div class="product-info">
                    <input type="radio" name="DiorSauvage" value="299" class="product-radio">
                    <img src="images/DiorSauvage.png" alt="Perfume" class="product-image">
                    <div>
                        <p id="perfumes-name">
                            <span><strong>Lalal&Co-OIL BASED PERFUME FOR HIM IN 50ML</strong></span>
                        </p>
                        <h5>
                            <ul>
                                <li>299</li>
                                <li>1</li>
                                <li>299</li>
                            </ul>
                        </h5>
                    </div>
                </div>

                <div class="product-info">
                    <input type="radio" name="DiorSauvage2" value="399" class="product-radio">
                    <img src="images/DiorSauvage.png" alt="Perfume" class="product-image">
                    <div>
                        <p id="perfumes-name">
                            <span><strong>Lalal&Co-OIL BASED PERFUME FOR HIM IN 50ML</strong></span>
                        </p>
                        <h5>
                            <ul>
                                <li>299</li>
                                <li>1</li>
                                <li>299</li>
                            </ul>
                        </h5>
                    </div>
                </div>
            </form>

            <br>
            <hr>

            <p style="display:flex; justify-content: flex-end;">
                <strong>TOTAL: ₱</strong>
                <span id="total">0</span>
            </p>
            <button popovertarget="payment-method" style="position:relative;left: 95%; font-size:30px; cursor:pointer; font-weight:bold; background-color:gold;padding: 10px;border-radius:10px;">BUY</button>
            
            <div id="payment-method" popover>
                <h5 id="po">PAYMENT OPTION</h5>
                <hr>
                <p>Payment Method <i class='bx bx-info-circle'></i></p>

                <input type="radio" name="payment" value="Master Card">
                <span class="payment-text">Master Card</span><br>
                <input type="radio" name="payment" value="GCash">
                <span class="payment-text">GCash</span><br>
                <input type="radio" name="payment" value="Paypal">
                <span class="payment-text">Paypal</span><br>
                <input type="radio" name="payment" value="cash on delivery">
                <span class="payment-text">Cash On Delivery</span><br>
                <button id="confirm-button" popovertarget="payment-success" style="position:relative; cursor:pointer; font-size:16px; font-weight:bold; background-color:gold;padding: 10px;border-radius:10px;left: 80%;">Confirm</button>
                
                <div id="payment-success" popover>
                    <div class="success-content">
                        <i class='bx bx-check-circle success-icon'></i>
                        <h3 class="success-title">Payment Successful!</h3>
                        <p class="success-message">Thank you for your purchase</p>
                        <button onclick="window.location.href='index.php'" class="continue-btn">
                            Continue Shopping
                        </button>
                    </div>
                </div>
                <script>
                    // Validate product selection before showing payment modal
                    document.querySelector('[popovertarget="payment-method"]').addEventListener('click', function(e) {
                        const selectedProducts = document.querySelectorAll('input[name="DiorSauvage"]:checked, input[name="DiorSauvage2"]:checked');
                        if (!selectedProducts.length) {
                            e.preventDefault();
                            alert("Please select at least one product before proceeding to payment.");
                        }
                    });

                    // Handle payment confirmation
                    document.getElementById("confirm-button").addEventListener("click", function(e) {
                        const selectedPayment = document.querySelector('input[name="payment"]:checked');
                        const selectedProducts = document.querySelectorAll('input[name="DiorSauvage"]:checked, input[name="DiorSauvage2"]:checked');
                        
                        if (!selectedProducts.length) {
                            e.preventDefault();
                            alert("Please select at least one product.");
                            return;
                        }
                        
                        if (!selectedPayment) {
                            e.preventDefault();
                            alert("Please select a payment method.");
                            return;
                        }
                        
                        // Payment success popover will show automatically due to popovertarget attribute
                    });
                </script>

            </div>

        </div>
     <?php else: ?>
        <script>
            window.location.href = 'index.php?page=login';
        </script>
    <?php endif; ?>
    
    <!-- Add this script before the closing body tag -->
    <script>
        function updateTotal() {
            // Select all checked radio buttons
            const selectedProducts = document.querySelectorAll('input[name="DiorSauvage"]:checked, input[name="DiorSauvage2"]:checked');
            const totalSpan = document.getElementById('total');
            
            let total = 0;
            // Sum up the values of all checked radio buttons
            selectedProducts.forEach(product => {
                total += parseInt(product.value);
            });
            
            totalSpan.textContent = total;
        }

        // Add event listeners to all radio buttons
        const radioButtons = document.querySelectorAll('input[type="radio"].product-radio');
        radioButtons.forEach(button => {
            button.addEventListener('change', updateTotal);
        });

        // Initialize total when page loads
        updateTotal();
    </script>
</body>
</html>