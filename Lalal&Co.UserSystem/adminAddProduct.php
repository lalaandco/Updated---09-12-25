<?php session_start(); ?>
<?php include 'admin_header.php'; ?>
<?php

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $_POST['product_name'];
    $product_description = $_POST['product_description'];
    $product_price = $_POST['product_price'];
    $product_quantity = $_POST['product_quantity'];
    $category = $_POST['category'];
    
    // Handle image upload
    $image_path = 'images/1.png'; // Default image
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['product_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'images/' . $new_filename;
            
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                $image_path = $upload_path;
            }
        }
    }
    
    // FIXED: Set both inventory_quantity and display_quantity
    $stmt = $conn->prepare("INSERT INTO product_tbl (product_name, product_description, product_price, inventory_quantity, display_quantity, category, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdiiss", $product_name, $product_description, $product_price, $product_quantity, $product_quantity, $category, $image_path);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Product added successfully!";
        header('Location: adminEditProducts.php');
        exit();
    } else {
        $error_message = "Error adding product: " . $conn->error;
    }
    $stmt->close();
}

renderAdminHeader()
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="adminAddStyle.css">
    <title>Add New Product</title>
</head>
<body>
    <div class="container">
        <h1><i class='bx bx-plus-circle'></i> Add New Product</h1>

        <?php if (isset($error_message)): ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="product_name" required placeholder="Enter product name">
            </div>

            <div class="form-group">
                <label>Product Description *</label>
                <textarea name="product_description" required placeholder="Enter product description, notes, ingredients, etc."></textarea>
            </div>

            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option value="ForHim">For Him</option>
                    <option value="ForHer">For Her</option>
                    <option value="Others">Others</option>
                </select>
            </div>

            <div class="form-group">
                <label>Price (₱) *</label>
                <input type="number" name="product_price" step="0.01" required placeholder="0.00">
            </div>

            <div class="form-group">
                <label>Initial Quantity *</label>
                <input type="number" name="product_quantity" required placeholder="0" min="0">
            </div>

            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="product_image" accept="image/*" onchange="previewImage(event)">
                <img id="imagePreview" class="image-preview" alt="Preview">
                <small style="color: #666;">Accepted formats: JPG, PNG, GIF</small>
            </div>

            <button type="submit" class="btn-submit">
                <i class='bx bx-check-circle'></i> Add Product
            </button>
        </form>
    </div>

    <script>
        function previewImage(event) {
            const preview = document.getElementById('imagePreview');
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>