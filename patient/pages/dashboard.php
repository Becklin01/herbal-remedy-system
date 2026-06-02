<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');
$db=$db=Database::connect();$flash=getFlash();$user=getCurrentUser();$uid=$_SESSION['user_id'];

$stats['searches'] = (int)$db->prepare('SELECT COUNT(*) FROM search_history WHERE user_id=?')->execute([$uid]) ? $db->query("SELECT COUNT(*) FROM search_history WHERE user_id=$uid")->fetchColumn() : 0;
$s=$db->prepare('SELECT COUNT(*) FROM search_history WHERE user_id=?');$s->execute([$uid]);$stats['searches']=(int)$s->fetchColumn();
$s=$db->prepare('SELECT COUNT(*) FROM plant_scans WHERE user_id=?');$s->execute([$uid]);$stats['scans']=(int)$s->fetchColumn();
$s=$db->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=?');$s->execute([$uid]);$stats['appointments']=(int)$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='pending'");$s->execute([$uid]);$stats['pending']=(int)$s->fetchColumn();

$recentSearches=$db->prepare('SELECT * FROM search_history WHERE user_id=? ORDER BY searched_at DESC LIMIT 5');$recentSearches->execute([$uid]);$recentSearches=$recentSearches->fetchAll();
$upcomingApts=$db->prepare("SELECT a.*,u.full_name AS herbalist_name FROM appointments a JOIN users u ON u.id=a.herbalist_id WHERE a.patient_id=? AND a.status IN('pending','confirmed') AND a.appointment_date>=CURDATE() ORDER BY a.appointment_date ASC LIMIT 3");$upcomingApts->execute([$uid]);$upcomingApts=$upcomingApts->fetchAll();
$plants=$db->query('SELECT * FROM plants WHERE is_active=1 ORDER BY RAND() LIMIT 4')->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — Patient Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>

<div class="page-title">
  <h2>Good <?=date('H')<12?'Morning':(date('H')<17?'Afternoon':'Evening')?>, <?=htmlspecialchars(explode(' ',$user['full_name'])[0])?>! 👋</h2>
  <p>How are you feeling today? Start by checking your symptoms or identifying a plant.</p>
</div>

<!-- Quick action cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.75rem;">

  <!-- Check Symptoms -->
  <a href="<?= APP_URL ?>/patient/pages/symptom_checker.php" style="text-decoration:none;">
    <div class="card" style="border:2px solid var(--green-light);transition:all 0.2s;" 
         onmouseover="this.style.transform='translateY(-3px)'" 
         onmouseout="this.style.transform=''">
      <div class="card-body" style="text-align:center;padding:1.5rem 1rem;">
        <div style="margin-bottom:0.6rem;">
          <img src="<?= APP_URL ?>/assets/images/check_symptoms_icon.png" 
               alt="Check Symptoms" 
               style="width:60px;height:60px;object-fit:contain;">
        </div>
        <h4 style="font-size:0.95rem;margin-bottom:0.3rem;">Check Symptoms</h4>
        <p style="font-size:0.8rem;margin:0;">Describe how you feel and get herbal remedy suggestions</p>
      </div>
    </div>
  </a>

  <!-- Identify Plant (Updated with new icon) -->
  <a href="<?= APP_URL ?>/patient/pages/plant_detect.php" style="text-decoration:none;">
    <div class="card" style="border:2px solid var(--earth-light);transition:all 0.2s;" 
         onmouseover="this.style.transform='translateY(-3px)'" 
         onmouseout="this.style.transform=''">
      <div class="card-body" style="text-align:center;padding:1.5rem 1rem;">
        <div style="margin-bottom:0.6rem;">
          <img src="<?= APP_URL ?>/assets/images/identify_plant_icon.png" 
               alt="Identify Plant" 
               style="width:60px;height:60px;object-fit:contain;">
        </div>
        <h4 style="font-size:0.95rem;margin-bottom:0.3rem;">Identify Plant</h4>
        <p style="font-size:0.8rem;margin:0;">Upload a photo to identify a medicinal plant with AI</p>
      </div>
    </div>
  </a>
  <!-- Find Herbalist -->
  <a href="<?= APP_URL ?>/patient/pages/herbalists.php" style="text-decoration:none;">
    <div class="card" style="border:2px solid #e0e0e0;transition:all 0.2s;" 
         onmouseover="this.style.transform='translateY(-3px)'" 
         onmouseout="this.style.transform=''">
      <div class="card-body" style="text-align:center;padding:1.5rem 1rem;">
        <div style="margin-bottom:0.6rem;">
          <img src="<?= APP_URL ?>/assets/images/find_herbalist.png" 
               alt="Find Herbalist" 
               style="width:60px;height:60px;object-fit:contain;">
        </div>
        <h4 style="font-size:0.95rem;margin-bottom:0.3rem;">Find Herbalist</h4>
        <p style="font-size:0.8rem;margin:0;">Browse and book consultations with herbalists</p>
      </div>
    </div>
  </a>
  <!-- My Appointments -->
  <a href="<?= APP_URL ?>/patient/pages/my_appointments.php" style="text-decoration:none;">
    <div class="card" style="border:2px solid #ffeaa7;transition:all 0.2s;" 
         onmouseover="this.style.transform='translateY(-3px)'" 
         onmouseout="this.style.transform=''">
      <div class="card-body" style="text-align:center;padding:1.5rem 1rem;">
        <div style="margin-bottom:0.6rem;">
          <img src="<?= APP_URL ?>/assets/images/calendar_icon.png" 
               alt="My Appointments" 
               style="width:60px;height:60px;object-fit:contain;">
        </div>
        <h4 style="font-size:0.95rem;margin-bottom:0.3rem;">My Appointments</h4>
        <p style="font-size:0.8rem;margin:0;">0 pending appointment(s)</p>
      </div>
    </div>
  </a>
