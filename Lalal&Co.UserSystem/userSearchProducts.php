<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION["email"]);
$search_query = $_GET['query'] ?? '';
$category_filter = $_GET['category'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'relevance';

$products = [];
$search_performed = false;

if (!empty($search_query)) {
    $search_performed = true;
    
    // Build the WHERE clause
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Search in product name and description
    $search_term = "%{$search_query}%";
    $where_conditions[] = "(product_name LIKE ? OR product_description LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
    
    // Category filter
    if ($category_filter !== 'all') {
        $where_conditions[] = "category = ?";
        $params[] = $category_filter;
        $param_types .= 's';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Determine sort order
    $order_by = "product_name ASC"; // default
    switch ($sort_by) {
        case 'price_low':
            $order_by = "product_price ASC";
            break;
        case 'price_high':
            $order_by = "product_price DESC";
            break;
        case 'rating':
            $order_by = "average_rating DESC, total_ratings DESC";
            break;
        case 'popular':
            $order_by = "total_ratings DESC";
            break;
        case 'name_az':
            $order_by = "product_name ASC";
            break;
        case 'name_za':
            $order_by = "product_name DESC";
            break;
        default: // relevance
            $order_by = "CASE 
                WHEN product_name LIKE ? THEN 1 
                WHEN product_description LIKE ? THEN 2 
                ELSE 3 
            END, product_name ASC";
            // Add search term again for relevance sorting
            $params = array_merge([$search_term, $search_term], $params);
            $param_types = 'ss' . $param_types;
    }
    
    $query = "SELECT 
                product_id,
                product_name,
                product_description,
                product_price,
                product_quantity,
                category,
                image_path,
                average_rating,
                total_ratings,
                display_quantity
              FROM product_tbl 
              WHERE {$where_clause}
              ORDER BY {$order_by}";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@latest/css/boxicons.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="forIndexs.css">
    <title>Search Results - Lalal & Co.</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            max-height: 100vh;
            margin-top: 130px;
            padding: 0;
        }
        .search-results-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .search-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .search-title {
            font-size: 26px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .search-query {
            color: #202217ff;
            font-weight: 600;
        }
        
        .search-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .results-count {
            color: #666;
            font-size: 24px;
            font-weight: 500;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            padding: 5px 0;
            gap: 15px;
        }
        
        .filter-group label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            background: white;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .no-results-icon {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .no-results h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .no-results p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #45a049;
        }
        
        /* Product Grid Styles */
        .product-content {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .product-link {
            text-decoration: none;
            color: inherit;
        }
        
        .box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .box:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .box-img {
            width: 100%;
            height: 280px;
            overflow: hidden;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        
        .box-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 15px;
        }
        
        .box h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            min-height: 40px;
        }
        
        .inbox {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .price {
            font-size: 20px;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .add-to-cart-btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s;
        }
        
        .add-to-cart-btn:hover {
            background: #45a049;
        }
        
        /* Rating Styles */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .product-rating .stars {
            display: flex;
            gap: 2px;
        }
        
        .star-icon {
            color: #ddd;
            font-size: 16px;
        }
        
        .star-icon.filled {
            color: #ffc107;
        }
        
        .rating-text {
            color: #666;
            font-weight: 500;
        }
        
        .rating-count {
            color: #999;
            font-size: 12px;
        }
        
        .stock-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
            display: inline-block;
        }
        
        .in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="search-results-container">
        <div class="search-header">
            <h1 class="search-title">
                <?php if ($search_performed): ?>
                    Search Results for "<span class="search-query"><?php echo htmlspecialchars($search_query); ?></span>"
                <?php else: ?>
                    Search Products
                <?php endif; ?>
            </h1>
            
            <?php if ($search_performed): ?>
            <div class="search-meta">
                <div class="results-count">
                    Found <?php echo count($products); ?> product<?php echo count($products) !== 1 ? 's' : ''; ?>
                </div>
                
                <div class="search-filters">
                    <form method="GET" action="userSearchProducts.php" id="filterForm">
                        <input type="hidden" name="query" value="<?php echo htmlspecialchars($search_query); ?>">
                        
                        <div class="filter-group">
                            <label for="category">Category:</label>
                            <select name="category" id="category" onchange="document.getElementById('filterForm').submit()">
                                <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="ForHim" <?php echo $category_filter === 'ForHim' ? 'selected' : ''; ?>>For Him</option>
                                <option value="ForHer" <?php echo $category_filter === 'ForHer' ? 'selected' : ''; ?>>For Her</option>
                                <option value="Others" <?php echo $category_filter === 'Others' ? 'selected' : ''; ?>>Others</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="sort">Sort By:</label>
                            <select name="sort" id="sort" onchange="document.getElementById('filterForm').submit()">
                                <option value="relevance" <?php echo $sort_by === 'relevance' ? 'selected' : ''; ?>>Relevance</option>
                                <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="popular" <?php echo $sort_by === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="name_az" <?php echo $sort_by === 'name_az' ? 'selected' : ''; ?>>Name: A-Z</option>
                                <option value="name_za" <?php echo $sort_by === 'name_za' ? 'selected' : ''; ?>>Name: Z-A</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$search_performed): ?>
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h3>Start Searching</h3>
                <p>Enter a search term in the search bar above to find products</p>
                <a href="index.php" class="back-btn">Back to Home</a>
            </div>
        <?php elseif (empty($products)): ?>
            <div class="no-results">
                <h3>No Products Found</h3>
                <p>We couldn't find any products matching "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
                <p>Try searching with different keywords or browse our categories</p>
                <a href="index.php" class="back-btn">Back to Home</a>
            </div>
        <?php else: ?>
            <div class="product-content">
                <?php foreach ($products as $product): ?>
                    <a href="buyProduct.php?id=<?php echo $product['product_id']; ?>" class="product-link">
                        <div class="box">
                            <div class="box-img">
                                <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            </div>
                            
                            <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            
                            <?php
                            $stock = $product['display_quantity'];
                            if ($stock > 10): ?>
                                <span class="stock-status in-stock">✓ In Stock</span>
                            <?php elseif ($stock > 0): ?>
                                <span class="stock-status low-stock">⚠ Only <?php echo $stock; ?> left</span>
                            <?php else: ?>
                                <span class="stock-status out-of-stock">✗ Out of Stock</span>
                            <?php endif; ?>
                            
                            <div class="inbox">
                                <span class="price">₱<?php echo number_format($product['product_price'], 2); ?></span>
                                <button class="add-to-cart-btn" onclick="addToCart(event, <?php echo $product['product_id']; ?>)">
                                    <i class='bx bx-cart-add'></i>
                                </button>
                            </div>
                            
                            <!-- Rating Display -->
                            <?php if ($product['total_ratings'] > 0): ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon <?php echo $i <= round($product['average_rating']) ? 'filled' : ''; ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-text"><?php echo number_format($product['average_rating'], 1); ?></span>
                                <span class="rating-count">(<?php echo number_format($product['total_ratings']); ?>)</span>
                            </div>
                            <?php else: ?>
                            <div class="product-rating">
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-icon">★</span>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-count">No ratings yet</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function addToCart(event, productId) {
            event.preventDefault();
            event.stopPropagation();
            
            <?php if ($isLoggedIn): ?>
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            fetch('AddToCart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Product added to cart!');
                    updateCartCount();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
            <?php else: ?>
            if (confirm('You need to log in to add items to your cart. Go to login page?')) {
                window.location.href = 'login.php';
            }
            <?php endif; ?>
        }
        
        async function updateCartCount() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_cart_count');
                
                const response = await fetch('AddToCart.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        document.querySelectorAll('.cart-count').forEach(badge => {
                            badge.textContent = data.count;
                        });
                    }
                }
            } catch (error) {
                console.error('Error updating cart count:', error);
            }
        }
    </script>
</body>
</html>