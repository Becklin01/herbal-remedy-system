<?php
// ============================================================
//  Herbalist — My Schedule
//  File: herbalist/pages/schedule.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('herbalist');
$db=$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];$errors=[];
$prof=$db->prepare('SELECT * FROM herbalist_profiles WHERE user_id=?');$prof->execute([$uid]);$profile=$prof->fetch();

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
    $start=sanitize($_POST['start_time']??'08:00');
    $end  =sanitize($_POST['end_time']??'17:00');
    $days =sanitize($_POST['available_days']??'');
    if($start>=$end){$errors[]='End time must be after start time.';}
    if(empty($days)){$errors[]='Please select at least one available day.';}
    if(empty($errors)){
        if($profile){
            $db->prepare('UPDATE herbalist_profiles SET start_time=?,end_time=?,available_days=? WHERE user_id=?')->execute([$start,$end,$days,$uid]);
        } else {
            $db->prepare('INSERT INTO herbalist_profiles (user_id,start_time,end_time,available_days) VALUES (?,?,?,?)')->execute([$uid,$start,$end,$days]);
        }
        setFlash('success','Schedule updated successfully.');
        header('Location: '.APP_URL.'/herbalist/pages/schedule.php');exit;
    }
    $prof=$db->prepare('SELECT * FROM herbalist_profiles WHERE user_id=?');$prof->execute([$uid]);$profile=$prof->fetch();
}

$selectedDays=explode(',',($profile['available_days']??'Mon,Tue,Wed,Thu,Fri'));
$dayOptions=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

// Current week appointments
$weekApts=$db->prepare("SELECT a.*,u.full_name AS patient_name FROM appointments a JOIN users u ON u.id=a.patient_id WHERE a.herbalist_id=? AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 6 DAY) AND a.status IN('pending','confirmed') ORDER BY a.appointment_date,a.appointment_time");
$weekApts->execute([$uid]);$weekApts=$weekApts->fetchAll();

// Group by date
$byDate=[];foreach($weekApts as $a){$byDate[$a['appointment_date']][]=$a;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Schedule — Herbalist Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="page-title"><h2><i class="fa-solid fa-clock" style="color:var(--green-mid);"></i> My Schedule</h2><p>Set your available days and working hours for patient bookings.</p></div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

  <!-- Schedule settings form -->
  <div class="card">
    <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-calendar-days"></i> Availability Settings</h4></div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <div class="form-group">
          <label class="form-label">Available Days</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.25rem;">
            <?php foreach($dayOptions as $day):$sel=in_array($day,$selectedDays);?>
            <label style="display:flex;align-items:center;gap:0.3rem;background:<?=$sel?'var(--green-pale)':'#F7FAFC'?>;border:1.5px solid <?=$sel?'var(--green-mid)':'#E2E8F0'?>;border-radius:6px;padding:0.4rem 0.9rem;cursor:pointer;font-size:0.85rem;font-weight:600;transition:all 0.2s;" id="sl-<?=$day?>">
              <input type="checkbox" value="<?=$day?>" <?=$sel?'checked':''?> style="display:none;" onchange="toggleDay('<?=$day?>')">
              <?=$day?>
            </label>
            <?php endforeach;?>
          </div>
          <input type="hidden" name="available_days" id="sched_days" value="<?=htmlspecialchars($profile['available_days']??'Mon,Tue,Wed,Thu,Fri')?>">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label class="form-label">Working Hours — Start</label>
            <input type="time" name="start_time" class="form-control" value="<?=htmlspecialchars($profile['start_time']??'08:00')?>">
          </div>
          <div class="form-group">
            <label class="form-label">Working Hours — End</label>
            <input type="time" name="end_time" class="form-control" value="<?=htmlspecialchars($profile['end_time']??'17:00')?>">
          </div>
        </div>
        <div style="background:var(--green-pale);border-radius:var(--radius-sm);padding:0.85rem;margin-bottom:1rem;">
          <p style="margin:0;font-size:0.82rem;color:var(--green-dark);"><strong>Current schedule:</strong><br>
          Days: <strong><?=htmlspecialchars($profile['available_days']??'Not set')?></strong><br>
          Hours: <strong><?=date('h:i A',strtotime($profile['start_time']??'08:00:00'))?> — <?=date('h:i A',strtotime($profile['end_time']??'17:00:00'))?></strong></p>
        </div>
        <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-floppy-disk"></i> Save Schedule</button>
      </form>
    </div>
  </div>

  <!-- This week's appointments -->
  <div class="card">
    <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-calendar-week"></i> This Week's Bookings</h4></div>
    <div class="card-body">
      <?php if(empty($byDate)):?>
        <div style="text-align:center;padding:2rem;color:var(--text-light);">
          <div style="font-size:2.5rem;margin-bottom:0.75rem;">📅</div>
          <p style="margin:0;">No appointments this week.</p>
        </div>
      <?php else:?>
        <?php foreach($byDate as $date=>$dayApts):?>
        <div style="margin-bottom:1.25rem;">
          <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:var(--text-light);letter-spacing:0.06em;margin-bottom:0.5rem;padding-bottom:0.3rem;border-bottom:1px solid #E2E8F0;">
            <?=date('l d F',strtotime($date))?>
            <?php if($date===date('Y-m-d')):?><span class="badge badge-green" style="font-size:0.65rem;margin-left:0.4rem;">Today</span><?php endif;?>
          </div>
          <?php foreach($dayApts as $a):?>
          <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0.6rem;background:<?=$a['status']==='confirmed'?'var(--green-pale)':'#FFFBEB'?>;border-radius:6px;margin-bottom:0.4rem;">
            <div style="font-weight:700;font-size:0.9rem;color:var(--green-dark);white-space:nowrap;"><?=date('h:i A',strtotime($a['appointment_time']))?></div>
            <div style="flex:1;font-size:0.85rem;font-weight:600;"><?=htmlspecialchars($a['patient_name'])?></div>
            <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green'];?><span class="badge <?=$bc[$a['status']]??'badge-gray'?>" style="font-size:0.7rem;"><?=$a['status']?></span>
            <a href="<?= APP_URL ?>/herbalist/pages/appointments.php?action=manage&id=<?=$a['id']?>" class="btn btn-ghost btn-sm" style="padding:0.2rem 0.5rem;font-size:0.75rem;"><i class="fa-solid fa-pen"></i></a>
          </div>
          <?php endforeach;?>
        </div>
        <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>
</div>
</div></div>
<script>
function toggleDay(day){
  const lbl=document.getElementById('sl-'+day);
  const cb=lbl.querySelector('input');
  cb.checked=!cb.checked;
  lbl.style.background=cb.checked?'var(--green-pale)':'#F7FAFC';
  lbl.style.borderColor=cb.checked?'var(--green-mid)':'#E2E8F0';
  const hidden=document.getElementById('sched_days');
  let days=hidden.value?hidden.value.split(',').filter(Boolean):[];
  if(cb.checked){if(!days.includes(day))days.push(day);}
  else{days=days.filter(d=>d!==day);}
  hidden.value=days.join(',');
}
</script>
</body></html>
