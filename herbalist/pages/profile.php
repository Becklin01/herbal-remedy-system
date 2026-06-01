<?php
// ============================================================
//  Herbalist — Profile Setup
//  File: herbalist/pages/profile.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('herbalist');
$db=$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];$errors=[];
$user=getCurrentUser();
$prof=$db->prepare('SELECT * FROM herbalist_profiles WHERE user_id=?');$prof->execute([$uid]);$profile=$prof->fetch();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
    $act=$_POST['form_action']??'';

    if($act==='profile'){
        $name=sanitize($_POST['full_name']??'');
        $phone=sanitize($_POST['phone']??'');
        if(strlen($name)<3)$errors[]='Full name must be at least 3 characters.';
        if(empty($errors)){
            $db->prepare('UPDATE users SET full_name=?,phone=? WHERE id=?')->execute([$name,$phone,$uid]);
            $_SESSION['user_name']=$name;
            setFlash('success','Personal info updated.');
            header('Location: '.APP_URL.'/herbalist/pages/profile.php');exit;
        }
    }

    if($act==='professional'){
        $spec  =sanitize($_POST['specialisation']??'');
        $bio   =sanitize($_POST['bio']??'');
        $loc   =sanitize($_POST['location']??'');
        $yrs   =(int)($_POST['years_experience']??0);
        $fee   =(float)($_POST['consultation_fee']??0);
        $days  =sanitize($_POST['available_days']??'');

        // Upsert herbalist profile
        $exists=$db->prepare('SELECT id FROM herbalist_profiles WHERE user_id=?');$exists->execute([$uid]);
        if($exists->fetch()){
            $db->prepare('UPDATE herbalist_profiles SET specialisation=?,bio=?,location=?,years_experience=?,consultation_fee=?,available_days=? WHERE user_id=?')
               ->execute([$spec,$bio,$loc,$yrs,$fee,$days,$uid]);
        } else {
            $db->prepare('INSERT INTO herbalist_profiles (user_id,specialisation,bio,location,years_experience,consultation_fee,available_days) VALUES (?,?,?,?,?,?,?)')
               ->execute([$uid,$spec,$bio,$loc,$yrs,$fee,$days]);
        }
        logAction('UPDATE_HERBALIST_PROFILE','herbalist_profiles',$uid,'');
        setFlash('success','Professional profile updated successfully.');
        header('Location: '.APP_URL.'/herbalist/pages/profile.php');exit;
    }

    if($act==='password'){
        $cur=$_POST['current_password']??'';$new=$_POST['new_password']??'';$con=$_POST['confirm_password']??'';
        if(!password_verify($cur,$user['password_hash']))$errors[]='Current password is incorrect.';
        if(strlen($new)<8)$errors[]='New password must be at least 8 characters.';
        if($new!==$con)$errors[]='Passwords do not match.';
        if(empty($errors)){
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_BCRYPT,['cost'=>12]),$uid]);
            setFlash('success','Password changed successfully.');
            header('Location: '.APP_URL.'/herbalist/pages/profile.php');exit;
        }
    }
}
$user=getCurrentUser();$prof=$db->prepare('SELECT * FROM herbalist_profiles WHERE user_id=?');$prof->execute([$uid]);$profile=$prof->fetch();
$completionFields=['specialisation','bio','location','years_experience','consultation_fee'];
$filled=count(array_filter($completionFields,fn($f)=>!empty($profile[$f])));
$completion=round(($filled/count($completionFields))*100);

