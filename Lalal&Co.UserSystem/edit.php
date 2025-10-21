<?php
session_start();

if (basename($_SERVER['PHP_SELF']) == 'edit.php') {
    header("Location: index.php?page=editProfile");
    exit;
}

if (!isset($_SESSION['email'])) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=users_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $house_number = trim($_POST['house_number']);
    $street = trim($_POST['street']);
    $barangay = trim($_POST['barangay']);
    $city_data = $_POST['city']; // Format: "CityName|Province"
    $postal_code = trim($_POST['postal_code']);
    $contact = trim($_POST['contact']);
    
    // Parse city data
    $city_parts = explode('|', $city_data);
    $city = $city_parts[0] ?? '';
    $province = $city_parts[1] ?? '';
    
    // Basic validation
    if (empty($name) || empty($house_number) || empty($street) || empty($barangay) || 
        empty($city) || empty($postal_code) || empty($contact)) {
        $error = "All fields are required";
    } 
    // Validate contact number
    elseif (!preg_match('/^[0-9]{11}$/', $contact)) {
        $error = "Contact number must be exactly 11 digits";
    }
    // Validate postal code
    elseif (!preg_match('/^[0-9]{4}$/', $postal_code)) {
        $error = "Postal code must be exactly 4 digits";
    }
    else {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = ?, 
                    house_number = ?, 
                    street = ?, 
                    barangay = ?, 
                    city = ?, 
                    province = ?,
                    postal_code = ?, 
                    contact_number = ? 
                WHERE email = ?
            ");
            
            if ($stmt->execute([$name, $house_number, $street, $barangay, $city, $province, $postal_code, $contact, $_SESSION['email']])) {
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['contact-number'] = $contact;
                
                // Build full address
                $full_address = trim(
                    $house_number . " " . 
                    $street . ", " . 
                    $barangay . ", " . 
                    $city . ", " . 
                    $province . " " . 
                    $postal_code
                );
                $_SESSION["address"] = $full_address;
                
                // Store individual components
                $_SESSION["house_number"] = $house_number;
                $_SESSION["street"] = $street;
                $_SESSION["barangay"] = $barangay;
                $_SESSION["city"] = $city;
                $_SESSION["province"] = $province;
                $_SESSION["postal_code"] = $postal_code;
                
                $success = "Profile updated successfully!";
            }
        } catch(PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$_SESSION['email']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$name = $user['name'] ?? '';
$house_number = $user['house_number'] ?? '';
$street = $user['street'] ?? '';
$barangay = $user['barangay'] ?? '';
$city = $user['city'] ?? '';
$province = $user['province'] ?? '';
$postal_code = $user['postal_code'] ?? '';
$contact = $user['contact_number'] ?? '';
$email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Lalal & Co.</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
        }

        .edit-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }

        .edit-container {
            position: relative;
            background: #d4c4b0;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        h1 {
            color: #524747;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 8px;
        }

        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #524747;
            font-weight: 600;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0d5c9;
            border-radius: 8px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #7d6e5d;
            box-shadow: 0 0 5px rgba(125, 110, 93, 0.3);
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }

        button, .back-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
        }

        button[type="submit"] {
            background: #7d6e5d;
            color: white;
        }

        button[type="submit"]:hover {
            background: #6a5d4d;
            transform: translateY(-2px);
        }

        .back-btn {
            background: #e0d5c9;
            color: #524747;
        }

        .back-btn:hover {
            background: #d4c4b0;
            transform: translateY(-2px);
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success {
            background: rgba(46, 204, 113, 0.2);
            color: #27ae60;
        }

        .error {
            background: rgba(231, 76, 60, 0.2);
            color: #c0392b;
        }

        .email-field {
            background: rgba(255, 255, 255, 0.5);
            cursor: not-allowed;
        }

        h3 {
            color: #524747;
            margin: 20px 0 10px 0;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="edit-overlay">
        <div class="edit-container">
            <h1>Edit Profile</h1>
            
            <?php if (isset($error)): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php?page=editProfile">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email (Cannot be changed)</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($email) ?>" class="email-field" disabled>
                </div>

                <div class="form-group">
                    <label for="contact">Contact Number (11 digits)</label>
                    <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($contact) ?>" maxlength="11" pattern="[0-9]{11}" required>
                </div>

                <h3>Address Details</h3>
                <div class="form-group">
                    <div class="address-grid">
                        <div>
                            <label for="house_number">House/Unit Number</label>
                            <input type="text" id="house_number" name="house_number" value="<?= htmlspecialchars($house_number) ?>" required>
                        </div>
                        
                        <div>
                            <label for="street">Street Name</label>
                            <input type="text" id="street" name="street" value="<?= htmlspecialchars($street) ?>" required>
                        </div>

                        <div class="full-width">
                            <label for="barangay">Barangay</label>
                            <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($barangay) ?>" required>
                        </div>

                        <div>
                            <label for="city">City</label>
                            <select name="city" id="city" required>
                                <option value="">Select City</option>
                                <optgroup label="Metro Manila">
                                    <?php
                                    $metro_cities = [
                                        'Manila', 'Quezon City', 'Caloocan', 'Las Piñas', 'Makati',
                                        'Malabon', 'Mandaluyong', 'Marikina', 'Muntinlupa', 'Navotas',
                                        'Parañaque', 'Pasay', 'Pasig', 'Pateros', 'San Juan', 'Taguig', 'Valenzuela'
                                    ];
                                    foreach ($metro_cities as $mc) {
                                        $value = "$mc|Metro Manila";
                                        $selected = ($city == $mc && $province == 'Metro Manila') ? 'selected' : '';
                                        echo "<option value='$value' $selected>$mc</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Region IV-A (CALABARZON)">
                                    <?php
                                    $calabarzon_cities = [
                                        'Antipolo|Rizal', 'Bacoor|Cavite', 'Dasmariñas|Cavite', 'Imus|Cavite',
                                        'Biñan|Laguna', 'Calamba|Laguna', 'San Pedro|Laguna', 'Santa Rosa|Laguna'
                                    ];
                                    foreach ($calabarzon_cities as $cc) {
                                        list($c, $p) = explode('|', $cc);
                                        $selected = ($city == $c && $province == $p) ? 'selected' : '';
                                        echo "<option value='$cc' $selected>$c, $p</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="Other Major Cities">
                                    <?php
                                    $other_cities = [
                                        'Baguio|Benguet', 'Cebu City|Cebu', 'Davao City|Davao del Sur',
                                        'Cagayan de Oro|Misamis Oriental', 'Iloilo City|Iloilo', 'Bacolod|Negros Occidental'
                                    ];
                                    foreach ($other_cities as $oc) {
                                        list($c, $p) = explode('|', $oc);
                                        $selected = ($city == $c && $province == $p) ? 'selected' : '';
                                        echo "<option value='$oc' $selected>$c, $p</option>";
                                    }
                                    ?>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label for="postal_code">Postal Code (4 digits)</label>
                            <input type="text" id="postal_code" name="postal_code" value="<?= htmlspecialchars($postal_code) ?>" maxlength="4" pattern="[0-9]{4}" required>
                        </div>
                    </div>
                </div>

                <div class="buttons">
                    <a href="index.php" class="back-btn">Back</a>
                    <button type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>