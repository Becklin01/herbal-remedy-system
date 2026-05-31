<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');
$db=$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];$errors=[];

// Handle booking POST
if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
  $hid=(int)($_POST['herbalist_id']??0);$date=sanitize($_POST['apt_date']??'');$time=sanitize($_POST['apt_time']??'');$reason=sanitize($_POST['reason']??'');
  if(!$hid)$errors[]='Invalid herbalist.';
  if(empty($date)||strtotime($date)<strtotime('today'))$errors[]='Please choose a future date.';
  if(empty($time))$errors[]='Please choose a time.';
  if(empty($errors)){
    // Check no duplicate booking
    $chk=$db->prepare("SELECT id FROM appointments WHERE patient_id=? AND herbalist_id=? AND appointment_date=? AND status NOT IN('cancelled')");
    $chk->execute([$uid,$hid,$date]);
    if($chk->fetch()){$errors[]='You already have a booking with this herbalist on that date.';}
    else{
      $db->prepare('INSERT INTO appointments (patient_id,herbalist_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,?)')->execute([$uid,$hid,$date,$time,$reason,'pending']);
      setFlash('success','Appointment booked! The herbalist will confirm shortly.');
      header('Location: '.APP_URL.'/patient/pages/my_appointments.php');exit;
    }
  }
}

$bookHerbalistId=(int)($_GET['book']??0);
$search=sanitize($_GET['q']??'');$page=max(1,(int)($_GET['page']??1));$perPage=9;
$where="WHERE u.role='herbalist' AND u.is_active=1 AND u.is_approved=1";$params=[];
if($search){$where.=" AND (u.full_name LIKE ? OR hp.specialisation LIKE ? OR hp.location LIKE ?)";$s="%$search%";$params=[$s,$s,$s];}
$cnt=$db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN herbalist_profiles hp ON hp.user_id=u.id $where");$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT u.*,hp.specialisation,hp.location,hp.years_experience,hp.rating_avg,hp.consultation_fee,hp.bio,hp.available_days,hp.start_time,hp.end_time FROM users u LEFT JOIN herbalist_profiles hp ON hp.user_id=u.id $where ORDER BY hp.rating_avg DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute($params);$herbalists=$stmt->fetchAll();

// Load herbalist for booking modal
$bookingHerbalist=null;
if($bookHerbalistId){$s=$db->prepare("SELECT u.*,hp.* FROM users u LEFT JOIN herbalist_profiles hp ON hp.user_id=u.id WHERE u.id=? AND u.role='herbalist' AND u.is_active=1 AND u.is_approved=1");$s->execute([$bookHerbalistId]);$bookingHerbalist=$s->fetch();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Find Herbalists — Patient Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:var(--radius-lg);padding:2rem;max-width:480px;width:90%;max-height:90vh;overflow-y:auto;}
</style>
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><ul style="margin:0;padding-left:1.2rem;"><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>

<div class="page-title"><h2><i class="fa-solid fa-user-nurse" style="color:var(--green-mid);"></i> Find a Herbalist</h2><p>Browse verified herbalists and book a consultation.</p></div>

<div class="card" style="margin-bottom:1.25rem;"><div class="card-body" style="padding:1rem;">
  <form method="GET" style="display:flex;gap:0.75rem;">
    <div class="input-icon-wrapper" style="flex:1;"><i class="fa-solid fa-search input-icon"></i><input type="text" name="q" class="form-control" placeholder="Search by name, specialisation or location…" value="<?=htmlspecialchars($search)?>"></div>
    <button class="btn btn-primary btn-sm">Search</button>
    <?php if($search):?><a href="?" class="btn btn-ghost btn-sm">Clear</a><?php endif;?>
  </form>
</div></div>