$dayOptions=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$selectedDays=explode(',',($profile['available_days']??''));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile — Herbalist Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="page-title"><h2><i class="fa-solid fa-user-nurse" style="color:var(--green-mid);"></i> My Profile</h2></div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;">
  <!-- Left: avatar + completion -->
  <div>
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-body" style="text-align:center;padding:2rem 1.25rem;">
        <div style="width:80px;height:80px;border-radius:50%;background:var(--green-mid);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#fff;margin:0 auto 1rem;"><?=strtoupper(substr($user['full_name'],0,1))?></div>
        <h3 style="margin:0 0 0.2rem;font-size:1rem;"><?=htmlspecialchars($user['full_name'])?></h3>
        <p style="margin:0 0 0.3rem;font-size:0.82rem;color:var(--text-light);"><?=htmlspecialchars($profile['specialisation']??'No specialisation set')?></p>
        <span class="badge badge-earth">Herbalist</span>
        <p style="margin:0.75rem 0 0;font-size:0.75rem;color:var(--text-light);">Member since <?=date('d M Y',strtotime($user['created_at']))?></p>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.88rem;">Profile Completion</h4></div>
      <div class="card-body" style="padding:1rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;"><span style="font-size:0.82rem;">Completeness</span><strong style="color:<?=$completion>=80?'var(--green-mid)':'#D69E2E'?>"><?=$completion?>%</strong></div>
        <div style="height:8px;background:#E2E8F0;border-radius:99px;overflow:hidden;margin-bottom:0.75rem;"><div style="height:100%;width:<?=$completion?>%;background:<?=$completion>=80?'var(--green-light)':'#F6AD55'?>;border-radius:99px;transition:width 0.5s;"></div></div>
        <?php foreach(['specialisation'=>'Specialisation','bio'=>'Bio','location'=>'Location','years_experience'=>'Experience','consultation_fee'=>'Consultation fee'] as $f=>$label):?>
        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.3rem;font-size:0.8rem;">
          <i class="fa-solid fa-<?=!empty($profile[$f])?'circle-check':'circle-xmark'?>" style="color:<?=!empty($profile[$f])?'var(--green-mid)':'#CBD5E0'?>;"></i>
          <span style="color:<?=!empty($profile[$f])?'var(--text-dark)':'var(--text-light)'?>"><?=$label?></span>
        </div>
        <?php endforeach;?>
      </div>
    </div>
  </div>

  <!-- Right: forms -->
  <div>
    <!-- Personal info -->
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-user"></i> Personal Information</h4></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="profile">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Full Name *</label><div class="input-icon-wrapper"><i class="fa-solid fa-user input-icon"></i><input type="text" name="full_name" class="form-control" required value="<?=htmlspecialchars($user['full_name'])?>"></div></div>
            <div class="form-group"><label class="form-label">Email (read-only)</label><input type="email" class="form-control" value="<?=htmlspecialchars($user['email'])?>" disabled style="opacity:0.6;cursor:not-allowed;"></div>
            <div class="form-group"><label class="form-label">Phone Number</label><div class="input-icon-wrapper"><i class="fa-solid fa-phone input-icon"></i><input type="tel" name="phone" class="form-control" value="<?=htmlspecialchars($user['phone']??'')?>"></div></div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Personal Info</button>
        </form>
      </div>
    </div>

    <!-- Professional profile -->
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-stethoscope"></i> Professional Profile</h4></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="professional">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Specialisation</label><input type="text" name="specialisation" class="form-control" placeholder="e.g. Respiratory conditions, Digestive health, Skin treatments" value="<?=htmlspecialchars($profile['specialisation']??'')?>"></div>
            <div class="form-group"><label class="form-label">Location / City</label><div class="input-icon-wrapper"><i class="fa-solid fa-location-dot input-icon"></i><input type="text" name="location" class="form-control" placeholder="e.g. Yaoundé, Cameroon" value="<?=htmlspecialchars($profile['location']??'')?>"></div></div>
            <div class="form-group"><label class="form-label">Years of Experience</label><input type="number" name="years_experience" class="form-control" min="0" max="60" value="<?=htmlspecialchars($profile['years_experience']??0)?>"></div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Consultation Fee (XAF)</label><div class="input-icon-wrapper"><i class="fa-solid fa-money-bill input-icon"></i><input type="number" name="consultation_fee" class="form-control" min="0" placeholder="0 for free consultations" value="<?=htmlspecialchars($profile['consultation_fee']??0)?>"></div></div>
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Bio / About You</label><textarea name="bio" class="form-control" rows="4" placeholder="Describe your background, expertise, and approach to herbal medicine…"><?=htmlspecialchars($profile['bio']??'')?></textarea></div>
            <div class="form-group" style="grid-column:1/-1;">
              <label class="form-label">Available Days</label>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.25rem;">
                <?php foreach($dayOptions as $day):?>
                <label style="display:flex;align-items:center;gap:0.3rem;background:<?=in_array($day,$selectedDays)?'var(--green-pale)':'#F7FAFC'?>;border:1.5px solid <?=in_array($day,$selectedDays)?'var(--green-mid)':'#E2E8F0'?>;border-radius:6px;padding:0.35rem 0.7rem;cursor:pointer;font-size:0.82rem;font-weight:600;transition:all 0.2s;" id="label-<?=$day?>">
                  <input type="checkbox" name="days[]" value="<?=$day?>" <?=in_array($day,$selectedDays)?'checked':''?> style="display:none;" onchange="updateDay('<?=$day?>')">
                  <?=$day?>
                </label>
                <?php endforeach;?>
              </div>
              <input type="hidden" name="available_days" id="available_days_hidden" value="<?=htmlspecialchars($profile['available_days']??'')?>">
            </div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Professional Profile</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-lock"></i> Change Password</h4></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="password">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Current Password</label><div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="current_password" class="form-control" required></div></div>
            <div class="form-group"><label class="form-label">New Password</label><div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="new_password" class="form-control" required placeholder="Min 8 characters"></div></div>
            <div class="form-group"><label class="form-label">Confirm New Password</label><div class="input-icon-wrapper"><i class="fa-solid fa-lock input-icon"></i><input type="password" name="confirm_password" class="form-control" required></div></div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key"></i> Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>
</div></div>
<script>
function updateDay(day){
  const cb=document.querySelector('input[value="'+day+'"]');
  const lbl=document.getElementById('label-'+day);
  const hidden=document.getElementById('available_days_hidden');
  let days=hidden.value?hidden.value.split(',').filter(Boolean):[];
  if(cb.checked){lbl.style.background='var(--green-pale)';lbl.style.borderColor='var(--green-mid)';if(!days.includes(day))days.push(day);}
  else{lbl.style.background='#F7FAFC';lbl.style.borderColor='#E2E8F0';days=days.filter(d=>d!==day);}
  hidden.value=days.join(',');
}
// Sync checkboxes with hidden field on load
document.querySelectorAll('input[name="days[]"]').forEach(cb=>{
  cb.addEventListener('change',()=>updateDay(cb.value));
});
</script>
</body></html>
