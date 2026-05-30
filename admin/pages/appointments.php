<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $id=(int)($_POST['apt_id']??0);$status=sanitize($_POST['new_status']??'');
  if(in_array($status,['pending','confirmed','completed','cancelled'])){
    $db->prepare('UPDATE appointments SET status=? WHERE id=?')->execute([$status,$id]);
    logAction('UPDATE_APPOINTMENT','appointments',$id,'Admin changed status to '.$status);
    setFlash('success','Appointment status updated.');
  }
  header('Location: '.APP_URL.'/admin/pages/appointments.php');exit;
}

$filter=sanitize($_GET['status']??'all');$page=max(1,(int)($_GET['page']??1));$perPage=15;
$where='WHERE 1=1';if($filter!=='all')$where.=" AND a.status='$filter'";
$cnt=$db->prepare("SELECT COUNT(*) FROM appointments a $where");$cnt->execute();$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT a.*,p.full_name AS patient_name,h.full_name AS herbalist_name FROM appointments a JOIN users p ON p.id=a.patient_id JOIN users h ON h.id=a.herbalist_id $where ORDER BY a.appointment_date DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute();$apts=$stmt->fetchAll();
$counts=['all'=>$db->query('SELECT COUNT(*) FROM appointments')->fetchColumn(),'pending'=>$db->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn(),'confirmed'=>$db->query("SELECT COUNT(*) FROM appointments WHERE status='confirmed'")->fetchColumn(),'completed'=>$db->query("SELECT COUNT(*) FROM appointments WHERE status='completed'")->fetchColumn(),'cancelled'=>$db->query("SELECT COUNT(*) FROM appointments WHERE status='cancelled'")->fetchColumn()];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointments — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title"><h2>Appointments</h2><p>Overview of all patient-herbalist bookings</p></div>
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php foreach($counts as $s=>$c):?>
  <a href="?status=<?=$s?>" class="btn <?=$filter===$s?'btn-primary':'btn-ghost'?> btn-sm"><?=ucfirst($s)?> (<?=$c?>)</a>
  <?php endforeach;?>
</div>
<div class="table-wrapper"><table>
  <thead><tr><th>Patient</th><th>Herbalist</th><th>Date & Time</th><th>Reason</th><th>Status</th><th>Update Status</th></tr></thead>
  <tbody>
  <?php foreach($apts as $a):?>
  <tr>
    <td style="font-weight:600;"><?=htmlspecialchars($a['patient_name'])?></td>
    <td><?=htmlspecialchars($a['herbalist_name'])?></td>
    <td><div style="font-weight:600;"><?=date('d M Y',strtotime($a['appointment_date']))?></div><div style="font-size:0.78rem;color:var(--text-light);"><?=date('h:i A',strtotime($a['appointment_time']))?></div></td>
    <td style="font-size:0.82rem;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars($a['reason']??'—')?></td>
    <td><?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red'];?><span class="badge <?=$bc[$a['status']]??'badge-gray'?>"><?=$a['status']?></span></td>
    <td>
      <form method="POST" style="display:flex;gap:0.4rem;align-items:center;">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="apt_id" value="<?=$a['id']?>">
        <select name="new_status" class="form-control form-select" style="width:auto;padding:0.3rem 2rem 0.3rem 0.6rem;font-size:0.82rem;">
          <?php foreach(['pending','confirmed','completed','cancelled'] as $s):?><option value="<?=$s?>" <?=$a['status']===$s?'selected':''?>><?=ucfirst($s)?></option><?php endforeach;?>
        </select>
        <button class="btn btn-primary btn-sm">Update</button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($apts)):?><tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-light);">No appointments found.</td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?status=<?=$filter?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
</div></div></body></html>
