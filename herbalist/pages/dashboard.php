<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('herbalist');
$db=Database::connect();$flash=getFlash();$user=getCurrentUser();$uid=$_SESSION['user_id'];
$s=$db->prepare("SELECT COUNT(*) FROM appointments WHERE herbalist_id=? AND status='pending'");$s->execute([$uid]);$stats['pending']=(int)$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM appointments WHERE herbalist_id=? AND status='confirmed'");$s->execute([$uid]);$stats['confirmed']=(int)$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM appointments WHERE herbalist_id=? AND status='completed'");$s->execute([$uid]);$stats['completed']=(int)$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE herbalist_id=?");$s->execute([$uid]);$stats['patients']=(int)$s->fetchColumn();
$today=$db->prepare("SELECT a.*,u.full_name AS patient_name,u.phone FROM appointments a JOIN users u ON u.id=a.patient_id WHERE a.herbalist_id=? AND a.appointment_date=CURDATE() AND a.status IN('pending','confirmed') ORDER BY a.appointment_time ASC");$today->execute([$uid]);$todayApts=$today->fetchAll();
$upcoming=$db->prepare("SELECT a.*,u.full_name AS patient_name FROM appointments a JOIN users u ON u.id=a.patient_id WHERE a.herbalist_id=? AND a.appointment_date>CURDATE() AND a.appointment_date<=DATE_ADD(CURDATE(),INTERVAL 7 DAY) AND a.status IN('pending','confirmed') ORDER BY a.appointment_date,a.appointment_time LIMIT 5");$upcoming->execute([$uid]);$upcomingApts=$upcoming->fetchAll();
$recent=$db->prepare("SELECT a.*,u.full_name AS patient_name FROM appointments a JOIN users u ON u.id=a.patient_id WHERE a.herbalist_id=? AND a.status='completed' ORDER BY a.appointment_date DESC LIMIT 5");$recent->execute([$uid]);$recentApts=$recent->fetchAll();
$prof=$db->prepare('SELECT * FROM herbalist_profiles WHERE user_id=?');$prof->execute([$uid]);$profile=$prof->fetch();
$incomplete=empty($profile['specialisation'])||empty($profile['bio'])||empty($profile['location']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — Herbalist Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if($incomplete):?><div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i><div><strong>Complete your profile</strong> — patients find you more easily with a complete profile. <a href="<?= APP_URL ?>/herbalist/pages/profile.php" style="font-weight:700;">Update now →</a></div></div><?php endif;?>
<div class="page-title"><h2>Good <?=date('H')<12?'Morning':(date('H')<17?'Afternoon':'Evening')?>, <?=htmlspecialchars(explode(' ',$user['full_name'])[0])?>! 🌱</h2><p>Your appointment overview for today and the coming week.</p></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem;">
  <div class="stat-card"><div class="stat-icon" style="background:#FEF3C7;color:#92400E;"><i class="fa-solid fa-clock"></i></div><div class="stat-info"><h3><?=$stats['pending']?></h3><p>Pending</p></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div><div class="stat-info"><h3><?=$stats['confirmed']?></h3><p>Confirmed</p></div></div>
  <div class="stat-card"><div class="stat-icon earth"><i class="fa-solid fa-check-double"></i></div><div class="stat-info"><h3><?=$stats['completed']?></h3><p>Completed</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-users"></i></div><div class="stat-info"><h3><?=$stats['patients']?></h3><p>Total Patients</p></div></div>
</div>
<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-calendar-day" style="color:var(--green-mid);"></i> Today — <?=date('l, d F Y')?></h4>
    <a href="<?= APP_URL ?>/herbalist/pages/appointments.php" class="btn btn-ghost btn-sm">View All</a>
  </div>
  <div class="card-body">
    <?php if(empty($todayApts)):?><div style="text-align:center;padding:2rem;color:var(--text-light);"><div style="font-size:2rem;margin-bottom:0.5rem;">🌿</div><p style="margin:0;">No appointments today.</p></div>
    <?php else:?><div style="display:flex;flex-direction:column;gap:0.75rem;">
      <?php foreach($todayApts as $a):?>
      <div style="display:flex;align-items:center;gap:1rem;padding:0.85rem;border:1px solid #E2E8F0;border-radius:var(--radius-sm);<?=$a['status']==='confirmed'?'border-left:3px solid var(--green-light);':''?>">
        <div style="text-align:center;background:var(--green-pale);border-radius:8px;padding:0.5rem 0.75rem;flex-shrink:0;"><div style="font-weight:700;font-size:1rem;color:var(--green-dark);"><?=date('h:i',strtotime($a['appointment_time']))?></div><div style="font-size:0.68rem;color:var(--green-mid);font-weight:600;"><?=date('A',strtotime($a['appointment_time']))?></div></div>
        <div style="flex:1;"><div style="font-weight:600;"><?=htmlspecialchars($a['patient_name'])?></div><?php if($a['phone']):?><div style="font-size:0.78rem;color:var(--text-light);">📞 <?=htmlspecialchars($a['phone'])?></div><?php endif;?><?php if($a['reason']):?><div style="font-size:0.8rem;color:var(--text-mid);font-style:italic;">"<?=htmlspecialchars(substr($a['reason'],0,60))?><?=strlen($a['reason'])>60?'…':''?>"</div><?php endif;?></div>
        <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green'];?><span class="badge <?=$bc[$a['status']]??'badge-gray'?>"><?=$a['status']?></span>
        <a href="<?= APP_URL ?>/herbalist/pages/appointments.php?action=manage&id=<?=$a['id']?>" class="btn btn-primary btn-sm">Manage</a>
      </div>
      <?php endforeach;?></div>
    <?php endif;?>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
  <div class="card"><div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-calendar-week"></i> Next 7 Days</h4></div><div class="card-body">
    <?php if(empty($upcomingApts)):?><p style="text-align:center;color:var(--text-light);padding:1rem 0;margin:0;">No upcoming appointments.</p>
    <?php else:?><?php foreach($upcomingApts as $a):?>
      <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid #F1F5F9;">
        <div style="text-align:center;width:42px;flex-shrink:0;"><div style="font-weight:700;font-size:1rem;color:var(--green-dark);"><?=date('d',strtotime($a['appointment_date']))?></div><div style="font-size:0.65rem;color:var(--text-light);text-transform:uppercase;"><?=date('M',strtotime($a['appointment_date']))?></div></div>
        <div style="flex:1;"><div style="font-weight:600;font-size:0.88rem;"><?=htmlspecialchars($a['patient_name'])?></div><div style="font-size:0.75rem;color:var(--text-light);"><?=date('h:i A',strtotime($a['appointment_time']))?></div></div>
        <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green'];?><span class="badge <?=$bc[$a['status']]??'badge-gray'?>" style="font-size:0.7rem;"><?=$a['status']?></span>
      </div>
    <?php endforeach;?><?php endif;?>
  </div></div>
  <div class="card"><div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-check-double"></i> Recently Completed</h4></div><div class="card-body">
    <?php if(empty($recentApts)):?><p style="text-align:center;color:var(--text-light);padding:1rem 0;margin:0;">No completed appointments yet.</p>
    <?php else:?><?php foreach($recentApts as $a):?>
      <div style="display:flex;align-items:center;gap:0.75rem;padding:0.6rem 0;border-bottom:1px solid #F1F5F9;">
        <div style="width:36px;height:36px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:0.9rem;font-weight:700;color:var(--green-dark);flex-shrink:0;"><?=strtoupper(substr($a['patient_name'],0,1))?></div>
        <div style="flex:1;"><div style="font-weight:600;font-size:0.88rem;"><?=htmlspecialchars($a['patient_name'])?></div><div style="font-size:0.75rem;color:var(--text-light);"><?=date('d M Y',strtotime($a['appointment_date']))?></div></div>
        <span class="badge badge-gray" style="font-size:0.7rem;">Done</span>
      </div>
    <?php endforeach;?><?php endif;?>
  </div></div>
</div>
</div></div></body></html>
