<?php
// ============================================================
//  Global Helper Functions
//  File: includes/helpers.php
// ============================================================

require_once __DIR__ . '/../config/database.php';

// ── Auth helpers ──────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $db   = Database::connect();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(string $redirect = '/herbal_system/index.php'): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php?error=Please+log+in+first');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ' . APP_URL . '/index.php?error=Access+denied');
        exit;
    }
}

function login(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

// ── Security helpers ──────────────────────────────────────────

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function generateToken(int $length = 64): string {
    return bin2hex(random_bytes($length / 2));
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// ── Flash message helpers ─────────────────────────────────────

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void {
    $flash = getFlash();
    if (!$flash) return;
    $type    = $flash['type'];   // success | danger | warning | info
    $message = $flash['message'];
    echo <<<HTML
    <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
        {$message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    HTML;
}

// ── Audit log helper ──────────────────────────────────────────

function logAction(
    string  $action,
    string  $targetTable = '',
    int     $targetId    = 0,
    string  $description = ''
): void {
    try {
        $db   = Database::connect();
        $stmt = $db->prepare('
            INSERT INTO audit_log
                (user_id, action, target_table, target_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $targetTable ?: null,
            $targetId    ?: null,
            $description ?: null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Fail silently — logging should never break normal flow
    }
}

// ── File upload helper ────────────────────────────────────────

function uploadImage(array $file, string $destDir): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size']  >  MAX_FILE_SIZE)  return false;
    if (!in_array($file['type'], ALLOWED_TYPES)) return false;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $destPath = rtrim($destDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) return false;
    return $filename;
}

// ── Pagination helper ─────────────────────────────────────────

function paginate(int $total, int $perPage, int $current): array {
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $current,
        'total_pages' => $totalPages,
        'offset'      => ($current - 1) * $perPage,
        'has_prev'    => $current > 1,
        'has_next'    => $current < $totalPages,
    ];
}

// ── Timeago helper ────────────────────────────────────────────

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    return match(true) {
        $diff < 60     => 'just now',
        $diff < 3600   => (int)($diff / 60)   . ' minutes ago',
        $diff < 86400  => (int)($diff / 3600)  . ' hours ago',
        $diff < 604800 => (int)($diff / 86400) . ' days ago',
        default        => date('d M Y', strtotime($datetime)),
    };
}
