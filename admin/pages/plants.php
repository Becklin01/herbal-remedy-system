<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('admin');

$db=$db=Database::connect();$flash=getFlash();$action=$_GET['action']??'list';$editId=(int)($_GET['id']??0);$errors=[];
$families=$db->query('SELECT * FROM plant_families ORDER BY name')->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!verifyCsrf($_POST['csrf_token']??'')){$errors[]='Invalid request.';}
  else{
    $act=$_POST['form_action']??'';
    if($act==='delete'){$delId=(int)$_POST['plant_id'];$db->prepare('UPDATE plants SET is_active=0 WHERE id=?')->execute([$delId]);logAction('DELETE_PLANT','plants',$delId,'');setFlash('success','Plant removed.');header('Location: '.APP_URL.'/admin/pages/plants.php');exit;}
    if($act==='toggle'){$togId=(int)$_POST['plant_id'];$togVal=(int)$_POST['current_active'];$db->prepare('UPDATE plants SET is_active=? WHERE id=?')->execute([!$togVal,$togId]);setFlash('success','Status updated.');header('Location: '.APP_URL.'/admin/pages/plants.php');exit;}
    if(in_array($act,['add','edit'])){
      $data=['common_name'=>sanitize($_POST['common_name']??''),'scientific_name'=>sanitize($_POST['scientific_name']??''),'local_name'=>sanitize($_POST['local_name']??''),'family_id'=>(int)($_POST['family_id']??0)?:null,'description'=>sanitize($_POST['description']??''),'parts_used'=>sanitize($_POST['parts_used']??''),'medicinal_uses'=>sanitize($_POST['medicinal_uses']??''),'preparation'=>sanitize($_POST['preparation']??''),'dosage_notes'=>sanitize($_POST['dosage_notes']??''),'contraindications'=>sanitize($_POST['contraindications']??''),'toxicity_level'=>sanitize($_POST['toxicity_level']??'none')];
      if(empty($data['common_name']))$errors[]='Common name required.';
      if(empty($data['scientific_name']))$errors[]='Scientific name required.';
      if(empty($data['medicinal_uses']))$errors[]='Medicinal uses required.';
      if(empty($errors)){
        $imageFilename=$_POST['existing_image']??null;
        if(!empty($_FILES['plant_image']['name'])){$up=uploadImage($_FILES['plant_image'],PLANT_IMG_DIR);if($up)$imageFilename=$up;else $errors[]='Image upload failed.';}
        if(empty($errors)){
          if($act==='add'){$db->prepare('INSERT INTO plants (common_name,scientific_name,local_name,family_id,description,parts_used,medicinal_uses,preparation,dosage_notes,contraindications,toxicity_level,image_filename,is_active,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)')->execute([...$data,$imageFilename,$_SESSION['user_id']]);setFlash('success','Plant added.');}
          else{$sql='UPDATE plants SET common_name=?,scientific_name=?,local_name=?,family_id=?,description=?,parts_used=?,medicinal_uses=?,preparation=?,dosage_notes=?,contraindications=?,toxicity_level=?'.($imageFilename?',image_filename=?':'').' WHERE id=?';$params=array_values($data);if($imageFilename)$params[]=$imageFilename;$params[]=$editId;$db->prepare($sql)->execute($params);setFlash('success','Plant updated.');}
          header('Location: '.APP_URL.'/admin/pages/plants.php');exit;
        }
      }
    }
  }
}

$editPlant=null;
if($action==='edit'&&$editId){$s=$db->prepare('SELECT * FROM plants WHERE id=?');$s->execute([$editId]);$editPlant=$s->fetch();if(!$editPlant){setFlash('danger','Not found.');header('Location: '.APP_URL.'/admin/pages/plants.php');exit;}}

