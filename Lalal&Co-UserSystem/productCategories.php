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
    <link rel="stylesheet" href="Categories.css">
    <!-- Add other CSS files as needed -->
</head>
<body>
    <?php include 'header.php'; ?>
    
    <section class="product" id="product">
        <div class="select-category">
            <a href="productCategories.php" class="category-link active">For Him</a>
            <a href="productCategories2.php" class="category-link">For Her</a>
            <a href="productCategories3.php" class="category-link">For Kids</a>
            <a href="productCategories4.php" class="category-link">Accessories</a>
        </div>

        <div class="middle-text">
            <h2><span>For Him Collection</span></h2>
        </div>

        <div class="product-contents">
            <!-- Product 1 - Now fully clickable -->
            <a href="buyProduct.php?id=01" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>CNALB TNOM</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=02" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>DEERC SUTNEVA</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>
            
            <a href="buyProduct.php?id=03" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>DER ETSOCAL</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>
            
            <a href="buyProduct.php?id=04" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>EGAVUAS ROID</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=05" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>AUGUST</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=06" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>BILL</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=07" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>JAMES</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=08" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>JOE</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=09" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>DREW</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>

            <a href="buyProduct.php?id=10" class="product-link">
                <div class="box">
                    <div class="box-img">
                        <img src="images/ForHim.png">
                    </div>
                    <h3>JOHN</h3>
                    <div class="inbox">
                        <span class="price">₱299.00</span>
                    
                    </div>
                </div>
            </a>
            
    
</body>
</html>