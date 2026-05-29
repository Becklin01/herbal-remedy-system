<?php
// ============================================================
//  Registration Page — register.php
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/' . $_SESSION['user_role'] . '/pages/dashboard.php');
    exit;
}

$errors  = [];
$success = '';
$old     = []; // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $old = [
            'full_name' => sanitize($_POST['full_name'] ?? ''),
            'email'     => sanitize($_POST['email']     ?? ''),
            'phone'     => sanitize($_POST['phone']     ?? ''),
            'role'      => sanitize($_POST['role']      ?? 'patient'),
        ];
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        // Validation
        if (empty($old['full_name']))      $errors[] = 'Full name is required.';
        if (strlen($old['full_name']) < 3) $errors[] = 'Full name must be at least 3 characters.';
        if (empty($old['email']))          $errors[] = 'Email address is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (empty($password))              $errors[] = 'Password is required.';
        if (strlen($password) < 8)         $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2)      $errors[] = 'Passwords do not match.';
        if (!in_array($old['role'], ['patient','herbalist'])) $errors[] = 'Invalid role selected.';

        // Check duplicate email
        if (empty($errors)) {
            $db   = Database::connect();
            $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'This email address is already registered. Please sign in instead.';
            }
        }

        // Create account
        if (empty($errors)) {
            $db   = Database::connect();
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // Herbalists need approval; patients are immediately active
            $isApproved = ($old['role'] === 'patient') ? 1 : 0;

            $stmt = $db->prepare('
                INSERT INTO users (full_name, email, password_hash, role, phone, is_active, is_approved)
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ');
            $stmt->execute([
                $old['full_name'],
                $old['email'],
                $hash,
                $old['role'],
                $old['phone'] ?: null,
                $isApproved
            ]);
            $newId = $db->lastInsertId();

            // If herbalist, create empty profile record
            if ($old['role'] === 'herbalist') {
                $db->prepare('INSERT INTO herbalist_profiles (user_id) VALUES (?)')
                   ->execute([$newId]);
            }

            logAction('REGISTER', 'users', $newId, 'New ' . $old['role'] . ' registered');

            if ($old['role'] === 'herbalist') {
                setFlash('info', 'Registration successful! Your herbalist account is pending admin approval. You will be notified once approved.');
                header('Location: ' . APP_URL . '/login.php');
            } else {
                // Auto-login patient
                $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$newId]);
                $user = $stmt->fetch();
                login($user);
                setFlash('success', 'Welcome to the Herbal Remedy System, ' . $user['full_name'] . '! 🌿');
                header('Location: ' . APP_URL . '/patient/pages/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — Herbal Remedy System</title>
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
      <h1>Join Our <span>Community</span></h1>
      <p>Create your free account and start exploring the power of herbal medicine guided by AI.</p>

      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-user-injured"></i></div>
        <div class="auth-feature-text">
          <h5>Register as Patient</h5>
          <p>Get symptom-based remedy recommendations and book herbalist consultations.</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-seedling"></i></div>
        <div class="auth-feature-text">
          <h5>Register as Herbalist</h5>
          <p>Build your professional profile, manage appointments and reach more patients.</p>
        </div>
      </div>
      <div class="auth-feature">
        <div class="auth-feature-icon"><i class="fa-solid fa-lock"></i></div>
        <div class="auth-feature-text">
          <h5>100% Free & Secure</h5>
          <p>No credit card needed. Your data is protected with industry-standard security.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="auth-right" style="padding: 2rem;">
    <div class="auth-box animate-fadeInUp" style="max-width:460px;">
      <div class="auth-box-header">
        <div class="logo-icon">🌱</div>
        <h2>Create Account</h2>
        <p>Fill in your details below to get started</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <div>
            <i class="fa-solid fa-circle-exclamation"></i>
            <strong>Please fix the following:</strong>
            <ul style="margin:0.4rem 0 0 1rem;padding:0;">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <div class="auth-form">
        <div class="card">
          <div class="card-body">
            <form method="POST" action="register.php" id="registerForm">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

              <!-- Role selector -->
              <div class="form-group">
                <label class="form-label">I am registering as</label>
                <div class="role-selector">
                  <div class="role-option">
                    <input type="radio" name="role" id="reg_patient"
                           value="patient" <?= ($old['role'] ?? 'patient') === 'patient' ? 'checked' : '' ?>>
                    <label for="reg_patient">
                      <span class="role-icon">🌿</span>
                      Patient
                      <small style="color:var(--text-light);font-weight:400;font-size:0.72rem;">Instant access</small>
                    </label>
                  </div>
                  <div class="role-option">
                    <input type="radio" name="role" id="reg_herbalist"
                           value="herbalist" <?= ($old['role'] ?? '') === 'herbalist' ? 'checked' : '' ?>>
                    <label for="reg_herbalist">
                      <span class="role-icon">🌱</span>
                      Herbalist
                      <small style="color:var(--text-light);font-weight:400;font-size:0.72rem;">Requires approval</small>
                    </label>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="full_name">Full Name</label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-user input-icon"></i>
                  <input type="text" id="full_name" name="full_name"
                         class="form-control <?= !empty($errors) && empty($old['full_name']) ? 'is-invalid' : '' ?>"
                         placeholder="e.g. Marie Ngono"
                         value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                         required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-envelope input-icon"></i>
                  <input type="email" id="email" name="email"
                         class="form-control"
                         placeholder="your@email.com"
                         value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                         required>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="phone">Phone Number <span style="font-weight:400;color:var(--text-light);">(optional)</span></label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-phone input-icon"></i>
                  <input type="tel" id="phone" name="phone"
                         class="form-control"
                         placeholder="+237 6XX XXX XXX"
                         value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-lock input-icon"></i>
                  <input type="password" id="password" name="password"
                         class="form-control"
                         placeholder="Minimum 8 characters"
                         oninput="checkStrength(this.value)"
                         required>
                  <button type="button" class="toggle-password" onclick="togglePwd('password',this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
                <div class="password-strength">
                  <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                  <p class="strength-text" id="strengthText">Enter a password</p>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="password2">Confirm Password</label>
                <div class="input-icon-wrapper">
                  <i class="fa-solid fa-lock input-icon"></i>
                  <input type="password" id="password2" name="password2"
                         class="form-control" placeholder="Re-enter password" required>
                  <button type="button" class="toggle-password" onclick="togglePwd('password2',this)">
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </div>

              <div id="herbalistNote" class="alert alert-info" style="display:none;font-size:0.83rem;">
                <i class="fa-solid fa-circle-info"></i>
                <span>Herbalist accounts require admin approval before you can log in. This usually takes 24-48 hours.</span>
              </div>

              <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top:0.75rem;">
                <i class="fa-solid fa-user-plus"></i> Create My Account
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="auth-divider">Already have an account?</div>
      <a href="login.php" class="btn btn-outline btn-full">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In Instead
      </a>
    </div>
  </div>
</div>

<script>
// Toggle password visibility
function togglePwd(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  input.type  = input.type === 'password' ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}

// Password strength checker
function checkStrength(val) {
  const fill = document.getElementById('strengthFill');
  const text = document.getElementById('strengthText');
  let score = 0;
  if (val.length >= 8)  score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const levels = [
    { pct:'0%',   color:'#E2E8F0', label:'Enter a password' },
    { pct:'25%',  color:'#FC8181', label:'Weak' },
    { pct:'50%',  color:'#F6AD55', label:'Fair' },
    { pct:'75%',  color:'#68D391', label:'Good' },
    { pct:'100%', color:'#38A169', label:'Strong ✓' },
  ];
  fill.style.width      = levels[score].pct;
  fill.style.background = levels[score].color;
  text.textContent      = levels[score].label;
  text.style.color      = levels[score].color;
}

// Show herbalist approval notice
document.querySelectorAll('input[name="role"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const note = document.getElementById('herbalistNote');
    note.style.display = radio.value === 'herbalist' && radio.checked ? 'flex' : 'none';
  });
});
</script>
</body>
</html>
