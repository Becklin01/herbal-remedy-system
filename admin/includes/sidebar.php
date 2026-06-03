<?php
// ============================================================
//  Admin Sidebar + Topbar Partial
//  File: admin/includes/sidebar.php
// ============================================================
$currentPage = basename($_SERVER['PHP_SELF']);
$user        = getCurrentUser();

function navLink(string $href, string $icon, string $label, string $current): string {
    $base   = basename($href);
    $active = ($current === $base) ? 'active' : '';
    return "<a href=\"{$href}\" class=\"sidebar-link {$active}\"><i class=\"{$icon}\"></i> {$label}</a>";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
  <div style="display:flex;align-items:center;gap:0.6rem;">
    <img src="<?= APP_URL ?>/assets/images/herbal_logo.png"
         style="width:42px;height:42px;object-fit:contain;" alt="logo">
    <div>
      <h4 style="margin:0;color:#fff;font-family:var(--font-display);font-size:1.15rem;">Herbal System</h4>
      <p style="margin:0;font-size:0.75rem;color:var(--green-light);">Administrator Panel</p>
    </div>
  </div>
</div>
  <nav class="sidebar-nav">
    <div class="sidebar-section-title">Overview</div>
    <?= navLink(APP_URL.'/admin/pages/dashboard.php',    'fa-solid fa-gauge',            'Dashboard',        $currentPage) ?>
    <div class="sidebar-section-title">Content Management</div>
    <?= navLink(APP_URL.'/admin/pages/plants.php',       'fa-solid fa-seedling',         'Medicinal Plants', $currentPage) ?>
    <?= navLink(APP_URL.'/admin/pages/remedies.php',     'fa-solid fa-mortar-pestle',    'Remedies',         $currentPage) ?>
    <?= navLink(APP_URL.'/admin/pages/symptoms.php',     'fa-solid fa-stethoscope',      'Symptoms',         $currentPage) ?>
    <div class="sidebar-section-title">User Management</div>
    <?= navLink(APP_URL.'/admin/pages/patients.php',     'fa-solid fa-user-injured',     'Patients',         $currentPage) ?>
    <?= navLink(APP_URL.'/admin/pages/herbalists.php',   'fa-solid fa-user-nurse',       'Herbalists',       $currentPage) ?>
    <div class="sidebar-section-title">Operations</div>
    <?= navLink(APP_URL.'/admin/pages/appointments.php', 'fa-solid fa-calendar-check',   'Appointments',     $currentPage) ?>
    <?= navLink(APP_URL.'/admin/pages/scans.php',        'fa-solid fa-camera',           'Plant Scans',      $currentPage) ?>
    <?= navLink(APP_URL.'/admin/pages/audit.php',        'fa-solid fa-clock-rotate-left','Audit Log',        $currentPage) ?>
  </nav>
  <div class="sidebar-footer">
    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
      <div style="width:34px;height:34px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:var(--green-dark);flex-shrink:0;">
        <?= strtoupper(substr($user['full_name'],0,1)) ?>
      </div>
      <div style="overflow:hidden;">
        <p style="margin:0;font-size:0.82rem;color:#fff;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($user['full_name']) ?></p>
        <p style="margin:0;font-size:0.72rem;color:rgba(255,255,255,0.5);">Administrator</p>
      </div>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn btn-ghost btn-sm btn-full" style="color:rgba(255,255,255,0.7);border-color:rgba(255,255,255,0.15);">
      <i class="fa-solid fa-right-from-bracket"></i> Sign Out
    </a>
  </div>
</aside>
<div class="main-topbar">
  <div style="display:flex;align-items:center;gap:1rem;">
    <button onclick="document.getElementById('sidebar').classList.toggle('open')" id="menuToggle" style="display:none;background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--green-dark);">
      <i class="fa-solid fa-bars"></i>
    </button>
    <div>
      <h4 style="margin:0;font-size:1rem;">Admin Panel</h4>
      <p style="margin:0;font-size:0.78rem;color:var(--text-light);">Home / <?= ucfirst(str_replace(['.php','_'],['', ' '],$currentPage)) ?></p>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:0.75rem;">
    <span class="badge badge-green" style="font-size:0.72rem;"><i class="fa-solid fa-circle" style="font-size:0.5rem;color:#38a169;"></i> System Online</span>
    <a href="<?= APP_URL ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-up-right-from-square"></i> View Site</a>
  </div>
</div>
<style>@media(max-width:768px){#menuToggle{display:block !important;}}</style>
