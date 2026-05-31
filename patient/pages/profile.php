<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');
$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];$errors=[];$user=getCurrentUser();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $act=$_POST['form_action']??'';
  if($act==='profile'){
    $name=sanitize($_POST['full_name']??'');$phone=sanitize($_POST['phone']??'');
    if(empty($name)||strlen($name)<3){$errors[]='Full name must be at least 3 characters.';}
    if(empty($errors)){
      $db->prepare('UPDATE users SET full_name=?,phone=? WHERE id=?')->execute([$name,$phone,$uid]);
      $_SESSION['user_name']=$name;
      logAction('UPDATE_PROFILE','users',$uid,'Patient updated profile');
      setFlash('success','Profile updated successfully.');
      header('Location: '.APP_URL.'/patient/pages/profile.php');exit;
    }
  }
  if($act==='password'){
    $cur=$_POST['current_password']??'';$new=$_POST['new_password']??'';$con=$_POST['confirm_password']??'';
    if(!password_verify($cur,$user['password_hash'])){$errors[]='Current password is incorrect.';}
    if(strlen($new)<8){$errors[]='New password must be at least 8 characters.';}
    if($new!==$con){$errors[]='New passwords do not match.';}
    if(empty($errors)){
      $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$uid]);
      logAction('CHANGE_PASSWORD','users',$uid,'Patient changed password');
      setFlash('success','Password changed successfully.');
      header('Location: '.APP_URL.'/patient/pages/profile.php');exit;
    }
  }
}
$user=getCurrentUser();
// Stats
$s=$db->prepare('SELECT COUNT(*) FROM search_history WHERE user_id=?');$s->execute([$uid]);$searches=(int)$s->fetchColumn();
$s=$db->prepare('SELECT COUNT(*) FROM plant_scans WHERE user_id=?');$s->execute([$uid]);$scans=(int)$s->fetchColumn();
$s=$db->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?');$s->execute([$uid]);$apts=(int)$s->fetchColumn();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile — Patient Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="page-title"><h2><i class="fa-solid fa-user" style="color:var(--green-mid);"></i> My Profile</h2></div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">
  <!-- Left: avatar + stats -->
  <div>
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-body" style="text-align:center;padding:2rem 1.5rem;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--green-mid);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;margin:0 auto 1rem;"><?=strtoupper(substr($user['full_name'],0,1))?></div>
        <h3 style="margin:0 0 0.25rem;"><?=htmlspecialchars($user['full_name'])?></h3>
        <p style="margin:0 0 0.25rem;font-size:0.85rem;color:var(--text-light);"><?=htmlspecialchars($user['email'])?></p>
        <span class="badge badge-green">Patient</span>
        <p style="margin:0.75rem 0 0;font-size:0.78rem;color:var(--text-light);">Member since <?=date('d M Y',strtotime($user['created_at']))?></p>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.88rem;">Activity Summary</h4></div>
      <div class="card-body" style="padding:1rem;">
        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #F1F5F9;"><span style="font-size:0.85rem;">Symptom searches</span><strong><?=$searches?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid #F1F5F9;"><span style="font-size:0.85rem;">Plant scans</span><strong><?=$scans?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:0.5rem 0;"><span style="font-size:0.85rem;">Appointments</span><strong><?=$apts?></strong></div>
      </div>
    </div>
  </div>

  <!-- Right: edit forms -->
  <div>
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-pen"></i> Edit Profile Information</h4></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="profile">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Full Name *</label>
              <div class="input-icon-wrapper"><i class="fa-solid fa-user input-icon"></i><input type="text" name="full_name" class="form-control" required value="<?=htmlspecialchars($user['full_name'])?>"></div>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Email Address</label>
              <div class="input-icon-wrapper"><i class="fa-solid fa-envelope input-icon"></i><input type="email" class="form-control" value="<?=htmlspecialchars($user['email'])?>" disabled style="opacity:0.6;cursor:not-allowed;"></div>
              <p class="form-hint">Email address cannot be changed.</p>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Phone Number</label>
              <div class="input-icon-wrapper"><i class="fa-solid fa-phone input-icon"></i><input type="tel" name="phone" class="form-control" placeholder="+237 6XX XXX XXX" value="<?=htmlspecialchars($user['phone']??'')?>"></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-lock"></i> Change Password</h4></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="password">
          <div class="form-group">
            <label class="form-label">Current Password *</label>
            <div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="current_password" class="form-control" required></div>
          </div>
          <div class="form-group">
            <label class="form-label">New Password *</label>
            <div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="new_password" class="form-control" required placeholder="Minimum 8 characters"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password *</label>
            <div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="confirm_password" class="form-control" required></div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div></div></body></html>
