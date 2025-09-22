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

  <!-- HEADER -->
  <section id="mainheader-section">
    <header class="header">
      <div class="main-header">
        <!-- Left -->
        <div class="left-section">
          <a href="index.php">
            <img src="lcLogo.png" alt="LG Logo" class="logo-small" 
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
          </a>
          <div class="logo-placeholder logo-small-placeholder" style="display:none;">LG</div>
        </div>

        <!-- Center -->
        <div class="center-section">
          <a href="index.php">
            <img src="homepage_title.png" alt="Lalal & Co" class="logo-main"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
          </a>
          <div class="logo-placeholder logo-main-placeholder" style="display:none;">LALAL & CO.</div>
        </div>

        <!-- Right -->
        <div class="right-section">
          <!-- Search -->
          <div class="search-container">
            <form action="" method="get" class="search-form">
              <input type="text" name="query" placeholder="Search" class="search-input" required>
              <button type="submit" class="search-icon icon">
                <svg viewBox="0 0 24 24">
                  <path d="M15.5 14h-.79l-.28-.27A6.471 
                           6.471 0 0 0 16 9.5 
                           6.5 6.5 0 1 0 9.5 16
                           c1.61 0 3.09-.59 4.23-1.57l.27.28v.79
                           l5 4.99L20.49 19l-4.99-5zm-6 
                           0C7.01 14 5 11.99 5 9.5
                           S7.01 5 9.5 5 
                           14 7.01 14 9.5 
                           11.99 14 9.5 14z"/>
                </svg>
              </button>
            </form>
          </div>

          <!-- User/Login -->
          <a href="login.php">
            <div class="icon">
              <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 
                         2 12s4.48 10 10 10 
                         10-4.8 10-10S17.52 2 12 2zm0 3c1.66 0 
                         3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 
                         1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22
                         .03-1.99 44-3.08 6-3.08 1.99 0 
                         5.97 1.09 6 3.08-1.29 1.94-3.5 
                         3.22-6 3.22z"/>
              </svg>
            </div>
          </a>

          <!-- Heart -->
          <div class="icon">
            <svg viewBox="0 0 24 24">   
              <path d="M12 21.35l-1.45-1.32C5.4 
                       15.36 2 12.28 2 8.5 
                       2 5.42 4.42 3 7.5 3c1.74 0 
                       3.41.81 4.5 2.09C13.09 3.81 
                       14.76 3 16.5 3 19.58 3 
                       22 5.42 22 8.5c0 3.78-3.4 
                       6.86-8.55 11.54L12 21.35z"/>
            </svg>
          </div>

          <!-- Cart -->
          <div class="icon cart-icon">
            <svg viewBox="0 0 24 24">
              <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 
                       22 7 22s2-.9 2-2-.9-2-2-2zM1 
                       2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 
                       0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25
                       l.03-.12.9-1.63h7.45c.75 0 1.41-.41 
                       1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 
                       0-.55-.45-1-1-1H5.21l-.94-2H1zm16 
                       16c-1.1 0-1.99.9-1.99 2s.89 
                       2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
            </svg>
            <span class="cart-count">0</span>
          </div>
        </div>
      </div>
    </header>
  </section>

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