$search=sanitize($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$perPage=10;
$where="WHERE p.is_active=1";$params=[];
if($search){$where.=" AND (p.common_name LIKE ? OR p.scientific_name LIKE ? OR p.medicinal_uses LIKE ?)";$s="%$search%";$params=[$s,$s,$s];}
$total=(int)$db->prepare("SELECT COUNT(*) FROM plants p $where")->execute($params)??0;
$cnt=$db->prepare("SELECT COUNT(*) FROM plants p $where");$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT p.*,f.name AS family_name FROM plants p LEFT JOIN plant_families f ON f.id=p.family_id $where ORDER BY p.created_at DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute($params);$plants=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Medicinal Plants — Admin</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper"><div class="main-content">

<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>

<?php if($action==='list'):?>
<div class="page-title" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
  <div><h2>Medicinal Plants</h2><p><?=$total?> plant(s) in the database</p></div>
  <a href="?action=add" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Plant</a>
</div>
<div class="card" style="margin-bottom:1rem;"><div class="card-body" style="padding:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
    <input type="hidden" name="action" value="list">
    <div class="input-icon-wrapper" style="flex:1;min-width:220px;">
      <i class="fa-solid fa-search input-icon"></i>
      <input type="text" name="q" class="form-control" placeholder="Search plants…" value="<?=htmlspecialchars($search)?>">
    </div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search):?><a href="?action=list" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div></div>
<div class="table-wrapper"><table>
  <thead><tr><th>Plant</th><th>Scientific Name</th><th>Family</th><th>Parts Used</th><th>Toxicity</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach($plants as $p):?>
  <tr>
    <td><div style="display:flex;align-items:center;gap:0.75rem;">
      <?php if($p['image_filename']):?><img src="<?=APP_URL?>/assets/images/plants/<?=htmlspecialchars($p['image_filename'])?>" style="width:38px;height:38px;border-radius:6px;object-fit:cover;" alt="">
      <?php else:?><div style="width:38px;height:38px;border-radius:6px;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.1rem;">🌿</div><?php endif;?>
      <div><div style="font-weight:600;"><?=htmlspecialchars($p['common_name'])?></div><?php if($p['local_name']):?><div style="font-size:0.75rem;color:var(--text-light);"><?=htmlspecialchars($p['local_name'])?></div><?php endif;?></div>
    </div></td>
    <td style="font-style:italic;font-size:0.88rem;"><?=htmlspecialchars($p['scientific_name'])?></td>
    <td><?=htmlspecialchars($p['family_name']??'—')?></td>
    <td style="font-size:0.82rem;"><?=htmlspecialchars($p['parts_used']??'—')?></td>
    <td><?php $tc=['none'=>'badge-green','low'=>'badge-green','moderate'=>'badge-gold','high'=>'badge-red'];?><span class="badge <?=$tc[$p['toxicity_level']]??'badge-gray'?>"><?=$p['toxicity_level']?></span></td>
    <td><span class="badge <?=$p['is_active']?'badge-green':'badge-red'?>"><?=$p['is_active']?'Active':'Inactive'?></span></td>
    <td><div style="display:flex;gap:0.4rem;">
      <a href="?action=edit&id=<?=$p['id']?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-pen"></i></a>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Toggle status?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="toggle">
        <input type="hidden" name="plant_id" value="<?=$p['id']?>">
        <input type="hidden" name="current_active" value="<?=$p['is_active']?>">
        <button class="btn btn-ghost btn-sm"><i class="fa-solid fa-power-off"></i></button>
      </form>
      <form method="POST" style="margin:0;" onsubmit="return confirm('Remove plant?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="delete">
        <input type="hidden" name="plant_id" value="<?=$p['id']?>">
        <button class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
      </form>
    </div></td>
  </tr>
  <?php endforeach;?>
  <?php if(empty($plants)):?><tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-light);"><?=$search?'No plants match your search.':'No plants yet. <a href="?action=add">Add the first one</a>.'?></td></tr><?php endif;?>
  </tbody>
</table></div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php if($pag['has_prev']):?><a href="?q=<?=urlencode($search)?>&page=<?=$page-1?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-chevron-left"></i></a><?php endif;?>
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?q=<?=urlencode($search)?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
  <?php if($pag['has_next']):?><a href="?q=<?=urlencode($search)?>&page=<?=$page+1?>" class="btn btn-ghost btn-sm"><i class="fa-solid fa-chevron-right"></i></a><?php endif;?>
</div>
<?php endif;?>

<?php else:?>
<!-- ADD / EDIT FORM -->
<div class="page-title" style="display:flex;justify-content:space-between;align-items:center;">
  <div><h2><?=$action==='add'?'Add New Plant':'Edit Plant'?></h2><p><?=$action==='add'?'Fill in the plant details below.':'Update the plant information.'?></p></div>
  <a href="?action=list" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>
