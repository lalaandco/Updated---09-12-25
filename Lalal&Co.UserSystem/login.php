<?php
if (basename($_SERVER['PHP_SELF']) == 'login.php') {
    header("Location: index.php?page=login");
    exit;
}

$activeForm = $_SESSION['active_form'] ?? 'login';
$error = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$success = [
    'register' => $_SESSION['register_success'] ?? ''
];
$old = $_SESSION['old_inputs'] ?? [];

unset($_SESSION['login_error'], $_SESSION['register_error'], $_SESSION['register_success'], 
      $_SESSION['active_form'], $_SESSION['old_inputs']);

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}

function showSuccess($success) {
    return !empty($success) ? "<p class='success-message'>$success</p>" : '';
}

function isActiveForm($formName, $activeForm) {
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
    <link rel="stylesheet" href="logins.css">
    <title>Log In and Register</title>
</head>

<body> 
    <div class="overlay"></div>
    <div class="login-container">
        <!-- LOGIN FORM -->
        <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post">
                <div class="back-button">
                    <a href="index.php"><i class='bx bx-undo'></i></a>
                </div>    
                <div class="logo">
                    <img src="images/loginLogo.png" alt="logo">
                </div>
                <h2>LOG IN</h2>
                <?= showError($error['login']); ?>
                
                <input type="email" name="email" placeholder="Email" required><br>
                
                <div class="password-wrapper">
                    <input type="password" name="password" placeholder="Password" id="loginPassword" required>
                    <i class='bx bx-show toggle-icon' id="toggleLoginPassword"></i>
                </div>
                
                <input type="submit" value="Log In" name="login" style="margin-top: 20px;"><br>
                
                <h2 class="or"><span>OR</span></h2>
                <div class="social-icons">
                    <a href="#" onclick="showForm('register-form'); return false;" class="toggle-signup">SIGN UP</a>
                </div>
            </form>
        </div>

        <!-- REGISTER FORM -->
        <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
            <form action="login_register.php" method="post">
                <?= showSuccess($success['register']); ?>
                <?= showError($error['register']); ?>
                
                <div class="back-button">
                    <a href="index.php"><i class='bx bx-undo'></i></a>
                </div>  
                <h2>Register</h2>
                
                <input type="text" name="name" placeholder="Full Name" value="<?= htmlspecialchars($old['name'] ?? '') ?>" required><br>
                <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required><br>
                <input type="text" name="contact-number" placeholder="Contact Number (11 digits)" value="<?= htmlspecialchars($old['contact-number'] ?? '') ?>" maxlength="11" pattern="[0-9]{11}" required><br>
                
                <h3 style="margin: 15px 0 10px 0; color: #333; font-size: 14px;">Address Details</h3>
                
                <div class="address-grid">
                    <input type="text" name="house_number" placeholder="House/Unit Number" value="<?= htmlspecialchars($old['house_number'] ?? '') ?>" required>
                    <input type="text" name="street" placeholder="Street Name" value="<?= htmlspecialchars($old['street'] ?? '') ?>" required>
                    <input type="text" name="barangay" placeholder="Barangay" value="<?= htmlspecialchars($old['barangay'] ?? '') ?>" class="full-width" required>
                    
                    <select name="city" required>
                        <option value="">Select City</option>
                        <optgroup label="Metro Manila">
                            <option value="Manila|Metro Manila|NCR">Manila</option>
                            <option value="Quezon City|Metro Manila|NCR">Quezon City</option>
                            <option value="Caloocan|Metro Manila|NCR">Caloocan</option>
                            <option value="Las Piñas|Metro Manila|NCR">Las Piñas</option>
                            <option value="Makati|Metro Manila|NCR">Makati</option>
                            <option value="Malabon|Metro Manila|NCR">Malabon</option>
                            <option value="Mandaluyong|Metro Manila|NCR">Mandaluyong</option>
                            <option value="Marikina|Metro Manila|NCR">Marikina</option>
                            <option value="Muntinlupa|Metro Manila|NCR">Muntinlupa</option>
                            <option value="Navotas|Metro Manila|NCR">Navotas</option>
                            <option value="Parañaque|Metro Manila|NCR">Parañaque</option>
                            <option value="Pasay|Metro Manila|NCR">Pasay</option>
                            <option value="Pasig|Metro Manila|NCR">Pasig</option>
                            <option value="Pateros|Metro Manila|NCR">Pateros</option>
                            <option value="San Juan|Metro Manila|NCR">San Juan</option>
                            <option value="Taguig|Metro Manila|NCR">Taguig</option>
                            <option value="Valenzuela|Metro Manila|NCR">Valenzuela</option>
                        </optgroup>
                        <optgroup label="Region IV-A (CALABARZON)">
                            <option value="Antipolo|Rizal|Region IV-A">Antipolo, Rizal</option>
                            <option value="Bacoor|Cavite|Region IV-A">Bacoor, Cavite</option>
                            <option value="Dasmariñas|Cavite|Region IV-A">Dasmariñas, Cavite</option>
                            <option value="Imus|Cavite|Region IV-A">Imus, Cavite</option>
                            <option value="Biñan|Laguna|Region IV-A">Biñan, Laguna</option>
                            <option value="Calamba|Laguna|Region IV-A">Calamba, Laguna</option>
                            <option value="San Pedro|Laguna|Region IV-A">San Pedro, Laguna</option>
                            <option value="Santa Rosa|Laguna|Region IV-A">Santa Rosa, Laguna</option>
                        </optgroup>
                        <optgroup label="Other Major Cities">
                            <option value="Baguio|Benguet|CAR">Baguio City</option>
                            <option value="Cebu City|Cebu|Region VII">Cebu City</option>
                            <option value="Davao City|Davao del Sur|Region XI">Davao City</option>
                            <option value="Cagayan de Oro|Misamis Oriental|Region X">Cagayan de Oro</option>
                            <option value="Iloilo City|Iloilo|Region VI">Iloilo City</option>
                            <option value="Bacolod|Negros Occidental|Region VI">Bacolod</option>
                        </optgroup>
                    </select>
                    
                    <input type="text" name="postal_code" placeholder="Postal Code" value="<?= htmlspecialchars($old['postal_code'] ?? '') ?>" maxlength="4" pattern="[0-9]{4}" required>
                </div>
                
                <div class="password-wrapper">
                    <input type="password" name="password" placeholder="Password (min. 8 characters)" id="registerPassword" required>
                    <i class='bx bx-show toggle-icon' id="toggleRegisterPassword"></i>
                </div>
                
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirmPassword" required>
                    <i class='bx bx-show toggle-icon' id="toggleConfirmPassword"></i>
                </div>
                
                <input type="submit" value="Register" name="register"><br>

                <h2 class="or"><span>OR</span></h2>
                <div class="social-icons">
                    <a href="#" onclick="showForm('login-form'); return false;" class="toggle-login">BACK TO LOGIN</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/hide forms
        function showForm(formId) {
            document.querySelectorAll('.form-box').forEach(form => {
                form.classList.remove('active');
            });
            document.getElementById(formId).classList.add('active');
        }

        // Password visibility toggles
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            
            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    if (input.type === 'password') {
                        input.type = 'text';
                        this.classList.remove('bx-show');
                        this.classList.add('bx-hide');
                    } else {
                        input.type = 'password';
                        this.classList.remove('bx-hide');
                        this.classList.add('bx-show');
                    }
                });
            }
        }

        // Initialize all password toggles
        setupPasswordToggle('toggleLoginPassword', 'loginPassword');
        setupPasswordToggle('toggleRegisterPassword', 'registerPassword');
        setupPasswordToggle('toggleConfirmPassword', 'confirmPassword');
    </script>
</body>
</html>