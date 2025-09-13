<?php
session_start();
$isLoggedIn = isset($_SESSION["email"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Page Title</title>
    <link rel="stylesheet" href="styleIndex.css">
    <link rel="stylesheet" href="buyingProduct.css">
    <!-- Add other CSS files as needed -->
</head>
<body>
    <?php include 'header.php'; ?>
    
  
     <div class="page">
    <div class="product">
      <!-- LEFT: image in a light-gray card -->
      <div class="image-panel">
        <!-- Use your perfume image here -->
        <img src="images/DIOR SAUVAGE.png" alt="Perfume">
      </div>

      <!-- RIGHT: product details -->
      <div class="details">
        <h1 class="title">Cnalb Tnom</h1>
        <p class="price">PHP 299</p>

        <div class="stars">
          ⭐ ⭐ ⭐ ⭐ ⭐ <span class="muted">4.9 | 1k sold | 740 ratings <br></span>
           <span class="muted">Shipping Fee: 25 pesos</span>
        </div>

        <div class="ship">
          <span class="muted">Shipping:</span> Brgy. San Isidro, Makati City
        </div>
      <?php if ($isLoggedIn): ?>
        <button class="btn" onclick="handleAddToBag()">Add to Bag</button>
          <p class="note><?= $_SESSION['product_description']?></p>  
      <?php else: ?>
          <button class="btn" onclick="handleAddToBag()">Add to Bag</button>

          <p class="note"><b>Top notes:</b> Lavender, Pineapple, Bergamot, Lemon Verbena</p>
          <p class="note"><b>Middle notes:</b> Red Apple, Dried Fruits, Oak Moss, Geranium, Rose</p>
          <p class="note"><b>Base notes:</b> Tonka Bean, Sandalwood</p>
      <?php endif; ?>

<script>
  function thatLoggedIn() {
    alert("Item added to your bag!");
  }
</script>

<script>
    function handleAddToBag() {
        const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';

        if (!isLoggedIn) {
            // Redirect to login page
            window.location.href = "login.php"; // Adjust as needed
        }
    }
</script>

    </div>
  </div>

  <script src="script.js"></script>

  
    

    
</body>
</html>