<?php
// ============================================================
//  Login Page — login.php
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header('Location: ' . APP_URL . '/' . $role . '/pages/dashboard.php');
    exit;
}

$error   = '';
$success = '';
$flash   = getFlash();

// Pre-fill role from query string (from landing page portal cards)
$preRole = in_array($_GET['role'] ?? '', ['patient','herbalist','admin'])
           ? $_GET['role']
           : 'patient';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = sanitize($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } else {
            $db   = Database::connect();
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect email or password. Please try again.';
            } elseif ($user['role'] === 'herbalist' && !$user['is_approved']) {
                $error = 'Your herbalist account is pending admin approval. Please check back soon.';
            } else {
                login($user);
                logAction('LOGIN', 'users', $user['id'], 'User logged in successfully');
                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                header('Location: ' . APP_URL . '/' . $user['role'] . '/pages/dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Herbal Remedy System</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="auth-page">

  <!-- LEFT PANEL -->
  <div class="auth-left">
    <div class="auth-left-content">
      <a href="<?= APP_URL ?>" style="display:flex;align-items:center;gap:0.5rem;color:var(--green-light);text-decoration:none;font-weight:600;font-size:0.9rem;margin-bottom:2.5rem;">
        <i class="fa-solid fa-arrow-left"></i> Back to Home
      </a>
      <h1>Welcome <span>Back</span></h1>
      <p>Sign in to access your personalised herbal healthcare dashboard.</p>

      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="auth-feature-text">
          <h5>Secure & Private</h5>
          <p>Your health data is encrypted and never shared without consent.</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-brain"></i></div>
        <div class="auth-feature-text">
          <h5>AI-Powered Recommendations</h5>
          <p>Get intelligent herbal remedy suggestions tailored to your symptoms.</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-leaf"></i></div>
        <div class="auth-feature-text">
          <h5>Cameroonian Plant Database</h5>
          <p>Curated database of medicinal plants found across Cameroon.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right">
    <div class="auth-box animate-fadeInUp">
      <div class="auth-box-header">
        <div class="logo-icon">🌿</div>
        <h2>Sign In</h2>
        <p>Enter your credentials to continue</p>
      </div>

      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
          <i class="fa-solid fa-circle-info"></i>
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i class="fa-solid fa-circle-exclamation"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <div class="auth-form">
        <div class="card">
          <div class="card-body">
            <form method="POST" action="login.php" id="loginForm">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

              <!-- Role tabs -->
              <div class="role-selector" style="grid-template-columns:1fr 1fr 1fr;margin-bottom:1.5rem;">
                <div class="role-option">
                  <input type="radio" name="login_role_display" id="role_patient"
                         value="patient" <?= $preRole==='patient' ? 'checked' : '' ?>>
                  <label for="role_patient">
                    <span class="role-icon">🌿</span> Patient
                  </label>
                </div>
                <div class="role-option">
                  <input type="radio" name="login_role_display" id="role_herbalist"
                         value="herbalist" <?= $preRole==='herbalist' ? 'checked' : '' ?>>
                  <label for="role_herbalist">
                    <span class="role-icon">🌱</span> Herbalist
                  </label>
                </div>
                <div class="role-option">
                  <input type="radio" name="login_role_display" id="role_admin"
                         value="admin" <?= $preRole==='admin' ? 'checked' : '' ?>>
                  <label for="role_admin">
                    <span class="role-icon">⚙️</span> Admin
                  </label>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-envelope input-icon"></i>
                  <input type="email" id="email" name="email" class="form-control"
                         placeholder="your@email.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                         required autofocus>
                </div>
              </div>

              <div class="form-group">
                <div class="d-flex justify-between align-center mb-1">
                  <label class="form-label" for="password" style="margin:0;">Password</label>
                </div>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-lock input-icon"></i>
                  <input type="password" id="password" name="password"
                         class="form-control" placeholder="••••••••" required>
                  <button type="button" class="toggle-password" onclick="togglePwd('password', this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="auth-divider">Don't have an account?</div>
      <a href="register.php" class="btn btn-outline btn-full">
        <i class="fa-solid fa-user-plus"></i> Create Account
      </a>

      <p style="text-align:center;margin-top:1rem;font-size:0.78rem;color:var(--text-light);">
        <i class="fa-solid fa-shield-halved"></i>
        Protected with CSRF and bcrypt password hashing
      </p>
    </div>
  </div>
</div>

<script>
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
</body>
</html>
