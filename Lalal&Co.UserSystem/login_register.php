<?php
    session_start();
    require_once 'config.php';

    if (isset($_POST['register'])){ 
        $name = $_POST["name"];
        $address = $_POST["address"];
        $contact_number = $_POST["contact-number"];
        $email = $_POST["email"];
        $password_raw = $_POST["password"];

        // preserve non-password inputs so they can be repopulated on error
        $_SESSION['old_inputs'] = [
            'name' => $name,
            'address' => $address,
            'contact-number' => $contact_number,
            'email' => $email
        ];

        // default: clear password on error (safer). We'll set to false only when we
        // want to keep the password input (not storing the raw password, just a flag).
        $_SESSION['clear_password'] = true;

        // require at least one non-letter character (number or symbol)
        if (!preg_match('/[^A-Za-z]/', $password_raw)) {
            $_SESSION['register_error'] = "Password must contain at least a number or symbol.";
            $_SESSION['active_form'] = "register"; 
            // leave clear_password = true (clear password input)
            header("Location: index.php?page=login");
            exit();
        }

        if (!preg_match('/^[0-9]{11}$/', $contact_number)) {
            $_SESSION['register_error'] = "Contact number must be exactly 11 digits and contain only numbers.";
            $_SESSION['active_form'] = "register"; 
            header("Location: index.php?page=login");
            exit();
        }

        if (strlen($password_raw) < 8){
            $_SESSION['register_error'] = "Password must be at least 8 characters long.";
            $_SESSION['active_form'] = "register"; 
            // leave clear_password = true (clear password input)
            header("Location: index.php?page=login");
            exit();
        }

        // If password passed validation, we keep other inputs and do NOT force clearing password flag.
        $_SESSION['clear_password'] = false;

        if (empty($name) || empty($address) || empty($contact_number) || empty($email) || empty($password_raw)) {
            $_SESSION['register_error'] = "Please Complete The Credentials";
            $_SESSION['active_form'] = "register"; 
            // don't change clear_password (it will be false here because password passed validations above)
            header("Location: index.php?page=login");
            exit();
        }
        
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        $checkEmail = $conn->query("SELECT email FROM users WHERE email = '$email'");
        if ($checkEmail->num_rows > 0) {
            $_SESSION['register_error'] = "Email is already registered!";
            $_SESSION['active_form'] = "register"; 
            // keep clear_password = false so other inputs stay filled (password not stored)
        } else {
            $insert = $conn->query("INSERT INTO users (name,address,contact_number,email,password) VALUES ('$name','$address','$contact_number','$email','$password')");
            if ($insert) {
                $_SESSION['register_success'] = "Registered Successfully. Please log in.";
                $_SESSION['active_form'] = "register"; 
                // clear saved old inputs on success
                unset($_SESSION['old_inputs'], $_SESSION['clear_password']);
            }
        }
        header("Location: index.php?page=login");
        exit();
    }

    if (isset($_POST["login"])){
        $email = $_POST["email"];
        $password = $_POST["password"];

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = "Please enter both email and password.";
            $_SESSION['active_form'] = "login"; 
            header("Location: index.php?page=login");
            exit();
        }

        $result = $conn->query("SELECT * FROM users WHERE email = '$email'");

        if($result->num_rows > 0){
            $user = $result->fetch_assoc();
            if(password_verify($password,$user['password'])){
                $_SESSION['name'] = $user["name"];
                $_SESSION['email'] = $user["email"];
                $_SESSION["address"] = $user["address"];
                $_SESSION["contact-number"] = $user["contact_number"];

                // Redirect to main index page instead of user_login.php
                header("Location: index.php");
                exit();
            }
            else {
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
    }

?>