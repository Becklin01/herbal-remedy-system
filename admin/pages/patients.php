<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $uid=(int)($_POST['user_id']??0);$act=$_POST['form_action']??'';
  if($act==='toggle'){$cur=(int)$_POST['current_active'];$db->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([!$cur,$uid]);logAction('TOGGLE_PATIENT','users',$uid,'');setFlash('success','Patient status updated.');}
  header('Location: '.APP_URL.'/admin/pages/patients.php');exit;
}

$search=sanitize($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$perPage=15;
$where="WHERE role='patient'";$params=[];
if($search){$where.=" AND (full_name LIKE ? OR email LIKE ?)";$s="%$search%";$params=[$s,$s];}
$cnt=$db->prepare("SELECT COUNT(*) FROM users $where");$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT u.*,(SELECT COUNT(*) FROM search_history WHERE user_id=u.id) AS searches,(SELECT COUNT(*) FROM plant_scans WHERE user_id=u.id) AS scans,(SELECT COUNT(*) FROM appointments WHERE patient_id=u.id) AS apts FROM users u $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute($params);$patients=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Patients — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title"><h2>Patient Accounts</h2><p><?=$total?> registered patient(s)</p></div>
<div class="card" style="margin-bottom:1rem;"><div class="card-body" style="padding:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;">
    <div class="input-icon-wrapper" style="flex:1;"><i class="fa-solid fa-search input-icon"></i><input type="text" name="q" class="form-control" placeholder="Search by name or email…" value="<?=htmlspecialchars($search)?>"></div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search):?><a href="?" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div></div>
<div class="table-wrapper"><table>
  <thead><tr><th>Patient</th><th>Phone</th><th>Searches</th><th>Scans</th><th>Appointments</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach($patients as $p):?>
  <tr>
    <td><div style="font-weight:600;"><?=htmlspecialchars($p['full_name'])?></div><div style="font-size:0.78rem;color:var(--text-light);"><?=htmlspecialchars($p['email'])?></div></td>
    <td style="font-size:0.85rem;"><?=htmlspecialchars($p['phone']??'—')?></td>
    <td><span class="badge badge-green"><?=$p['searches']?></span></td>
    <td><span class="badge badge-earth"><?=$p['scans']?></span></td>
    <td><span class="badge badge-gray"><?=$p['apts']?></span></td>
    <td style="font-size:0.82rem;"><?=date('d M Y',strtotime($p['created_at']))?></td>
    <td><span class="badge <?=$p['is_active']?'badge-green':'badge-red'?>"><?=$p['is_active']?'Active':'Suspended'?></span></td>
    <td>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle patient status?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="toggle">
        <input type="hidden" name="user_id" value="<?=$p['id']?>">
        <input type="hidden" name="current_active" value="<?=$p['is_active']?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-power-off"></i></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($patients)):?><tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-light);">No patients found.</td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?q=<?=urlencode($search)?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
</div></div></body></html>
