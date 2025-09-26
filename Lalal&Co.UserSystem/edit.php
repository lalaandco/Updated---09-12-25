<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: index.php');
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=users_db", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $contact = trim($_POST['contact']);
    
    // Basic validation
    if (empty($name) || empty($address) || empty($contact)) {
        $error = "All fields are required";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, address = ?, contact_number = ? WHERE email = ?");
            if ($stmt->execute([$name, $address, $contact, $_SESSION['email']])) {
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['address'] = $address;
                $_SESSION['contact-number'] = $contact;
                $success = "Profile updated successfully!";
            }
        } catch(PDOException $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    }
}

// Get current user data
$name = $_SESSION['name'] ?? '';
$address = $_SESSION['address'] ?? '';
$contact = $_SESSION['contact-number'] ?? '';
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f0ea;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .edit-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        h1 {
            color: #7d6e5d;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #7d6e5d;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d4c4b0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        input:focus {
            outline: none;
            border-color: #7d6e5d;
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
        }

        button[type="submit"] {
            background: #7d6e5d;
            color: white;
        }

        button[type="submit"]:hover {
            background: #6a5d4d;
        }

        .back-btn {
            background: #d4c4b0;
            color: #7d6e5d;
        }

        .back-btn:hover {
            background: #c4b4a0;
        }

        .message {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
        }

        .email-field {
            background: #f5f0ea;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="edit-container">
        <h1>Edit Profile</h1>
        
        <?php if (isset($error)): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit.php">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= htmlspecialchars($address) ?>" required>
            </div>

            <div class="form-group">
                <label for="contact">Contact Number</label>
                <input type="text" id="contact" name="contact" value="<?= htmlspecialchars($contact) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" value="<?= htmlspecialchars($email) ?>" class="email-field" disabled>
            </div>

            <div class="buttons">
                <a href="index.php" class="back-btn">Back</a>
                <button type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>