<form method="POST" action="?action=<?=$action?><?=$editId?"&id=$editId":''?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
  <input type="hidden" name="form_action" value="<?=$action?>">
  <?php if($editPlant&&$editPlant['image_filename']):?><input type="hidden" name="existing_image" value="<?=htmlspecialchars($editPlant['image_filename'])?>"> <?php endif;?>
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;">
    <div>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Basic Information</h4></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group"><label class="form-label">Common Name *</label><input type="text" name="common_name" class="form-control" required value="<?=htmlspecialchars($editPlant['common_name']??$_POST['common_name']??'')?>"></div>
            <div class="form-group"><label class="form-label">Scientific Name *</label><input type="text" name="scientific_name" class="form-control" required value="<?=htmlspecialchars($editPlant['scientific_name']??$_POST['scientific_name']??'')?>" style="font-style:italic;"></div>
            <div class="form-group"><label class="form-label">Local Name</label><input type="text" name="local_name" class="form-control" value="<?=htmlspecialchars($editPlant['local_name']??$_POST['local_name']??'')?>"></div>
            <div class="form-group"><label class="form-label">Plant Family</label>
              <select name="family_id" class="form-control form-select"><option value="">— Select —</option>
                <?php foreach($families as $f):?><option value="<?=$f['id']?>" <?=(($editPlant['family_id']??null)==$f['id'])?'selected':''?>><?=htmlspecialchars($f['name'])?></option><?php endforeach;?>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Parts Used</label><input type="text" name="parts_used" class="form-control" placeholder="e.g. Leaves, Root, Bark" value="<?=htmlspecialchars($editPlant['parts_used']??$_POST['parts_used']??'')?>"></div>
          </div>
          <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?=htmlspecialchars($editPlant['description']??$_POST['description']??'')?></textarea></div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Medicinal Information</h4></div>
        <div class="card-body">
          <div class="form-group"><label class="form-label">Medicinal Uses *</label><textarea name="medicinal_uses" class="form-control" rows="4" required><?=htmlspecialchars($editPlant['medicinal_uses']??$_POST['medicinal_uses']??'')?></textarea></div>
          <div class="form-group"><label class="form-label">Preparation Method</label><textarea name="preparation" class="form-control" rows="4"><?=htmlspecialchars($editPlant['preparation']??$_POST['preparation']??'')?></textarea></div>
          <div class="form-group"><label class="form-label">Dosage Notes</label><textarea name="dosage_notes" class="form-control" rows="2"><?=htmlspecialchars($editPlant['dosage_notes']??$_POST['dosage_notes']??'')?></textarea></div>
        </div>
      </div>
    </div>
    <div>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Plant Image</h4></div>
        <div class="card-body">
          <?php if(!empty($editPlant['image_filename'])):?>
            <img src="<?=APP_URL?>/assets/images/plants/<?=htmlspecialchars($editPlant['image_filename'])?>" style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:0.75rem;" alt="">
          <?php else:?>
            <div style="width:100%;height:120px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin-bottom:0.75rem;">🌿</div>
          <?php endif;?>
          <label class="form-label">Upload Image</label>
          <input type="file" name="plant_image" class="form-control" accept="image/jpeg,image/png,image/webp">
          <p class="form-hint">JPG or PNG, max 5MB</p>
        </div>
      </div>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">Safety</h4></div>
        <div class="card-body">
          <div class="form-group"><label class="form-label">Toxicity Level</label>
            <select name="toxicity_level" class="form-control form-select">
              <?php foreach(['none','low','moderate','high'] as $t):?><option value="<?=$t?>" <?=(($editPlant['toxicity_level']??'none')===$t)?'selected':''?>><?=ucfirst($t)?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Contraindications</label><textarea name="contraindications" class="form-control" rows="4"><?=htmlspecialchars($editPlant['contraindications']??$_POST['contraindications']??'')?></textarea></div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:0.6rem;">
        <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="fa-solid fa-<?=$action==='add'?'plus':'floppy-disk'?>"></i> <?=$action==='add'?'Add Plant':'Save Changes'?></button>
        <a href="?action=list" class="btn btn-ghost btn-full">Cancel</a>
      </div>
    </div>
  </div>
</form>
<?php endif;?>
</div></div>
</body></html>
