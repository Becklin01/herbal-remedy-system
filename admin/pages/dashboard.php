<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db    = Database::connect();
$flash = getFlash();

// Stats
$stats = [];
$stats['plants']       = $db->query('SELECT COUNT(*) FROM plants WHERE is_active=1')->fetchColumn();
$stats['remedies']     = $db->query('SELECT COUNT(*) FROM remedies WHERE is_active=1')->fetchColumn();
$stats['patients']     = $db->query("SELECT COUNT(*) FROM users WHERE role='patient' AND is_active=1")->fetchColumn();
$stats['herbalists']   = $db->query("SELECT COUNT(*) FROM users WHERE role='herbalist' AND is_active=1")->fetchColumn();
$stats['pending']      = $db->query("SELECT COUNT(*) FROM users WHERE role='herbalist' AND is_approved=0")->fetchColumn();
$stats['appointments'] = $db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();
$stats['scans']        = $db->query('SELECT COUNT(*) FROM plant_scans')->fetchColumn();
$stats['searches']     = $db->query('SELECT COUNT(*) FROM search_history')->fetchColumn();

// Recent registrations
$recentUsers = $db->query("SELECT id,full_name,email,role,created_at,is_approved FROM users WHERE role!='admin' ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Recent plant scans
$recentScans = $db->query("SELECT ps.*,u.full_name FROM plant_scans ps JOIN users u ON u.id=ps.user_id ORDER BY ps.scanned_at DESC LIMIT 6")->fetchAll();

// Top searched symptoms
$topSymptoms = $db->query("SELECT name,category FROM symptoms ORDER BY id LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Dashboard — Admin Panel</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper">
  <div class="main-content">
    <?php if($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <div class="page-title">
      <h2>Dashboard Overview</h2>
      <p>Welcome back, <?= htmlspecialchars(getCurrentUser()['full_name']) ?>. Here is what is happening in the system today.</p>
    </div>

    <?php if($stats['pending']>0): ?>
    <div class="alert alert-warning">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <strong><?= $stats['pending'] ?> herbalist account(s)</strong> are awaiting your approval.
      <a href="<?= APP_URL ?>/admin/pages/herbalists.php?filter=pending" style="font-weight:700;margin-left:0.5rem;">Review now →</a>
    </div>
    <?php endif; ?>

    <!-- Stat Cards Row 1 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.5rem;">
      <div class="stat-card">
        <div class="stat-icon green"><i class="fa-solid fa-seedling"></i></div>
        <div class="stat-info"><h3><?= $stats['plants'] ?></h3><p>Medicinal Plants</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon earth"><i class="fa-solid fa-mortar-pestle"></i></div>
        <div class="stat-info"><h3><?= $stats['remedies'] ?></h3><p>Herbal Remedies</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon gold"><i class="fa-solid fa-users"></i></div>
        <div class="stat-info"><h3><?= $stats['patients'] ?></h3><p>Registered Patients</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fa-solid fa-user-nurse"></i></div>
        <div class="stat-info"><h3><?= $stats['herbalists'] ?></h3><p>Active Herbalists</p></div>
      </div>
    </div>

    <!-- Stat Cards Row 2 -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
      <div class="stat-card">
        <div class="stat-icon" style="background:#FEF3C7;color:#92400E;"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-info"><h3><?= $stats['pending'] ?></h3><p>Pending Approvals</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:#EBF8FF;color:#1A365D;"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-info"><h3><?= $stats['appointments'] ?></h3><p>Pending Appointments</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-pale);color:var(--green-dark);"><i class="fa-solid fa-camera"></i></div>
        <div class="stat-info"><h3><?= $stats['scans'] ?></h3><p>Plant Scans</p></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--earth-light);color:var(--earth);"><i class="fa-solid fa-magnifying-glass"></i></div>
        <div class="stat-info"><h3><?= $stats['searches'] ?></h3><p>Symptom Searches</p></div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="card" style="margin-bottom:1.5rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-bolt" style="color:var(--gold);"></i> Quick Actions</h4></div>
      <div class="card-body" style="display:flex;gap:0.75rem;flex-wrap:wrap;">
        <a href="<?= APP_URL ?>/admin/pages/plants.php?action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Plant</a>
        <a href="<?= APP_URL ?>/admin/pages/remedies.php?action=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Remedy</a>
        <a href="<?= APP_URL ?>/admin/pages/herbalists.php?filter=pending" class="btn btn-outline btn-sm"><i class="fa-solid fa-user-check"></i> Approve Herbalists</a>
        <a href="<?= APP_URL ?>/admin/pages/audit.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-clock-rotate-left"></i> View Audit Log</a>
      </div>
    </div>

    <!-- Two column: Recent Users + Symptoms -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

      <!-- Recent Users -->
      <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
          <h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-users"></i> Recent Registrations</h4>
          <a href="<?= APP_URL ?>/admin/pages/patients.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
              <?php foreach($recentUsers as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($u['full_name']) ?></div>
                  <div style="font-size:0.75rem;color:var(--text-light);"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td><span class="badge <?= $u['role']==='herbalist'?'badge-earth':'badge-green' ?>"><?= $u['role'] ?></span></td>
                <td>
                  <?php if($u['role']==='herbalist' && !$u['is_approved']): ?>
                    <span class="badge badge-gold">Pending</span>
                  <?php else: ?>
                    <span class="badge badge-green">Active</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:0.82rem;"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($recentUsers)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:2rem;">No users yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Symptom keywords -->
      <div class="card">
        <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-stethoscope"></i> Symptom Database</h4></div>
        <div class="card-body">
          <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
            <?php foreach($topSymptoms as $s): ?>
              <span class="badge badge-green" style="text-transform:none;letter-spacing:0;"><?= htmlspecialchars($s['name']) ?></span>
            <?php endforeach; ?>
          </div>
          <div style="margin-top:1rem;">
            <a href="<?= APP_URL ?>/admin/pages/symptoms.php" class="btn btn-outline btn-sm btn-full">Manage Symptoms</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Plant Scans -->
    <?php if(!empty($recentScans)): ?>
    <div class="card">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-camera"></i> Recent Plant Scans</h4>
        <a href="<?= APP_URL ?>/admin/pages/scans.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrapper">
        <table>
          <thead><tr><th>Patient</th><th>Predicted Plant</th><th>Confidence</th><th>Flagged</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($recentScans as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['full_name']) ?></td>
              <td><?= htmlspecialchars($s['predicted_plant'] ?? '—') ?></td>
              <td>
                <?php if($s['confidence']): ?>
                  <div style="display:flex;align-items:center;gap:0.5rem;">
                    <div style="height:6px;width:80px;background:#E2E8F0;border-radius:99px;overflow:hidden;">
                      <div style="height:100%;width:<?= $s['confidence'] ?>%;background:<?= $s['confidence']>=70?'var(--green-light)':'#F6AD55' ?>;border-radius:99px;"></div>
                    </div>
                    <span style="font-size:0.82rem;"><?= $s['confidence'] ?>%</span>
                  </div>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td><?= $s['is_flagged'] ? '<span class="badge badge-red">Flagged</span>' : '<span class="badge badge-gray">OK</span>' ?></td>
              <td style="font-size:0.82rem;"><?= date('d M Y',strtotime($s['scanned_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
