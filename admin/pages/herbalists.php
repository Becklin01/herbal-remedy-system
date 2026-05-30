<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();$errors=[];
$filter=sanitize($_GET['filter']??'all');

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!verifyCsrf($_POST['csrf_token']??'')){$errors[]='Invalid request.';}
  else{
    $act=(int)$_POST['form_action']??'';
    $uid=(int)($_POST['user_id']??0);
    if($_POST['form_action']==='approve'){
      $db->prepare("UPDATE users SET is_approved=1 WHERE id=? AND role='herbalist'")->execute([$uid]);
      logAction('APPROVE_HERBALIST','users',$uid,'Admin approved herbalist');
      setFlash('success','Herbalist approved successfully.');
    }
    if($_POST['form_action']==='reject'){
      $db->prepare("UPDATE users SET is_active=0 WHERE id=? AND role='herbalist'")->execute([$uid]);
      logAction('REJECT_HERBALIST','users',$uid,'Admin rejected herbalist');
      setFlash('warning','Herbalist account deactivated.');
    }
    if($_POST['form_action']==='toggle'){
      $cur=(int)$_POST['current_active'];
      $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([!$cur,$uid]);
      setFlash('success','Status updated.');
    }
    header('Location: '.APP_URL.'/admin/pages/herbalists.php?filter='.$filter);exit;
  }
}

$where="WHERE u.role='herbalist'";
if($filter==='pending') $where.=" AND u.is_approved=0 AND u.is_active=1";
elseif($filter==='active') $where.=" AND u.is_approved=1 AND u.is_active=1";

$page=max(1,(int)($_GET['page']??1));$perPage=12;
$cnt=$db->prepare("SELECT COUNT(*) FROM users u $where");$cnt->execute();$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT u.*,hp.specialisation,hp.location,hp.years_experience,hp.rating_avg,hp.consultation_fee FROM users u LEFT JOIN herbalist_profiles hp ON hp.user_id=u.id $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute();$herbalists=$stmt->fetchAll();
$pendingCount=(int)$db->query("SELECT COUNT(*) FROM users WHERE role='herbalist' AND is_approved=0 AND is_active=1")->fetchColumn();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Herbalists — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><?=htmlspecialchars($errors[0])?></div><?php endif;?>

<div class="page-title"><h2>Herbalist Management</h2><p>Review, approve or manage herbalist accounts.</p></div>

<!-- Filter tabs -->
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <a href="?filter=all"     class="btn <?=$filter==='all'    ?'btn-primary':'btn-ghost'?> btn-sm">All Herbalists (<?=$total?>)</a>
  <a href="?filter=pending" class="btn <?=$filter==='pending'?'btn-primary':'btn-ghost'?> btn-sm">
    Pending Approval
    <?php if($pendingCount>0):?><span style="background:#c53030;color:#fff;border-radius:99px;padding:0 6px;font-size:0.72rem;margin-left:4px;"><?=$pendingCount?></span><?php endif;?>
  </a>
  <a href="?filter=active"  class="btn <?=$filter==='active' ?'btn-primary':'btn-ghost'?> btn-sm">Active</a>
</div>

<?php if($filter==='pending'&&$pendingCount>0):?>
<div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> <strong><?=$pendingCount?> herbalist(s)</strong> are waiting for approval. Review their profiles below and approve or reject.</div>
<?php endif;?>

<!-- Cards grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;">
<?php foreach($herbalists as $h):?>
<div class="card">
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1rem;">
      <div style="width:48px;height:48px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;color:var(--green-dark);flex-shrink:0;">
        <?=strtoupper(substr($h['full_name'],0,1))?>
      </div>
      <div style="flex:1;min-width:0;">
        <h4 style="margin:0;font-size:1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($h['full_name'])?></h4>
        <p style="margin:0;font-size:0.8rem;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($h['email'])?></p>
        <?php if($h['specialisation']):?><p style="margin:0.25rem 0 0;font-size:0.78rem;color:var(--green-mid);"><?=htmlspecialchars($h['specialisation'])?></p><?php endif;?>
      </div>
      <?php if(!$h['is_approved']):?>
        <span class="badge badge-gold">Pending</span>
      <?php elseif($h['is_active']):?>
        <span class="badge badge-green">Active</span>
      <?php else:?>
        <span class="badge badge-red">Inactive</span>
      <?php endif;?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;font-size:0.8rem;margin-bottom:1rem;">
      <div style="color:var(--text-light);">📍 <?=htmlspecialchars($h['location']??'N/A')?></div>
      <div style="color:var(--text-light);">⭐ <?=number_format($h['rating_avg']??0,1)?>/5.0</div>
      <div style="color:var(--text-light);">🕒 <?=htmlspecialchars($h['years_experience']??0)?> yrs exp</div>
      <div style="color:var(--text-light);">📅 <?=date('d M Y',strtotime($h['created_at']))?></div>
    </div>

    <?php if($h['phone']):?><p style="font-size:0.8rem;color:var(--text-light);margin-bottom:0.75rem;">📞 <?=htmlspecialchars($h['phone'])?></p><?php endif;?>

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <?php if(!$h['is_approved']&&$h['is_active']):?>
      <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="approve">
        <input type="hidden" name="user_id" value="<?=$h['id']?>">
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
      </form>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Reject and deactivate this account?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="reject">
        <input type="hidden" name="user_id" value="<?=$h['id']?>">
        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-times"></i> Reject</button>
      </form>
      <?php else:?>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle account status?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="toggle">
        <input type="hidden" name="user_id" value="<?=$h['id']?>">
        <input type="hidden" name="current_active" value="<?=$h['is_active']?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-power-off"></i> <?=$h['is_active']?'Deactivate':'Reactivate'?></button>
      </form>
      <?php endif;?>
    </div>
  </div>
</div>
<?php endforeach;?>
<?php if(empty($herbalists)):?>
<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--text-light);">
  <div style="font-size:3rem;margin-bottom:1rem;">🌱</div>
  <p><?=$filter==='pending'?'No pending herbalist approvals.':'No herbalists found.'?></p>
</div>
<?php endif;?>
</div>

<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.5rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?filter=<?=$filter?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
</div></div></body></html>
