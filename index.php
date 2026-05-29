<?php
// ============================================================
//  Landing Page — index.php
//  Redirects logged-in users to their dashboard
// ============================================================
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Redirect already-logged-in users
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin')     { header('Location: ' . APP_URL . '/admin/pages/dashboard.php');     exit; }
    if ($role === 'herbalist') { header('Location: ' . APP_URL . '/herbalist/pages/dashboard.php'); exit; }
    if ($role === 'patient')   { header('Location: ' . APP_URL . '/patient/pages/dashboard.php');   exit; }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Herbal Remedy System — Natural Healthcare AI</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .hero {
      min-height: 100vh;
      background: var(--green-dark);
      display: grid;
      grid-template-columns: 1fr 1fr;
      position: relative;
      overflow: hidden;
    }
    .hero::before {
      content: '';
      position: absolute;
      top: -30%;
      right: -10%;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(82,183,136,0.12) 0%, transparent 70%);
      border-radius: 50%;
    }
    .hero-left {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 4rem 3rem 4rem 5vw;
      position: relative;
      z-index: 2;
    }
    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(82,183,136,0.15);
      color: var(--green-light);
      border: 1px solid rgba(82,183,136,0.3);
      padding: 0.35rem 0.9rem;
      border-radius: 99px;
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      margin-bottom: 1.5rem;
      width: fit-content;
    }
    .hero-left h1 {
      color: var(--white);
      font-size: clamp(2rem, 4vw, 3.2rem);
      line-height: 1.2;
      margin-bottom: 1.25rem;
    }
    .hero-left h1 em {
      font-style: normal;
      color: var(--green-light);
    }
    .hero-left p {
      color: rgba(255,255,255,0.7);
      font-size: 1.05rem;
      line-height: 1.75;
      max-width: 480px;
      margin-bottom: 2.5rem;
    }
    .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
    .hero-right {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem 5vw 3rem 2rem;
      position: relative;
      z-index: 2;
    }
    .hero-card {
      background: rgba(255,255,255,0.04);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: var(--radius-xl);
      padding: 2.5rem;
      width: 100%;
      max-width: 420px;
      backdrop-filter: blur(10px);
    }
    .hero-card h3 {
      color: var(--white);
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
    }
    .hero-card > p {
      color: rgba(255,255,255,0.55);
      font-size: 0.85rem;
      margin-bottom: 1.75rem;
    }
    .portal-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .portal-card {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: var(--radius-md);
      padding: 1.1rem;
      text-align: center;
      transition: var(--transition);
      text-decoration: none;
      cursor: pointer;
    }
    .portal-card:hover {
      background: rgba(82,183,136,0.15);
      border-color: rgba(82,183,136,0.4);
      transform: translateY(-2px);
    }
    .portal-card .p-icon {
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
    }
    .portal-card h5 {
      color: var(--white);
      font-size: 0.9rem;
      font-family: var(--font-body);
      font-weight: 600;
      margin-bottom: 0.2rem;
    }
    .portal-card p {
      color: rgba(255,255,255,0.5);
      font-size: 0.75rem;
      margin: 0;
    }
    .portal-divider {
      text-align: center;
      margin: 1.25rem 0;
      color: rgba(255,255,255,0.35);
      font-size: 0.8rem;
      position: relative;
    }
    .portal-divider::before, .portal-divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 38%;
      height: 1px;
      background: rgba(255,255,255,0.12);
    }
    .portal-divider::before { left: 0; }
    .portal-divider::after  { right: 0; }
    .features-strip {
      background: var(--cream);
      padding: 4rem 5vw;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1.5rem;
      max-width: 1100px;
      margin: 2.5rem auto 0;
    }
    .feature-item {
      text-align: center;
      padding: 1.75rem 1.25rem;
      border-radius: var(--radius-md);
      background: var(--white);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }
    .feature-item:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .feature-item .f-icon {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-sm);
      background: var(--green-pale);
      color: var(--green-dark);
      font-size: 1.4rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
    }
    .feature-item h4 { font-size: 1rem; margin-bottom: 0.5rem; }
    .feature-item p  { font-size: 0.85rem; margin: 0; }
    footer {
      background: var(--green-dark);
      color: rgba(255,255,255,0.6);
      text-align: center;
      padding: 1.5rem;
      font-size: 0.85rem;
    }
    footer span { color: var(--green-light); }
    @media (max-width: 768px) {
      .hero { grid-template-columns: 1fr; }
      .hero-right { display: none; }
      .hero-left  { padding: 3rem 1.5rem; }
    }
  </style>
