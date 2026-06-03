<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$user        = getCurrentUser();
function pNavLink(string $href, string $icon, string $label, string $current): string {
    $active = (basename($href) === $current) ? 'active' : '';
    return "<a href=\"{$href}\" class=\"sidebar-link {$active}\"><i class=\"{$icon}\"></i> {$label}</a>";
}
?>
<aside class="sidebar" id="sidebar">
  <<div class="sidebar-brand">
  <div style="display:flex;align-items:center;gap:0.6rem;">
    <img src="<?= APP_URL ?>/assets/images/herbal_logo.png"
         style="width:42px;height:42px;object-fit:contain;" alt="logo">
    <div>
      <h4 style="margin:0;color:#fff;font-family:var(--font-display);font-size:1.15rem;">Herbal System</h4>
      <p style="margin:0;font-size:0.75rem;color:var(--green-light);">Patient Portal</p>
    </div>
  </div>
</div>
    <div class="sidebar-section-title">Main</div>
    <?= pNavLink(APP_URL.'/patient/pages/dashboard.php',       'fa-solid fa-gauge',            'Dashboard',       $currentPage) ?>
    <?= pNavLink(APP_URL.'/patient/pages/symptom_checker.php', 'fa-solid fa-stethoscope',      'Symptom Checker', $currentPage) ?>
    <?= pNavLink(APP_URL.'/patient/pages/plant_detect.php',    'fa-solid fa-camera',           'Plant Detection', $currentPage) ?>
    <div class="sidebar-section-title">Herbalists</div>
    <?= pNavLink(APP_URL.'/patient/pages/herbalists.php',      'fa-solid fa-user-nurse',       'Find Herbalists', $currentPage) ?>
    <?= pNavLink(APP_URL.'/patient/pages/my_appointments.php', 'fa-solid fa-calendar-check',   'My Appointments', $currentPage) ?>
    <div class="sidebar-section-title">Account</div>
    <?= pNavLink(APP_URL.'/patient/pages/history.php',         'fa-solid fa-clock-rotate-left','Search History',  $currentPage) ?>
    <?= pNavLink(APP_URL.'/patient/pages/profile.php',         'fa-solid fa-user',             'My Profile',      $currentPage) ?>
  </nav>
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
      <div style="width:34px;height:34px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:var(--green-dark);flex-shrink:0;"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
      <div style="overflow:hidden;"><p style="margin:0;font-size:0.82rem;color:#fff;font-weight:600;"><?= htmlspecialchars($user['full_name']) ?></p><p style="margin:0;font-size:0.72rem;color:rgba(255,255,255,0.5);">Patient</p></div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn btn-ghost btn-sm btn-full" style="color:rgba(255,255,255,0.7);border-color:rgba(255,255,255,0.15);"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
  </div>
</aside>
<div class="main-topbar">
  <div style="display:flex;align-items:center;gap:1rem;">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" id="menuToggle" style="display:none;background:none;border:none;font-size:1.2rem;cursor:pointer;"><i class="fa-solid fa-bars"></i></button>
    <div><h4 style="margin:0;font-size:1rem;">Patient Portal</h4><p style="margin:0;font-size:0.78rem;color:var(--text-light);">Welcome, <?= htmlspecialchars($user['full_name']) ?></p></div>
  </div>
  <a href="<?= APP_URL ?>/patient/pages/symptom_checker.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-stethoscope"></i> Check Symptoms</a>
</div>
<style>@media(max-width:768px){#menuToggle{display:block !important;}}</style>
