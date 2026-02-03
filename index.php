<?php
ob_start();


require 'pages/layout_header.php';
require 'pages/ajax_handler.php';


$page = $_GET['page'] ?? 'landing';

// Router
switch ($page) {
    case 'landing':
        require 'pages/layout_html_header.php';
        require 'pages/landing.php';
        break;
    case 'login':
        require 'pages/layout_html_header.php';
        require 'pages/login.php';
        break;
    case 'register':
        require 'pages/layout_html_header.php';
        require 'pages/register.php';
        break;
    case 'forgot-password':
        require 'pages/layout_html_header.php';
        require 'pages/forgot-password.php';
        break;
    case 'verify-otp':
        require 'pages/layout_html_header.php';
        require 'pages/verify-otp.php';
        break;
    case 'reset-password':
        require 'pages/layout_html_header.php';
        require 'pages/reset-password.php';
        break;

    case 'presensi-masuk':
    case 'presensi-pulang':
        // Public access allowed for scanning (kiosk mode)
        require 'pages/layout_html_header.php';
        require 'pages/presensi.php';
        break;
    default:
        // Private Pages (Dashboard, Members, Reports, etc.)
        requireAuth(); // Ensure user is logged in
        require 'pages/layout_html_header.php';
        
        if (isAdmin()) {
            require 'pages/admin.php';
        } else {
            require 'pages/pegawai.php';
        }
        break;
}

require 'pages/layout_footer.php';
?>