<?php if($total===0):?>
<div style="text-align:center;padding:3rem;"><div style="font-size:3rem;margin-bottom:1rem;">🌱</div><h3>No Herbalists Found</h3><p><?=$search?'Try a different search term.':'No verified herbalists are registered yet.'?></p></div>
<?php else:?>
<p style="font-size:0.85rem;color:var(--text-light);margin-bottom:1rem;"><?=$total?> herbalist(s) available</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:1.25rem;margin-bottom:1.5rem;">
<?php foreach($herbalists as $h):?>
<div class="card">
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;gap:1rem;margin-bottom:1rem;">
      <div style="width:52px;height:52px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:700;color:var(--green-dark);flex-shrink:0;"><?=strtoupper(substr($h['full_name'],0,1))?></div>
      <div style="flex:1;min-width:0;">
        <h4 style="margin:0 0 0.2rem;font-size:1rem;"><?=htmlspecialchars($h['full_name'])?></h4>
        <?php if($h['specialisation']):?><p style="margin:0;font-size:0.8rem;color:var(--green-mid);font-weight:600;"><?=htmlspecialchars($h['specialisation'])?></p><?php endif;?>
        <div style="display:flex;align-items:center;gap:0.25rem;margin-top:0.25rem;">
          <?php $rat=min(5,round($h['rating_avg']??0));for($i=1;$i<=5;$i++):?><i class="fa-solid fa-star" style="font-size:0.7rem;color:<?=$i<=$rat?'#D4A017':'#E2E8F0'?>;"></i><?php endfor;?>
          <span style="font-size:0.75rem;color:var(--text-light);margin-left:0.25rem;"><?=number_format($h['rating_avg']??0,1)?></span>
        </div>
      </div>
    </div>
    <?php if($h['bio']):?><p style="font-size:0.82rem;color:var(--text-mid);margin-bottom:0.75rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"><?=htmlspecialchars($h['bio'])?></p><?php endif;?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.3rem;font-size:0.78rem;color:var(--text-light);margin-bottom:1rem;">
      <span>📍 <?=htmlspecialchars($h['location']??'N/A')?></span>
      <span>🕒 <?=htmlspecialchars($h['years_experience']??0)?> yrs exp</span>
      <?php if($h['consultation_fee']>0):?><span>💰 <?=number_format($h['consultation_fee'],0)?> XAF</span><?php endif;?>
      <?php if($h['available_days']):?><span>📅 <?=htmlspecialchars($h['available_days'])?></span><?php endif;?>
    </div>
    <a href="?book=<?=$h['id']?><?=$search?"&q=".urlencode($search):''?>" class="btn btn-primary btn-full btn-sm"><i class="fa-solid fa-calendar-plus"></i> Book Appointment</a>
  </div>
</div>
<?php endforeach;?>
</div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?q=<?=urlencode($search)?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
<?php endif;?>
</div></div>

<!-- BOOKING MODAL -->
<div class="modal-overlay <?=$bookingHerbalist?'open':''?>" id="bookingModal">
  <div class="modal-box">
    <?php if($bookingHerbalist):?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
      <h3 style="margin:0;font-size:1.1rem;">Book Appointment</h3>
      <a href="?" style="color:var(--text-light);text-decoration:none;font-size:1.2rem;"><i class="fa-solid fa-times"></i></a>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;background:var(--green-pale);border-radius:var(--radius-sm);margin-bottom:1.25rem;">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--green-mid);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;"><?=strtoupper(substr($bookingHerbalist['full_name'],0,1))?></div>
      <div><div style="font-weight:600;"><?=htmlspecialchars($bookingHerbalist['full_name'])?></div><div style="font-size:0.8rem;color:var(--text-light);"><?=htmlspecialchars($bookingHerbalist['specialisation']??'General Herbalist')?></div></div>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="herbalist_id" value="<?=$bookingHerbalist['id']?>">
      <div class="form-group"><label class="form-label">Preferred Date *</label><input type="date" name="apt_date" class="form-control" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required value="<?=htmlspecialchars($_POST['apt_date']??'')?>"></div>
      <div class="form-group"><label class="form-label">Preferred Time *</label>
        <select name="apt_time" class="form-control form-select" required>
          <option value="">— Select a time —</option>
          <?php for($h2=8;$h2<=17;$h2++): foreach(['00','30'] as $m): $t=sprintf('%02d:%s:00',$h2,$m); ?><option value="<?=$t?>" <?=($_POST['apt_time']??'')===$t?'selected':''?>><?=date('h:i A',strtotime($t))?></option><?php endforeach; endfor;?>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Reason for Consultation</label><textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe your health concern…"><?=htmlspecialchars($_POST['reason']??'')?></textarea></div>
      <div style="display:flex;gap:0.6rem;">
        <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-calendar-check"></i> Confirm Booking</button>
        <a href="?" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
    <?php endif;?>
  </div>
</div>
</body></html>
