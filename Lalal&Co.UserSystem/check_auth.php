// check_auth.php - to be included at the top of pages requiring admin authentication (Admin Dashboard, Edit Products, Orders, etc.)

<?php
// Include this at the top of any page that requires authentication
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}
?>