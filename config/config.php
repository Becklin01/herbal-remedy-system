<?php
// ============================================================
//  HERBAL REMEDY SYSTEM — Global Configuration
//  Author : BECKLIN SAMUEL (ICTU20223544)
//  File   : config/config.php
// ============================================================

// ── Error reporting (set to 0 on production) ─────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ── Database credentials ──────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'herbal_system');
define('DB_USER',     'root');
define('DB_PASS',     '');          // Default XAMPP password is empty
define('DB_CHARSET',  'utf8mb4');

// ── Application settings ─────────────────────────────────────
define('APP_NAME',    'Herbal Remedy System');
define('APP_URL',     'http://localhost/herbal_system');
define('APP_VERSION', '1.0.0');

// ── Gemini API ────────────────────────────────────────────────
// Get your free key at: https://aistudio.google.com
define('GEMINI_API_KEY',  'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_API_URL',  'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent');

// ── Python microservice (plant detection) ─────────────────────
define('PYTHON_API_URL',  'http://localhost:5000/predict');

// ── File upload settings ─────────────────────────────────────
define('UPLOAD_DIR',      __DIR__ . '/../assets/images/uploads/');
define('PLANT_IMG_DIR',   __DIR__ . '/../assets/images/plants/');
define('MAX_FILE_SIZE',   5 * 1024 * 1024);   // 5 MB
define('ALLOWED_TYPES',   ['image/jpeg', 'image/png', 'image/webp']);

// ── Session settings ──────────────────────────────────────────
define('SESSION_LIFETIME', 3600);   // 1 hour in seconds

// ── Plant detection threshold ─────────────────────────────────
define('CONFIDENCE_THRESHOLD', 70.0);  // Minimum % to accept a prediction

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set('Africa/Douala');

// ── Start session if not already started ─────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
