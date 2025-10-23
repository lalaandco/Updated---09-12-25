<?php
session_start();
require_once 'config.php';

// REGISTRATION HANDLER
if (isset($_POST['register'])){ 
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $contact_number = trim($_POST["contact-number"]);
    
    // Address fields
    $house_number = trim($_POST["house_number"]);
    $street = trim($_POST["street"]);
    $barangay = trim($_POST["barangay"]);
    $city_data = $_POST["city"];
    $postal_code = trim($_POST["postal_code"]);
    
    $password_raw = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Parse city data
    $city_parts = explode('|', $city_data);
    $city = $city_parts[0] ?? '';
    $province = $city_parts[1] ?? '';
    
    $full_address = trim("$house_number $street, $barangay, $city, $province $postal_code");

    // Store inputs for repopulation
    $_SESSION['old_inputs'] = [
        'name' => $name,
        'email' => $email,
        'contact-number' => $contact_number,
        'house_number' => $house_number,
        'street' => $street,
        'barangay' => $barangay,
        'postal_code' => $postal_code
    ];

    // Validations
    if (empty($name) || empty($email) || empty($contact_number) || empty($house_number) || 
        empty($street) || empty($barangay) || empty($city) || empty($postal_code) || 
        empty($password_raw) || empty($confirm_password)) {
        $_SESSION['register_error'] = "Please complete all fields";
        $_SESSION['active_form'] = "register"; 
        header("Location: index.php?page=login");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format";
        $_SESSION['active_form'] = "register";
        header("Location: index.php?page=login");
        exit();
    }

    if (!preg_match('/^[0-9]{11}$/', $contact_number)) {
        $_SESSION['register_error'] = "Contact number must be exactly 11 digits";
        $_SESSION['active_form'] = "register"; 
        header("Location: index.php?page=login");
        exit();
    }

    if (!preg_match('/^[0-9]{4}$/', $postal_code)) {
        $_SESSION['register_error'] = "Postal code must be exactly 4 digits";
        $_SESSION['active_form'] = "register";
        header("Location: index.php?page=login");
        exit();
    }

    if (strlen($password_raw) < 8) {
        $_SESSION['register_error'] = "Password must be at least 8 characters long";
        $_SESSION['active_form'] = "register"; 
        header("Location: index.php?page=login");
        exit();
    }

    if (!preg_match('/[^A-Za-z]/', $password_raw)) {
        $_SESSION['register_error'] = "Password must contain at least one number or symbol";
        $_SESSION['active_form'] = "register"; 
        header("Location: index.php?page=login");
        exit();
    }

    if ($password_raw !== $confirm_password) {
        $_SESSION['register_error'] = "Passwords do not match";
        $_SESSION['active_form'] = "register";
        header("Location: index.php?page=login");
        exit();
    }

    // Check if email exists
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $result = $checkEmail->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['register_error'] = "Email is already registered!";
        $_SESSION['active_form'] = "register"; 
        header("Location: index.php?page=login");
        exit();
    }
    $checkEmail->close();
    
    // Hash password
    $password = password_hash($password_raw, PASSWORD_DEFAULT);
    
    // Insert user (NO EMAIL VERIFICATION)
    $insert = $conn->prepare("
        INSERT INTO users (
            name, email, password, address,
            house_number, street, barangay, city, province, postal_code,
            contact_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$insert) {
        $_SESSION['register_error'] = "Database error: " . $conn->error;
        $_SESSION['active_form'] = "register";
        header("Location: index.php?page=login");
        exit();
    }
    
    $insert->bind_param(
        "sssssssssss", 
        $name, $email, $password, $full_address,
        $house_number, $street, $barangay, $city, $province, $postal_code,
        $contact_number
    );
    
    if ($insert->execute()) {
        $_SESSION['register_success'] = "Registration successful! You can now log in.";
        $_SESSION['active_form'] = "login"; 
        unset($_SESSION['old_inputs']);
        header("Location: index.php?page=login");
        exit();
    } else {
        $_SESSION['register_error'] = "Registration failed: " . $conn->error;
        $_SESSION['active_form'] = "register";
        header("Location: index.php?page=login");
        exit();
    }
    $insert->close();
}

// LOGIN HANDLER
if (isset($_POST["login"])){
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both email and password.";
        $_SESSION['active_form'] = "login"; 
        header("Location: index.php?page=login");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Login successful - NO EMAIL VERIFICATION CHECK
            $_SESSION['name'] = $user["name"];
            $_SESSION['email'] = $user["email"];
            $_SESSION['contact-number'] = $user["contact_number"];
            
            $full_address = trim(
                $user["house_number"] . " " . 
                $user["street"] . ", " . 
                $user["barangay"] . ", " . 
                $user["city"] . ", " . 
                $user["province"] . " " . 
                $user["postal_code"]
            );
            $_SESSION["address"] = $full_address;
            
            $_SESSION["house_number"] = $user["house_number"];
            $_SESSION["street"] = $user["street"];
            $_SESSION["barangay"] = $user["barangay"];
            $_SESSION["city"] = $user["city"];
            $_SESSION["province"] = $user["province"];
            $_SESSION["postal_code"] = $user["postal_code"];

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['active_form'] = "login"; 
            $_SESSION['login_error'] = "Incorrect password.";
            header("Location: index.php?page=login");
            exit();
        }    
    }
    
    $_SESSION['active_form'] = "login"; 
    $_SESSION['login_error'] = "Email not found.";
    header("Location: index.php?page=login");
    exit();
    $stmt->close();
}

$conn->close();
?>