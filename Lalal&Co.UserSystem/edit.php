<?php
session_start(); // Add this at the very top

if (basename($_SERVER['PHP_SELF']) == 'edit.php') {
    header("Location: index.php?page=editProfile");
    exit;
}

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
            height: 790px;
            margin-top: 120px;
            margin-left: 1450px;
            overflow-y: auto;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
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

        label {
            display: block;
            margin-bottom: 8px;
            color: #524747;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e0d5c9;
            border-radius: 8px;
            font-size: 16px;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        input:focus {
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
                <form method="POST" action="header.php?page=editProfile">
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
            </form>
        </div>
    </div>
</body>
</html>