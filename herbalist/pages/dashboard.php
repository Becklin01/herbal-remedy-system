<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole('herbalist');
$user  = getCurrentUser();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Herbalist Dashboard — Herbal Remedy System</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div style="min-height:100vh;background:var(--cream);display:flex;align-items:center;justify-content:center;">
  <div style="text-align:center;max-width:500px;padding:2rem;">
    <?php if ($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:1.5rem;">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>
    <div style="font-size:4rem;margin-bottom:1rem;">🌱</div>
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
    <p>Your <strong>Herbalist Dashboard</strong> is being built. Coming in the next module.</p>
    <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline" style="margin-top:1rem;">
      <i class="fa-solid fa-right-from-bracket"></i> Sign Out
    </a>
  </div>
</div>
</body>
</html>
