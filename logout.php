<?php
// ============================================================
//  Logout Handler — logout.php
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    logAction('LOGOUT', 'users', $_SESSION['user_id'], 'User logged out');
    setFlash('success', 'You have been signed out successfully.');
}

logout(); // destroys session and redirects to index.php
