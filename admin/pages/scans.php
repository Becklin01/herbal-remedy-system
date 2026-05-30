<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $id=(int)($_POST['scan_id']??0);$act=$_POST['form_action']??'';
  if($act==='flag'){$db->prepare('UPDATE plant_scans SET is_flagged=1 WHERE id=?')->execute([$id]);setFlash('warning','Scan flagged for review.');}
  if($act==='unflag'){$db->prepare('UPDATE plant_scans SET is_flagged=0 WHERE id=?')->execute([$id]);setFlash('success','Flag removed.');}
  header('Location: '.APP_URL.'/admin/pages/scans.php');exit;
}

$page=max(1,(int)($_GET['page']??1));$perPage=15;$filter=sanitize($_GET['filter']??'all');
$where='WHERE 1=1';if($filter==='flagged')$where.=' AND ps.is_flagged=1';
$cnt=$db->prepare("SELECT COUNT(*) FROM plant_scans ps $where");$cnt->execute();$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT ps.*,u.full_name,p.common_name AS matched_plant_name FROM plant_scans ps JOIN users u ON u.id=ps.user_id LEFT JOIN plants p ON p.id=ps.plant_id $where ORDER BY ps.scanned_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute();$scans=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Plant Scans — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title"><h2>Plant Scans</h2><p><?=$total?> AI plant detection scan(s)</p></div>
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;">
  <a href="?filter=all" class="btn <?=$filter==='all'?'btn-primary':'btn-ghost'?> btn-sm">All Scans</a>
  <a href="?filter=flagged" class="btn <?=$filter==='flagged'?'btn-primary':'btn-ghost'?> btn-sm">Flagged Only</a>
</div>
<div class="table-wrapper"><table>
  <thead><tr><th>Image</th><th>Patient</th><th>Predicted Plant</th><th>Confidence</th><th>Matched DB Plant</th><th>Date</th><th>Flag</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach($scans as $s):?>
  <tr>
    <td><?php if($s['image_filename']):?><img src="<?=APP_URL?>/assets/images/uploads/<?=htmlspecialchars($s['image_filename'])?>" style="width:44px;height:44px;object-fit:cover;border-radius:6px;" alt=""><?php else:?><div style="width:44px;height:44px;background:var(--green-pale);border-radius:6px;display:flex;align-items:center;justify-content:center;">🌿</div><?php endif;?></td>
    <td style="font-weight:600;font-size:0.88rem;"><?=htmlspecialchars($s['full_name'])?></td>
    <td style="font-style:italic;"><?=htmlspecialchars($s['predicted_plant']??'Unknown')?></td>
    <td><?php if($s['confidence']):$col=$s['confidence']>=70?'var(--green-light)':'#F6AD55';?>
      <div style="display:flex;align-items:center;gap:0.4rem;">
        <div style="height:5px;width:70px;background:#E2E8F0;border-radius:99px;overflow:hidden;"><div style="height:100%;width:<?=$s['confidence']?>%;background:<?=$col?>;border-radius:99px;"></div></div>
        <span style="font-size:0.8rem;"><?=number_format($s['confidence'],1)?>%</span>
      </div>
    <?php else:?>—<?php endif;?></td>
    <td style="font-size:0.85rem;"><?=htmlspecialchars($s['matched_plant_name']??'—')?></td>
    <td style="font-size:0.82rem;"><?=date('d M Y H:i',strtotime($s['scanned_at']))?></td>
    <td><?=$s['is_flagged']?'<span class="badge badge-red">Flagged</span>':'<span class="badge badge-gray">OK</span>'?></td>
    <td>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="scan_id" value="<?=$s['id']?>">
        <input type="hidden" name="form_action" value="<?=$s['is_flagged']?'unflag':'flag'?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-<?=$s['is_flagged']?'flag-checkered':'flag'?>"></i> <?=$s['is_flagged']?'Unflag':'Flag'?></button>
      </form>
    </td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($scans)):?><tr><td colspan="8" style="text-align:center;padding:2.5rem;color:var(--text-light);">No scans found.</td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?filter=<?=$filter?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
</div></div></body></html>
