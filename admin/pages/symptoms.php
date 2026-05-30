<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');
$db=Database::connect();$flash=getFlash();$action=$_GET['action']??'list';$editId=(int)($_GET['id']??0);$errors=[];

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $act=$_POST['form_action']??'';
  if($act==='delete'){$id=(int)$_POST['symptom_id'];$db->prepare('DELETE FROM symptoms WHERE id=?')->execute([$id]);setFlash('success','Symptom deleted.');header('Location: '.APP_URL.'/admin/pages/symptoms.php');exit;}
  if(in_array($act,['add','edit'])){
    $name=sanitize($_POST['name']??'');$cat=sanitize($_POST['category']??'');$kw=sanitize($_POST['keywords']??'');
    if(empty($name))$errors[]='Name required.';
    if(empty($errors)){
      if($act==='add'){$db->prepare('INSERT INTO symptoms (name,category,keywords) VALUES (?,?,?)')->execute([$name,$cat,$kw]);setFlash('success','Symptom added.');}
      else{$db->prepare('UPDATE symptoms SET name=?,category=?,keywords=? WHERE id=?')->execute([$name,$cat,$kw,$editId]);setFlash('success','Symptom updated.');}
      header('Location: '.APP_URL.'/admin/pages/symptoms.php');exit;
    }
  }
}

$editSym=null;
if($action==='edit'&&$editId){$s=$db->prepare('SELECT * FROM symptoms WHERE id=?');$s->execute([$editId]);$editSym=$s->fetch();}
$symptoms=$db->query('SELECT * FROM symptoms ORDER BY category,name')->fetchAll();
$categories=$db->query("SELECT DISTINCT category FROM symptoms WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Symptoms — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><?=htmlspecialchars($errors[0])?></div><?php endif;?>
<div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;">
  <div><h2>Symptom Keywords</h2><p><?=count($symptoms)?> symptoms — used by the rule engine to match patient input</p></div>
  <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Symptom</a>
</div>

<?php if(in_array($action,['add','edit'])):?>
<div class="card" style="max-width:600px;margin-bottom:1.5rem;">
  <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><?=$action==='add'?'Add New Symptom':'Edit Symptom'?></h4></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="form_action" value="<?=$action?>">
      <div class="form-group"><label class="form-label">Symptom Name *</label><input type="text" name="name" class="form-control" required value="<?=htmlspecialchars($editSym['name']??$_POST['name']??'')?>" placeholder="e.g. headache"></div>
      <div class="form-group"><label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" list="cat-list" placeholder="e.g. respiratory, digestive, skin" value="<?=htmlspecialchars($editSym['category']??$_POST['category']??'')?>">
        <datalist id="cat-list"><?php foreach($categories as $c):?><option value="<?=htmlspecialchars($c)?>"><?php endforeach;?></datalist>
      </div>
      <div class="form-group"><label class="form-label">Keywords / Synonyms</label><input type="text" name="keywords" class="form-control" placeholder="comma-separated: headache,head pain,migraine" value="<?=htmlspecialchars($editSym['keywords']??$_POST['keywords']??'')?>"><p class="form-hint">These are matched against patient symptom input text</p></div>
      <div style="display:flex;gap:0.6rem;">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-<?=$action==='add'?'plus':'floppy-disk'?>"></i> <?=$action==='add'?'Add':'Save'?></button>
        <a href="?action=list" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif;?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;">
<?php
$grouped=[];foreach($symptoms as $s){$grouped[$s['category']??'Other'][]=$s;}
foreach($grouped as $cat=>$syms):?>
<div class="card">
  <div class="card-header"><h4 style="margin:0;font-size:0.85rem;text-transform:capitalize;"><i class="fa-solid fa-stethoscope" style="color:var(--green-mid);margin-right:0.4rem;"></i><?=htmlspecialchars($cat)?></h4></div>
  <div class="card-body" style="padding:0.75rem;">
    <?php foreach($syms as $s):?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:0.4rem 0;border-bottom:1px solid #F1F5F9;">
      <div>
        <span style="font-weight:600;font-size:0.88rem;"><?=htmlspecialchars($s['name'])?></span>
        <?php if($s['keywords']):?><div style="font-size:0.72rem;color:var(--text-light);margin-top:0.1rem;"><?=htmlspecialchars(substr($s['keywords'],0,50))?></div><?php endif;?>
      </div>
      <div style="display:flex;gap:0.3rem;">
        <a href="?action=edit&id=<?=$s['id']?>" class="btn btn-ghost btn-sm" style="padding:0.25rem 0.5rem;"><i class="fa-solid fa-pen" style="font-size:0.75rem;"></i></a>
        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this symptom?')">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="delete">
          <input type="hidden" name="symptom_id" value="<?=$s['id']?>">
          <button class="btn btn-danger btn-sm" style="padding:0.25rem 0.5rem;"><i class="fa-solid fa-trash" style="font-size:0.75rem;"></i></button>
        </form>
      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endforeach;?>
</div>
</div></div></body></html>
