<?php
session_start();
$isLoggedIn = isset($_SESSION["email"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="afterCheck.css">
  <title>La Gal & Co. Official Store</title>
</head>
<body>  
  <?php include 'header.php'; ?>

  <!-- MAIN -->
  <main>
    <div class="container">
      <div class="hero">
        <h1>THANK YOU FOR PURCHASING<br>AT LALAL &amp; CO.</h1>
        <p>Your order will be delivered soon.</p>
        <a href="index.php" class="btn">Continue Shopping</a>
      </div>
      
      <h2 class="section-title">Recommended</h2>
      <div class="divider"></div>

      <!-- Products -->
      <section class="products" aria-label="Recommended products">
        
        <!-- Card 1 -->
        <div class="card">
          <div class="card-image">
            <img src="images/1.png" alt="Product">
            <span class="card-price">₱299.00</span>
          </div>
          <div class="card-body">
            <h3 class="card-title">Der Etsocal</h3>
            <div class="card-meta">
            </div>
          </div>
        </div>

        <!-- Card 2 -->
        <div class="card">
          <div class="card-image">
            <img src="images/3.png" alt="Product">
            <span class="card-price">₱549.00  </span>
          </div>
          <div class="card-body">
            <h3 class="card-title">AG Cloud</h3>
            <div class="card-meta">
            </div>
          </div>
        </div>

        <!-- Card 3 -->
        <div class="card">
          <div class="card-image">
            <img src="images/2.png" alt="Product">
            <span class="card-price">₱349.00</span>
          </div>
          <div class="card-body">
            <h3 class="card-title">Raep Hsilgne MJ</h3>
            <div class="card-meta">
            </div>
          </div>
        </div>

        <!-- Card 4 -->
        <div class="card">
          <div class="card-image">
            <img src="images/4.png" alt="Product">
            <span class="card-price">₱399.00</span>
          </div>
          <div class="card-body">
            <h3 class="card-title">Sweet Pea </h3>
            <div class="card-meta">
            </div>
          </div>
        </div>

        <!-- Card 5 -->
        <div class="card">
          <div class="card-image">
            <img src="images/5.png" alt="Product">
            <span class="card-price">₱299.00</span>
          </div>
          <div class="card-body">
            <h3 class="card-title">Sutcivni</h3>
            <div class="card-meta">
            </div>
          </div>
        </div>


      </section>
    </div>
  </main>

</body>
</html>