<?php

    
    if (basename($_SERVER['PHP_SELF']) == 'login.php') {
        header("Location: index.php?page=login");
        exit;
    }

    
    $activeForm = $_SESSION['active_form'] ?? 'register';
    $activeForm = $_SESSION['active_form'] ?? 'login';

    // read session-provided messages / old inputs
    $error = [
        'login' => $_SESSION['login_error'] ?? '',
        'register' => $_SESSION['register_error'] ?? ''
    ];

    $success = [
        'register' => $_SESSION['register_success'] ?? ''
    ];

    

    // grab old inputs and clear-password flag
    $old = $_SESSION['old_inputs'] ?? [];
    $clearPassword = $_SESSION['clear_password'] ?? true;

    // only remove the session keys we used (so we don't unset unrelated session data)
    unset(
        $_SESSION['login_error'],
        $_SESSION['register_error'],
        $_SESSION['register_success'],
        $_SESSION['active_form'],
        $_SESSION['old_inputs'],
        $_SESSION['clear_password']
    );

    function showError($error){
        return !empty($error) ? "<p class='error-message'>$error</p>" : '';
    }

    function showSuccess($success) {
    return !empty($success) ? "<p class='success-message'>$success</p>" : '';
    }

    function isActiveForm($formName,$activeForm){
        return $formName === $activeForm ? 'active' : '';

    }

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" href="images/4.png" type="image/png">
    <link rel="stylesheet" href="login.css">
    <title>Log In and Register</title>
</head>

<body> 
    <div class="overlay">
    </div>
    <div class="login-container">
        <div class="form-box <?= isActiveForm('login',$activeForm); ?>" id="login-form" >
            <form action="login_register.php" method="post">
                <div class="back-button">
                    <a href="index.php">
                        <i class='bx bx-undo'></i>
                    </a>
                </div>    
                <div class="logo">
                    <img src="images/loginLogo.png" alt="logo">
                </div>
                    <h2>LOG IN</h2>
                    <?= showError($error['login']); ?>
                    <input type="email" name="email" placeholder="Email"><br>
                    <input type="password" name="password" placeholder="Password"><br>
                    <input type="submit" value="Log In" name="login"><br>
                    <h2 class="or"><span>OR</span></h2>
                    <div class="social-icons">
                        <a href="#" onclick="showForm('register-form')" class="toggle-signup">SIGN UP</a>
                        <a href="#"><i class='bx bxl-google'></i> LOG IN WITH GOOGLE</a>
                        <a href="#"><i class='bx bxl-facebook-circle' ></i> LOG IN WITH FACEBOOK</a>
                    </div>
                    <a href=""></a>
            </form>
        </div>
        <div class="form-box <?= isActiveForm('register',$activeForm); ?>" id="register-form" >
            <form action="login_register.php" method="post">
                <?= showSuccess($success['register']); ?>
                <?= showError($error['register']); ?>
                <div class="back-button">
                    <a href="index.php">
                        <i class='bx bx-undo'></i>
                    </a>
                </div>  
                <h2>Register</h2>
                <input type="text" name="name" placeholder="Name" value="<?= htmlspecialchars($old['name'] ?? '') ?>"><br>
                <input type="text" name="address" placeholder="Address" value="<?= htmlspecialchars($old['address'] ?? '') ?>"><br>
                <input type="text" name="contact-number" placeholder="Contact Number" value="<?= htmlspecialchars($old['contact-number'] ?? '') ?>"><br>    
                <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($old['email'] ?? '') ?>"><br>
                <input type="password" name="password" placeholder="Password" <?= ($clearPassword ? '' : '') ?> ><br>
                <input type="submit" value="Register" name="register"><br>

                <h2 class="or"><span>OR</span></h2>
                <div class="social-icons">
                    <a href="#" onclick="showForm('login-form')" class="toggle-login">BACK TO LOGIN</a>
                    <a href="#"><i class='bx bxl-google'></i> SIGN UP WITH GOOGLE</a>
                    <a href="#"><i class='bx bxl-facebook-circle' ></i> SIGN UP WITH FACEBOOK</a>
                </div>
            </form>
        </div>
    </div>      
    <script src="js/script.js"></script>
</body>
</html>