</head>
<body>

<!-- ── HERO ─────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-left animate-fadeInUp">
    <div class="hero-badge">
      <i class="fa-solid fa-leaf"></i>
      AI-Powered · Herbal Medicine
    </div>
    <h1>
      Your <em>Natural</em><br>
      Healthcare<br>
      Companion
    </h1>
    <p>
      Describe your symptoms and get intelligent herbal remedy recommendations.
      Identify medicinal plants from photos. Connect with qualified herbalists —
      all in one place.
    </p>
    <div class="hero-actions">
      <a href="register.php" class="btn btn-primary btn-lg">
        <i class="fa-solid fa-user-plus"></i> Get Started Free
      </a>
      <a href="login.php" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,0.4);">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </a>
    </div>
  </div>

  <div class="hero-right animate-fadeIn">
    <div class="hero-card">
      <h3>Access Your Portal</h3>
      <p>Choose your account type to get started</p>

      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:1rem;">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="portal-cards">
        <a href="login.php?role=patient" class="portal-card">
          <div class="p-icon">🌿</div>
          <h5>Patient</h5>
          <p>Find remedies</p>
        </a>
        <a href="login.php?role=herbalist" class="portal-card">
          <div class="p-icon">🌱</div>
          <h5>Herbalist</h5>
          <p>Manage patients</p>
        </a>
      </div>

      <div class="portal-divider">or</div>

      <a href="login.php?role=admin" class="portal-card" style="display:flex;align-items:center;gap:0.75rem;text-align:left;padding:1rem 1.25rem;">
        <div class="p-icon" style="font-size:1.4rem;margin:0;">⚙️</div>
        <div>
          <h5 style="margin:0;">Administrator</h5>
          <p style="margin:0;">System management</p>
        </div>
      </a>

      <p style="text-align:center;margin-top:1.25rem;font-size:0.82rem;color:rgba(255,255,255,0.4);">
        New to the platform?
        <a href="register.php" style="color:var(--green-light);font-weight:600;">Create an account</a>
      </p>
    </div>
  </div>
</section>

<!-- ── FEATURES ──────────────────────────────────────────────── -->
<section class="features-strip">
  <div style="text-align:center;">
    <h2>Everything you need for <span class="text-green">herbal healthcare</span></h2>
    <p style="color:var(--text-light);max-width:560px;margin:0.75rem auto 0;">
      A complete AI-powered platform combining traditional herbal knowledge with modern technology.
    </p>
  </div>
  <div class="features-grid">
    <div class="feature-item">
      <div class="f-icon"><i class="fa-solid fa-magnifying-glass-plus"></i></div>
      <h4>Symptom Checker</h4>
      <p>Describe your symptoms and get personalised herbal remedy recommendations instantly.</p>
    </div>
    <div class="feature-item">
      <div class="f-icon" style="background:var(--earth-light);color:var(--earth);"><i class="fa-solid fa-camera"></i></div>
      <h4>Plant Detection</h4>
      <p>Upload a photo of any plant and our AI will identify it and explain its medicinal uses.</p>
    </div>
    <div class="feature-item">
      <div class="f-icon" style="background:#FEF3C7;color:#92400E;"><i class="fa-solid fa-book-open"></i></div>
      <h4>Remedy Library</h4>
      <p>Browse a curated database of Cameroonian medicinal plants with preparation guides.</p>
    </div>
    <div class="feature-item">
      <div class="f-icon" style="background:#EBF8FF;color:#1A365D;"><i class="fa-solid fa-calendar-check"></i></div>
      <h4>Book Herbalists</h4>
      <p>Find and book consultations with verified herbalists near you.</p>
    </div>
  </div>
</section>

<footer>
  <p>© <?= date('Y') ?> <span>Herbal Remedy System</span> · BECKLIN SAMUEL (ICTU20223544) · BSc Software Engineering · The ICT University</p>
</footer>

</body>
</html>
