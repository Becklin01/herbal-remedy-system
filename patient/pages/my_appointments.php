<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');
$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $aid=(int)($_POST['apt_id']??0);
  $chk=$db->prepare('SELECT id FROM appointments WHERE id=? AND patient_id=? AND status=?');
  $chk->execute([$aid,$uid,'pending']);
  if($chk->fetch()){
    $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$aid]);
    setFlash('success','Appointment cancelled.');
  }
  header('Location: '.APP_URL.'/patient/pages/my_appointments.php');exit;
}

$filter=sanitize($_GET['status']??'all');$page=max(1,(int)($_GET['page']??1));$perPage=10;
$where="WHERE a.patient_id=$uid";if($filter!=='all')$where.=" AND a.status='$filter'";
$cnt=$db->query("SELECT COUNT(*) FROM appointments a $where")->fetchColumn();$total=(int)$cnt;
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT a.*,u.full_name AS herbalist_name,hp.specialisation,hp.location FROM appointments a JOIN users u ON u.id=a.herbalist_id LEFT JOIN herbalist_profiles hp ON hp.user_id=a.herbalist_id $where ORDER BY a.appointment_date DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute();$apts=$stmt->fetchAll();
$counts=[];foreach(['all','pending','confirmed','completed','cancelled'] as $s){$w="WHERE a.patient_id=$uid".($s!=='all'?" AND a.status='$s'":'');$counts[$s]=(int)$db->query("SELECT COUNT(*) FROM appointments a $w")->fetchColumn();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Appointments — Patient Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
  <div><h2><i class="fa-solid fa-calendar-check" style="color:var(--green-mid);"></i> My Appointments</h2><p>Track and manage all your herbalist consultations.</p></div>
  <a href="<?= APP_URL ?>/patient/pages/herbalists.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Book New</a>
</div>

<!-- Status filters -->
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php foreach($counts as $s=>$c):?>
  <a href="?status=<?=$s?>" class="btn <?=$filter===$s?'btn-primary':'btn-ghost'?> btn-sm"><?=ucfirst($s)?> (<?=$c?>)</a>
  <?php endforeach;?>
</div>

<?php if(empty($apts)):?>
<div style="text-align:center;padding:3rem;">
  <div style="font-size:3rem;margin-bottom:1rem;">📅</div>
  <h3>No Appointments Found</h3>
  <p><?=$filter!=='all'?'No '.$filter.' appointments.':'You have not booked any appointments yet.'?></p>
  <a href="<?= APP_URL ?>/patient/pages/herbalists.php" class="btn btn-primary" style="margin-top:0.75rem;"><i class="fa-solid fa-user-nurse"></i> Find a Herbalist</a>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:1rem;">
<?php foreach($apts as $a):?>
<div class="card">
  <div class="card-body" style="display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">
    <!-- Date block -->
    <div style="text-align:center;background:var(--green-pale);border-radius:var(--radius-sm);padding:0.75rem 1rem;min-width:70px;flex-shrink:0;">
      <div style="font-size:1.4rem;font-weight:700;color:var(--green-dark);line-height:1;"><?=date('d',strtotime($a['appointment_date']))?></div>
      <div style="font-size:0.72rem;color:var(--green-mid);font-weight:600;text-transform:uppercase;"><?=date('M Y',strtotime($a['appointment_date']))?></div>
    </div>
    <!-- Details -->
    <div style="flex:1;min-width:180px;">
      <h4 style="margin:0 0 0.2rem;font-size:1rem;"><?=htmlspecialchars($a['herbalist_name'])?></h4>
      <?php if($a['specialisation']):?><p style="margin:0 0 0.2rem;font-size:0.8rem;color:var(--green-mid);"><?=htmlspecialchars($a['specialisation'])?></p><?php endif;?>
      <p style="margin:0;font-size:0.8rem;color:var(--text-light);">
        <i class="fa-solid fa-clock"></i> <?=date('h:i A',strtotime($a['appointment_time']))?>
        <?php if($a['location']):?> &nbsp;·&nbsp; <i class="fa-solid fa-location-dot"></i> <?=htmlspecialchars($a['location'])?><?php endif;?>
      </p>
      <?php if($a['reason']):?><p style="margin:0.25rem 0 0;font-size:0.8rem;color:var(--text-mid);font-style:italic;">"<?=htmlspecialchars(substr($a['reason'],0,80))?><?=strlen($a['reason'])>80?'…':''?>"</p><?php endif;?>
    </div>
    <!-- Status + action -->
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.5rem;">
      <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red'];?>
      <span class="badge <?=$bc[$a['status']]??'badge-gray'?>" style="font-size:0.82rem;"><?=ucfirst($a['status'])?></span>
      <?php if($a['status']==='pending'):?>
      <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="apt_id" value="<?=$a['id']?>">
        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-times"></i> Cancel</button>
      </form>
      <?php elseif($a['status']==='confirmed'):?>
        <span style="font-size:0.75rem;color:var(--green-mid);"><i class="fa-solid fa-circle-check"></i> Confirmed by herbalist</span>
      <?php elseif($a['status']==='completed'):?>
        <span style="font-size:0.75rem;color:var(--text-light);"><i class="fa-solid fa-check-double"></i> Completed</span>
      <?php endif;?>
    </div>
    <?php if($a['notes']):?>
    <div style="width:100%;background:var(--green-pale);border-radius:var(--radius-sm);padding:0.6rem 0.85rem;margin-top:0.25rem;border-left:3px solid var(--green-mid);">
      <p style="margin:0;font-size:0.8rem;color:var(--green-dark);"><strong>Herbalist note:</strong> <?=htmlspecialchars($a['notes'])?></p>
    </div>
    <?php endif;?>
  </div>
</div>
<?php endforeach;?>
</div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?status=<?=$filter?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
<?php endif;?>
</div></div></body></html>
