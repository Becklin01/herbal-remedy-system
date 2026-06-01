<?php
// ============================================================
//  Herbalist — My Patients
//  File: herbalist/pages/patients.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('herbalist');
$db=$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];

$search=sanitize($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$perPage=12;

$where="WHERE a.herbalist_id=$uid";
$params=[];
if($search){$where.=" AND u.full_name LIKE ?";$params=["%$search%"];}

// Unique patients who have had appointments with this herbalist
$cntSql="SELECT COUNT(DISTINCT a.patient_id) FROM appointments a JOIN users u ON u.id=a.patient_id $where";
$cnt=$db->prepare($cntSql);$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);

$sql="SELECT u.id,u.full_name,u.email,u.phone,u.created_at,
      COUNT(a.id) AS total_apts,
      SUM(CASE WHEN a.status='completed' THEN 1 ELSE 0 END) AS completed_apts,
      SUM(CASE WHEN a.status='pending' THEN 1 ELSE 0 END) AS pending_apts,
      MAX(a.appointment_date) AS last_apt
      FROM appointments a
      JOIN users u ON u.id=a.patient_id
      $where
      GROUP BY u.id,u.full_name,u.email,u.phone,u.created_at
      ORDER BY last_apt DESC
      LIMIT $perPage OFFSET {$pag['offset']}";
$stmt=$db->prepare($sql);$stmt->execute($params);$patients=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Patients — Herbalist Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title"><h2><i class="fa-solid fa-users" style="color:var(--green-mid);"></i> My Patients</h2><p><?=$total?> patient(s) have booked appointments with you.</p></div>

<div class="card" style="margin-bottom:1.25rem;"><div class="card-body" style="padding:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;">
    <div class="input-icon-wrapper" style="flex:1;"><i class="fa-solid fa-search input-icon"></i><input type="text" name="q" class="form-control" placeholder="Search patients by name…" value="<?=htmlspecialchars($search)?>"></div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search):?><a href="?" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div></div>

<?php if(empty($patients)):?>
<div style="text-align:center;padding:3rem;"><div style="font-size:3rem;margin-bottom:1rem;">👥</div><h3>No Patients Yet</h3><p><?=$search?'No patients match your search.':'Patients who book appointments with you will appear here.'?></p></div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
<?php foreach($patients as $p):?>
<div class="card">
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:0.85rem;margin-bottom:1rem;">
      <div style="width:46px;height:46px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;color:var(--green-dark);flex-shrink:0;"><?=strtoupper(substr($p['full_name'],0,1))?></div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($p['full_name'])?></div>
        <div style="font-size:0.75rem;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($p['email'])?></div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.4rem;text-align:center;margin-bottom:1rem;">
      <div style="background:var(--green-pale);border-radius:6px;padding:0.4rem;"><div style="font-weight:700;font-size:1rem;color:var(--green-dark);"><?=$p['total_apts']?></div><div style="font-size:0.68rem;color:var(--text-light);">Total</div></div>
      <div style="background:#F0FFF4;border-radius:6px;padding:0.4rem;"><div style="font-weight:700;font-size:1rem;color:#276749;"><?=$p['completed_apts']?></div><div style="font-size:0.68rem;color:var(--text-light);">Done</div></div>
      <div style="background:#FFFBEB;border-radius:6px;padding:0.4rem;"><div style="font-weight:700;font-size:1rem;color:#92400E;"><?=$p['pending_apts']?></div><div style="font-size:0.68rem;color:var(--text-light);">Pending</div></div>
    </div>
    <div style="font-size:0.78rem;color:var(--text-light);margin-bottom:0.75rem;">
      <?php if($p['phone']):?><div>📞 <?=htmlspecialchars($p['phone'])?></div><?php endif;?>
      <div>📅 Last: <?=date('d M Y',strtotime($p['last_apt']))?></div>
    </div>
    <a href="<?= APP_URL ?>/herbalist/pages/appointments.php?status=all" class="btn btn-ghost btn-sm btn-full"><i class="fa-solid fa-calendar-check"></i> View Appointments</a>
  </div>
</div>
<?php endforeach;?>
</div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?q=<?=urlencode($search)?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
<?php endif;?>
</div></div></body></html>
