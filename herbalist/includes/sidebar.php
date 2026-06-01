<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$user        = getCurrentUser();
function hNavLink(string $href, string $icon, string $label, string $current): string {
    $active = (basename($href) === $current) ? 'active' : '';
    return "<a href=\"{$href}\" class=\"sidebar-link {$active}\"><i class=\"{$icon}\"></i> {$label}</a>";
}
$db2 = Database::connect();
$pc  = $db2->prepare("SELECT COUNT(*) FROM appointments WHERE herbalist_id=? AND status='pending'");
$pc->execute([$_SESSION['user_id']]);
$pendingCount = (int)$pc->fetchColumn();
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <h4>🌱 Herbal System</h4>
    <p>Herbalist Portal</p>
  </div>
  <nav class="sidebar-nav">
    <div class="sidebar-section-title">Main</div>
    <?= hNavLink(APP_URL.'/herbalist/pages/dashboard.php',   'fa-solid fa-gauge',        'Dashboard',   $currentPage) ?>
    <div class="sidebar-section-title">Appointments</div>
    <a href="<?= APP_URL ?>/herbalist/pages/appointments.php" class="sidebar-link <?= $currentPage==='appointments.php'?'active':'' ?>">
      <i class="fa-solid fa-calendar-check"></i> Appointments
      <?php if($pendingCount>0): ?>
        <span style="margin-left:auto;background:#c53030;color:#fff;border-radius:99px;padding:0 7px;font-size:0.7rem;font-weight:700;"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>
    <?= hNavLink(APP_URL.'/herbalist/pages/patients.php',    'fa-solid fa-users',        'My Patients', $currentPage) ?>
    <div class="sidebar-section-title">Account</div>
    <?= hNavLink(APP_URL.'/herbalist/pages/profile.php',     'fa-solid fa-user-nurse',   'My Profile',  $currentPage) ?>
    <?= hNavLink(APP_URL.'/herbalist/pages/schedule.php',    'fa-solid fa-clock',        'My Schedule', $currentPage) ?>
  </nav>
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
      <div style="width:34px;height:34px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:var(--green-dark);flex-shrink:0;"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      <div style="overflow:hidden;"><p style="margin:0;font-size:0.82rem;color:#fff;font-weight:600;"><?= htmlspecialchars($user['full_name']) ?></p><p style="margin:0;font-size:0.72rem;color:rgba(255,255,255,0.5);">Herbalist</p></div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn btn-ghost btn-sm btn-full" style="color:rgba(255,255,255,0.7);border-color:rgba(255,255,255,0.15);"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
</aside>
<div class="main-topbar">
  <div style="display:flex;align-items:center;gap:1rem;">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" id="menuToggle" style="display:none;background:none;border:none;font-size:1.2rem;cursor:pointer;"><i class="fa-solid fa-bars"></i></button>
    <div><h4 style="margin:0;font-size:1rem;">Herbalist Portal</h4><p style="margin:0;font-size:0.78rem;color:var(--text-light);">Welcome, <?= htmlspecialchars($user['full_name']) ?></p></div>
  </div>
  <?php if($pendingCount>0): ?>
  <a href="<?= APP_URL ?>/herbalist/pages/appointments.php?status=pending" class="btn btn-primary btn-sm"><i class="fa-solid fa-bell"></i> <?= $pendingCount ?> Pending</a>
  <?php endif; ?>
</div>
<style>@media(max-width:768px){#menuToggle{display:block !important;}}</style>
