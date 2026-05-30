<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();

$page=max(1,(int)($_GET['page']??1));$perPage=20;
$cnt=$db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();$total=(int)$cnt;
$pag=paginate($total,$perPage,$page);
$logs=$db->query("SELECT al.*,u.full_name FROM audit_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.logged_at DESC LIMIT $perPage OFFSET {$pag['offset']}")->fetchAll();
$actionColors=['LOGIN'=>'badge-green','LOGOUT'=>'badge-gray','REGISTER'=>'badge-green','CREATE_PLANT'=>'badge-earth','EDIT_PLANT'=>'badge-gold','DELETE_PLANT'=>'badge-red','APPROVE_HERBALIST'=>'badge-green','REJECT_HERBALIST'=>'badge-red','TOGGLE_PATIENT'=>'badge-gold','UPDATE_APPOINTMENT'=>'badge-gold'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Audit Log — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<div class="page-title"><h2>Audit Log</h2><p><?=$total?> total system events recorded</p></div>
<div class="table-wrapper"><table>
  <thead><tr><th>Action</th><th>User</th><th>Target</th><th>Description</th><th>IP Address</th><th>Date & Time</th></tr></thead>
  <tbody>
  <?php foreach($logs as $l):?>
  <tr>
    <td><span class="badge <?=$actionColors[$l['action']]??'badge-gray'?>" style="text-transform:none;letter-spacing:0;"><?=htmlspecialchars($l['action'])?></span></td>
    <td style="font-size:0.85rem;font-weight:600;"><?=htmlspecialchars($l['full_name']??'System')?></td>
    <td style="font-size:0.82rem;color:var(--text-light);"><?=htmlspecialchars($l['target_table']??'—')?><?=$l['target_id']?' #'.$l['target_id']:''?></td>
    <td style="font-size:0.82rem;"><?=htmlspecialchars($l['description']??'—')?></td>
    <td style="font-size:0.78rem;color:var(--text-light);"><?=htmlspecialchars($l['ip_address']??'—')?></td>
    <td style="font-size:0.82rem;"><?=date('d M Y H:i:s',strtotime($l['logged_at']))?></td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($logs)):?><tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-light);">No audit records yet.</td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
</div></div></body></html>