</div>

<!-- Stats row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem;">
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-magnifying-glass"></i></div><div class="stat-info"><h3><?=$stats['searches']?></h3><p>Symptom Searches</p></div></div>
  <div class="stat-card"><div class="stat-icon earth"><i class="fa-solid fa-camera"></i></div><div class="stat-info"><h3><?=$stats['scans']?></h3><p>Plant Scans</p></div></div>
  <div class="stat-card"><div class="stat-icon gold"><i class="fa-solid fa-calendar-check"></i></div><div class="stat-info"><h3><?=$stats['appointments']?></h3><p>Appointments</p></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div><div class="stat-info"><h3><?=$stats['pending']?></h3><p>Pending</p></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
  <!-- Upcoming appointments -->
  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-calendar-check"></i> Upcoming Appointments</h4>
      <a href="<?= APP_URL ?>/patient/pages/my_appointments.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body">
      <?php if(empty($upcomingApts)):?>
        <div style="text-align:center;padding:1.5rem;color:var(--text-light);">
          <div style="font-size:2rem;margin-bottom:0.5rem;">📅</div>
          <p style="margin:0;font-size:0.85rem;">No upcoming appointments</p>
          <a href="<?= APP_URL ?>/patient/pages/herbalists.php" class="btn btn-outline btn-sm" style="margin-top:0.75rem;">Book Now</a>
        </div>
      <?php else:?>
        <?php foreach($upcomingApts as $a):?>
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 0;border-bottom:1px solid #F1F5F9;">
          <div style="width:42px;height:42px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">🌱</div>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:0.88rem;"><?=htmlspecialchars($a['herbalist_name'])?></div>
            <div style="font-size:0.78rem;color:var(--text-light);"><?=date('D d M Y',strtotime($a['appointment_date']))?> at <?=date('h:i A',strtotime($a['appointment_time']))?></div>
          </div>
          <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green'];?><span class="badge <?=$bc[$a['status']]??'badge-gray'?>"><?=$a['status']?></span>
        </div>
        <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>

  <!-- Recent searches -->
  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-clock-rotate-left"></i> Recent Searches</h4>
      <a href="<?= APP_URL ?>/patient/pages/history.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body">
      <?php if(empty($recentSearches)):?>
        <div style="text-align:center;padding:1.5rem;color:var(--text-light);">
          <div style="font-size:2rem;margin-bottom:0.5rem;">🔍</div>
          <p style="margin:0;font-size:0.85rem;">No searches yet</p>
          <a href="<?= APP_URL ?>/patient/pages/symptom_checker.php" class="btn btn-outline btn-sm" style="margin-top:0.75rem;">Check Symptoms</a>
        </div>
      <?php else:?>
        <?php foreach($recentSearches as $s):?>
        <div style="padding:0.6rem 0;border-bottom:1px solid #F1F5F9;">
          <div style="font-size:0.87rem;font-weight:500;color:var(--text-dark);">"<?=htmlspecialchars(substr($s['symptom_input'],0,55))?><?=strlen($s['symptom_input'])>55?'…':''?>"</div>
          <div style="font-size:0.75rem;color:var(--text-light);margin-top:0.15rem;"><?=timeAgo($s['searched_at'])?> · <span class="badge badge-green" style="font-size:0.68rem;"><?=$s['source']?></span></div>
        </div>
        <?php endforeach;?>
      <?php endif;?>
    </div>
  </div>
</div>

<!-- Featured plants -->
<div class="card">
  <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-seedling"></i> Featured Medicinal Plants</h4></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
      <?php foreach($plants as $p):?>
      <div style="border:1px solid #E2E8F0;border-radius:10px;overflow:hidden;transition:all 0.2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
        <?php if($p['image_filename']):?>
          <img src="<?= APP_URL ?>/assets/images/plants/<?=htmlspecialchars($p['image_filename'])?>" style="width:100%;height:110px;object-fit:cover;" alt="">
        <?php else:?>
          <div style="width:100%;height:110px;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🌿</div>
        <?php endif;?>
        <div style="padding:0.75rem;">
          <div style="font-weight:700;font-size:0.9rem;"><?=htmlspecialchars($p['common_name'])?></div>
          <div style="font-size:0.75rem;font-style:italic;color:var(--text-light);"><?=htmlspecialchars($p['scientific_name'])?></div>
          <div style="font-size:0.78rem;color:var(--text-mid);margin-top:0.35rem;"><?=htmlspecialchars(substr($p['medicinal_uses'],0,60))?>…</div>
        </div>
      </div>
      <?php endforeach;?>
    </div>
  </div>
</div>
</div></div></body></html>
