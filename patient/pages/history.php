<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');
$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];
$page=max(1,(int)($_GET['page']??1));$perPage=10;
$cnt=$db->prepare('SELECT COUNT(*) FROM search_history WHERE user_id=?');$cnt->execute([$uid]);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare('SELECT * FROM search_history WHERE user_id=? ORDER BY searched_at DESC LIMIT '.$perPage.' OFFSET '.$pag['offset']);
$stmt->execute([$uid]);$searches=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Search History — Patient Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<div class="page-title"><h2><i class="fa-solid fa-clock-rotate-left" style="color:var(--green-mid);"></i> Search History</h2><p><?=$total?> symptom search(es) recorded</p></div>

<?php if(empty($searches)):?>
<div style="text-align:center;padding:3rem;">
  <div style="font-size:3rem;margin-bottom:1rem;">🔍</div>
  <h3>No Searches Yet</h3>
  <p>Your symptom search history will appear here after your first search.</p>
  <a href="<?= APP_URL ?>/patient/pages/symptom_checker.php" class="btn btn-primary" style="margin-top:0.75rem;"><i class="fa-solid fa-stethoscope"></i> Check Symptoms Now</a>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:1rem;">
<?php foreach($searches as $s):?>
<div class="card">
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
      <div style="flex:1;">
        <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;">
          <i class="fa-solid fa-stethoscope" style="color:var(--green-mid);"></i>
          <span style="font-weight:600;font-size:0.95rem;"><?=htmlspecialchars($s['symptom_input'])?></span>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
          <span class="badge <?=$s['source']==='hybrid'?'badge-green':($s['source']==='gemini'?'badge-gray':'badge-earth')?>" style="text-transform:capitalize;"><?=$s['source']?> result</span>
          <span style="font-size:0.78rem;color:var(--text-light);"><i class="fa-solid fa-clock"></i> <?=timeAgo($s['searched_at'])?> &nbsp;(<?=date('d M Y H:i',strtotime($s['searched_at']))?>) </span>
        </div>
        <?php if($s['gemini_response']):?>
        <details style="margin-top:0.75rem;">
          <summary style="cursor:pointer;font-size:0.85rem;font-weight:600;color:var(--green-mid);"><i class="fa-solid fa-robot"></i> View AI Response</summary>
          <div style="background:#F7FAFC;border-radius:var(--radius-sm);padding:1rem;margin-top:0.5rem;font-size:0.85rem;white-space:pre-wrap;line-height:1.7;max-height:300px;overflow-y:auto;"><?=htmlspecialchars($s['gemini_response'])?></div>
        </details>
        <?php endif;?>
      </div>
      <a href="<?= APP_URL ?>/patient/pages/symptom_checker.php" onclick="sessionStorage.setItem('prefill','<?=htmlspecialchars(addslashes($s['symptom_input']))?>')" class="btn btn-ghost btn-sm" style="flex-shrink:0;">
        <i class="fa-solid fa-rotate-right"></i> Search Again
      </a>
    </div>
  </div>
</div>
<?php endforeach;?>
</div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
<?php endif;?>
</div></div></body></html>
