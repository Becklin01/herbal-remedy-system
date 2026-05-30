<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');

$db=$db=Database::connect();$flash=getFlash();$action=$_GET['action']??'list';$editId=(int)($_GET['id']??0);$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!verifyCsrf($_POST['csrf_token']??'')){$errors[]='Invalid request.';}
  else{
    $act=$_POST['form_action']??'';
    if($act==='delete'){$id=(int)$_POST['remedy_id'];$db->prepare('UPDATE remedies SET is_active=0 WHERE id=?')->execute([$id]);logAction('DELETE_REMEDY','remedies',$id,'');setFlash('success','Remedy removed.');header('Location: '.APP_URL.'/admin/pages/remedies.php');exit;}
    if(in_array($act,['add','edit'])){
      $data=['name'=>sanitize($_POST['name']??''),'target_illness'=>sanitize($_POST['target_illness']??''),'ingredients'=>sanitize($_POST['ingredients']??''),'preparation'=>sanitize($_POST['preparation']??''),'dosage'=>sanitize($_POST['dosage']??''),'effectiveness_notes'=>sanitize($_POST['effectiveness_notes']??''),'warnings'=>sanitize($_POST['warnings']??'')];
      if(empty($data['name']))$errors[]='Name required.';
      if(empty($data['target_illness']))$errors[]='Target illness required.';
      if(empty($data['ingredients']))$errors[]='Ingredients required.';
      if(empty($data['preparation']))$errors[]='Preparation required.';
      if(empty($errors)){
        if($act==='add'){$db->prepare('INSERT INTO remedies (name,target_illness,ingredients,preparation,dosage,effectiveness_notes,warnings,is_active,created_by) VALUES (?,?,?,?,?,?,?,1,?)')->execute([...$data,$_SESSION['user_id']]);setFlash('success','Remedy added.');}
        else{$db->prepare('UPDATE remedies SET name=?,target_illness=?,ingredients=?,preparation=?,dosage=?,effectiveness_notes=?,warnings=? WHERE id=?')->execute([...$data,$editId]);setFlash('success','Remedy updated.');}
        header('Location: '.APP_URL.'/admin/pages/remedies.php');exit;
      }
    }
  }
}

$editRemedy=null;
if($action==='edit'&&$editId){$s=$db->prepare('SELECT * FROM remedies WHERE id=?');$s->execute([$editId]);$editRemedy=$s->fetch();if(!$editRemedy){setFlash('danger','Not found.');header('Location: '.APP_URL.'/admin/pages/remedies.php');exit;}}

$search=sanitize($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$perPage=10;
$where="WHERE is_active=1";$params=[];
if($search){$where.=" AND (name LIKE ? OR target_illness LIKE ?)";$s="%$search%";$params=[$s,$s];}
$cnt=$db->prepare("SELECT COUNT(*) FROM remedies $where");$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT * FROM remedies $where ORDER BY created_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute($params);$remedies=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Remedies — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>

<?php if($action==='list'):?>
<div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
  <div><h2>Herbal Remedies</h2><p><?=$total?> remedy(ies) in the database</p></div>
  <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Remedy</a>
</div>
<div class="card" style="margin-bottom:1rem;"><div class="card-body" style="padding:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    <input type="hidden" name="action" value="list">
    <div class="input-icon-wrapper" style="flex:1;"><i class="fa-solid fa-search input-icon"></i><input type="text" name="q" class="form-control" placeholder="Search remedies…" value="<?=htmlspecialchars($search)?>"></div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search):?><a href="?action=list" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div></div>
<div class="table-wrapper"><table>
  <thead><tr><th>Remedy Name</th><th>Target Illness</th><th>Ingredients Preview</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($remedies as $r):?>
  <tr>
    <td><div style="font-weight:600;"><?=htmlspecialchars($r['name'])?></div></td>
    <td><span class="badge badge-green" style="text-transform:none;"><?=htmlspecialchars($r['target_illness'])?></span></td>
    <td style="font-size:0.82rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?=htmlspecialchars(substr($r['ingredients'],0,80)).(strlen($r['ingredients'])>80?'…':'')?></td>
    <td><span class="badge <?=$r['is_active']?'badge-green':'badge-red'?>"><?=$r['is_active']?'Active':'Inactive'?></span></td>
    <td><div style="display:flex;gap:0.4rem;">
      <a href="?action=edit&id=<?=$r['id']?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i></a>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Remove remedy?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="delete">
        <input type="hidden" name="remedy_id" value="<?=$r['id']?>">
        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
      </form>
    </div></td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($remedies)):?><tr><td colspan="5" style="text-align:center;padding:2.5rem;color:var(--text-light);">No remedies found.</td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?q=<?=urlencode($search)?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>

<?php else:?>
<div class="page-title" style="display:flex;justify-content:space-between;align-items:center;">
  <div><h2><?=$action==='add'?'Add New Remedy':'Edit Remedy'?></h2></div>
  <a href="?action=list" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>
<form method="POST" action="?action=<?=$action?><?=$editId?"&id=$editId":''?>">
  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
  <input type="hidden" name="form_action" value="<?=$action?>">
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
    <div>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Remedy Details</h4></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Remedy Name *</label><input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($editRemedy['name']??$_POST['name']??'')?>"></div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Target Illness / Condition *</label><input type="text" name="target_illness" class="form-control" required placeholder="e.g. Cough, Malaria, Fever" value="<?=htmlspecialchars($editRemedy['target_illness']??$_POST['target_illness']??'')?>"></div>
          </div>
          <div class="form-group"><label class="form-label">Ingredients *</label><textarea name="ingredients" class="form-control" rows="4" required placeholder="List all plant ingredients and quantities…"><?=htmlspecialchars($editRemedy['ingredients']??$_POST['ingredients']??'')?></textarea></div>
          <div class="form-group"><label class="form-label">Preparation Instructions *</label><textarea name="preparation" class="form-control" rows="6" required placeholder="Step-by-step preparation method…"><?=htmlspecialchars($editRemedy['preparation']??$_POST['preparation']??'')?></textarea></div>
        </div>
      </div>
    </div>
    <div>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Additional Info</h4></div>
        <div class="card-body">
          <div class="form-group"><label class="form-label">Dosage</label><textarea name="dosage" class="form-control" rows="3" placeholder="How much, how often…"><?=htmlspecialchars($editRemedy['dosage']??$_POST['dosage']??'')?></textarea></div>
          <div class="form-group"><label class="form-label">Effectiveness Notes</label><textarea name="effectiveness_notes" class="form-control" rows="3" placeholder="Clinical or traditional evidence…"><?=htmlspecialchars($editRemedy['effectiveness_notes']??$_POST['effectiveness_notes']??'')?></textarea></div>
          <div class="form-group"><label class="form-label">Warnings</label><textarea name="warnings" class="form-control" rows="3" placeholder="Side effects, who to avoid…"><?=htmlspecialchars($editRemedy['warnings']??$_POST['warnings']??'')?></textarea></div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem;">
        <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="fa-solid fa-<?=$action==='add'?'plus':'floppy-disk'?>"></i> <?=$action==='add'?'Add Remedy':'Save Changes'?></button>
        <a href="?action=list" class="btn btn-ghost btn-full">Cancel</a>
      </div>
    </div>
  </div>
</form>
<?php endif;?>
</div></div></body></html>
