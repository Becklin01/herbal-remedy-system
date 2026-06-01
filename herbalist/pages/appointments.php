<?php
// ============================================================
//  Herbalist — Appointments Management
//  File: herbalist/pages/appointments.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('herbalist');
$db=$db=Database::connect();$flash=getFlash();$uid=$_SESSION['user_id'];$errors=[];
$action=$_GET['action']??'list';$manageId=(int)($_GET['id']??0);

// ── Handle POST ───────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf($_POST['csrf_token']??'')){
    $act=$_POST['form_action']??'';
    $aid=(int)($_POST['apt_id']??0);

    // Verify this appointment belongs to this herbalist
    $chk=$db->prepare('SELECT * FROM appointments WHERE id=? AND herbalist_id=?');
    $chk->execute([$aid,$uid]);$apt=$chk->fetch();

    if(!$apt){setFlash('danger','Appointment not found.');header('Location: '.APP_URL.'/herbalist/pages/appointments.php');exit;}

    if($act==='confirm'){
        $db->prepare("UPDATE appointments SET status='confirmed' WHERE id=?")->execute([$aid]);
        logAction('CONFIRM_APPOINTMENT','appointments',$aid,'Herbalist confirmed appointment');
        setFlash('success','Appointment confirmed. The patient will be notified.');
        header('Location: '.APP_URL.'/herbalist/pages/appointments.php');exit;
    }
    if($act==='complete'){
        $notes=sanitize($_POST['notes']??'');
        $db->prepare("UPDATE appointments SET status='completed',notes=? WHERE id=?")->execute([$notes,$aid]);
        logAction('COMPLETE_APPOINTMENT','appointments',$aid,'Herbalist marked appointment complete');
        setFlash('success','Appointment marked as completed.');
        header('Location: '.APP_URL.'/herbalist/pages/appointments.php');exit;
    }
    if($act==='cancel'){
        $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$aid]);
        logAction('CANCEL_APPOINTMENT','appointments',$aid,'Herbalist cancelled appointment');
        setFlash('warning','Appointment cancelled.');
        header('Location: '.APP_URL.'/herbalist/pages/appointments.php');exit;
    }
    if($act==='save_notes'){
        $notes=sanitize($_POST['notes']??'');
        $db->prepare("UPDATE appointments SET notes=? WHERE id=?")->execute([$notes,$aid]);
        setFlash('success','Consultation notes saved.');
        header('Location: '.APP_URL.'/herbalist/pages/appointments.php?action=manage&id='.$aid);exit;
    }
}

// ── Manage single appointment ─────────────────────────────────
$manageApt=null;
if($action==='manage'&&$manageId){
    $s=$db->prepare("SELECT a.*,u.full_name AS patient_name,u.email AS patient_email,u.phone AS patient_phone FROM appointments a JOIN users u ON u.id=a.patient_id WHERE a.id=? AND a.herbalist_id=?");
    $s->execute([$manageId,$uid]);$manageApt=$s->fetch();
    if(!$manageApt){setFlash('danger','Appointment not found.');header('Location: '.APP_URL.'/herbalist/pages/appointments.php');exit;}
}

// ── List with filters ─────────────────────────────────────────
$filter=sanitize($_GET['status']??'all');$page=max(1,(int)($_GET['page']??1));$perPage=12;
$where="WHERE a.herbalist_id=$uid";if($filter!=='all')$where.=" AND a.status='$filter'";
$cnt=$db->query("SELECT COUNT(*) FROM appointments a $where")->fetchColumn();$total=(int)$cnt;
$pag=paginate($total,$perPage,$page);
$stmt=$db->prepare("SELECT a.*,u.full_name AS patient_name,u.phone FROM appointments a JOIN users u ON u.id=a.patient_id $where ORDER BY FIELD(a.status,'pending','confirmed','completed','cancelled'),a.appointment_date DESC LIMIT $perPage OFFSET {$pag['offset']}");
$stmt->execute();$apts=$stmt->fetchAll();
$counts=[];foreach(['all','pending','confirmed','completed','cancelled'] as $s){$w="WHERE a.herbalist_id=$uid".($s!=='all'?" AND a.status='$s'":'');$counts[$s]=(int)$db->query("SELECT COUNT(*) FROM appointments a $w")->fetchColumn();}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Appointments — Herbalist Portal</title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head><body>
<?php require __DIR__.'/../includes/sidebar.php';?>
<div class="main-wrapper"><div class="main-content">
<?php if($flash):?><div class="alert alert-<?=$flash['type']?>"><?=htmlspecialchars($flash['message'])?></div><?php endif;?>
<?php if(!empty($errors)):?><div class="alert alert-danger"><?=htmlspecialchars($errors[0])?></div><?php endif;?>

<?php if($action==='manage'&&$manageApt):?>
<!-- ══ MANAGE SINGLE APPOINTMENT ══ -->
<div class="page-title" style="display:flex;justify-content:space-between;align-items:center;">
  <div><h2>Appointment Details</h2><p>View and manage this consultation.</p></div>
  <a href="<?= APP_URL ?>/herbalist/pages/appointments.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
  <!-- Patient info -->
  <div class="card">
    <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-user-injured"></i> Patient Information</h4></div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;">
        <div style="width:52px;height:52px;border-radius:50%;background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:700;color:var(--green-dark);"><?=strtoupper(substr($manageApt['patient_name'],0,1))?></div>
        <div><h4 style="margin:0;"><?=htmlspecialchars($manageApt['patient_name'])?></h4><p style="margin:0;font-size:0.82rem;color:var(--text-light);"><?=htmlspecialchars($manageApt['patient_email'])?></p></div>
      </div>
      <?php if($manageApt['patient_phone']):?><p style="font-size:0.88rem;"><i class="fa-solid fa-phone" style="color:var(--green-mid);width:20px;"></i> <?=htmlspecialchars($manageApt['patient_phone'])?></p><?php endif;?>
      <p style="font-size:0.88rem;"><i class="fa-solid fa-calendar" style="color:var(--green-mid);width:20px;"></i> <?=date('l, d F Y',strtotime($manageApt['appointment_date']))?></p>
      <p style="font-size:0.88rem;"><i class="fa-solid fa-clock" style="color:var(--green-mid);width:20px;"></i> <?=date('h:i A',strtotime($manageApt['appointment_time']))?></p>
      <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red'];?>
      <p style="font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;"><i class="fa-solid fa-circle-info" style="color:var(--green-mid);width:20px;"></i> Status: <span class="badge <?=$bc[$manageApt['status']]??'badge-gray'?>"><?=$manageApt['status']?></span></p>
      <?php if($manageApt['reason']):?>
        <div style="background:var(--green-pale);border-radius:var(--radius-sm);padding:0.75rem;margin-top:0.5rem;">
          <p style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-light);margin-bottom:0.3rem;">Reason for visit</p>
          <p style="font-size:0.88rem;margin:0;"><?=htmlspecialchars($manageApt['reason'])?></p>
        </div>
      <?php endif;?>
    </div>
  </div>

  <!-- Actions + Notes -->
  <div>
    <?php if(in_array($manageApt['status'],['pending','confirmed'])):?>
    <div class="card" style="margin-bottom:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-bolt"></i> Quick Actions</h4></div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">
        <?php if($manageApt['status']==='pending'):?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="confirm">
          <input type="hidden" name="apt_id" value="<?=$manageApt['id']?>">
          <button class="btn btn-primary btn-full"><i class="fa-solid fa-circle-check"></i> Confirm Appointment</button>
        </form>
        <?php endif;?>
        <?php if($manageApt['status']==='confirmed'):?>
        <p style="font-size:0.82rem;color:var(--green-mid);"><i class="fa-solid fa-circle-check"></i> This appointment is confirmed.</p>
        <?php endif;?>
        <form method="POST" onsubmit="return confirm('Cancel this appointment?')">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="form_action" value="cancel">
          <input type="hidden" name="apt_id" value="<?=$manageApt['id']?>">
          <button class="btn btn-danger btn-full"><i class="fa-solid fa-times"></i> Cancel Appointment</button>
        </form>
      </div>
    </div>
    <?php endif;?>

    <!-- Consultation notes -->
    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-notes-medical"></i> Consultation Notes</h4></div>
      <div class="card-body">
        <?php if($manageApt['status']==='cancelled'):?>
          <p style="color:var(--text-light);font-size:0.88rem;">This appointment was cancelled.</p>
        <?php else:?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="apt_id" value="<?=$manageApt['id']?>">
          <?php if($manageApt['status']==='confirmed'):?>
            <input type="hidden" name="form_action" value="complete">
            <div class="form-group">
              <label class="form-label">Add consultation notes *</label>
              <textarea name="notes" class="form-control" rows="5" placeholder="Describe your findings, recommended herbs, preparation advice, follow-up instructions…" required><?=htmlspecialchars($manageApt['notes']??'')?></textarea>
              <p class="form-hint">These notes will be visible to the patient after you mark the appointment as completed.</p>
            </div>
            <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-check-double"></i> Mark as Completed & Save Notes</button>
          <?php elseif($manageApt['status']==='pending'):?>
            <input type="hidden" name="form_action" value="save_notes">
            <div class="form-group">
              <label class="form-label">Pre-consultation notes (optional)</label>
              <textarea name="notes" class="form-control" rows="4" placeholder="Any preparation notes for this patient…"><?=htmlspecialchars($manageApt['notes']??'')?></textarea>
            </div>
            <button type="submit" class="btn btn-outline btn-full"><i class="fa-solid fa-floppy-disk"></i> Save Notes</button>
          <?php else:?>
            <input type="hidden" name="form_action" value="save_notes">
            <div class="form-group">
              <label class="form-label">Consultation notes</label>
              <textarea name="notes" class="form-control" rows="5"><?=htmlspecialchars($manageApt['notes']??'')?></textarea>
            </div>
            <button type="submit" class="btn btn-outline btn-full"><i class="fa-solid fa-floppy-disk"></i> Update Notes</button>
          <?php endif;?>
        </form>
        <?php endif;?>
      </div>
    </div>
  </div>
</div>

<?php else:?>
<!-- ══ APPOINTMENTS LIST ══ -->
<div class="page-title"><h2><i class="fa-solid fa-calendar-check" style="color:var(--green-mid);"></i> Appointments</h2><p>Manage all your patient bookings.</p></div>
<div style="display:flex;gap:0.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
  <?php foreach($counts as $s=>$c):?>
  <a href="?status=<?=$s?>" class="btn <?=$filter===$s?'btn-primary':'btn-ghost'?> btn-sm"><?=ucfirst($s)?> (<?=$c?>)</a>
  <?php endforeach;?>
</div>

<?php if(empty($apts)):?>
<div style="text-align:center;padding:3rem;"><div style="font-size:3rem;margin-bottom:1rem;">📅</div><h3>No <?=$filter!=='all'?ucfirst($filter).' ':''?>Appointments</h3><p>When patients book with you, they will appear here.</p></div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;">
<?php foreach($apts as $a):?>
<div class="card">
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem;">
      <div style="text-align:center;background:var(--green-pale);border-radius:8px;padding:0.5rem 0.75rem;flex-shrink:0;">
        <div style="font-weight:700;font-size:1.1rem;color:var(--green-dark);"><?=date('d',strtotime($a['appointment_date']))?></div>
        <div style="font-size:0.65rem;color:var(--green-mid);font-weight:600;text-transform:uppercase;"><?=date('M',strtotime($a['appointment_date']))?></div>
      </div>
      <div style="flex:1;">
        <div style="font-weight:600;"><?=htmlspecialchars($a['patient_name'])?></div>
        <div style="font-size:0.78rem;color:var(--text-light);"><?=date('h:i A',strtotime($a['appointment_time']))?></div>
        <?php if($a['phone']):?><div style="font-size:0.75rem;color:var(--text-light);">📞 <?=htmlspecialchars($a['phone'])?></div><?php endif;?>
      </div>
      <?php $bc=['pending'=>'badge-gold','confirmed'=>'badge-green','completed'=>'badge-gray','cancelled'=>'badge-red'];?>
      <span class="badge <?=$bc[$a['status']]??'badge-gray'?>"><?=$a['status']?></span>
    </div>
    <?php if($a['reason']):?><p style="font-size:0.8rem;color:var(--text-mid);font-style:italic;margin-bottom:0.75rem;">"<?=htmlspecialchars(substr($a['reason'],0,70))?><?=strlen($a['reason'])>70?'…':''?>"</p><?php endif;?>
    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
      <a href="?action=manage&id=<?=$a['id']?>" class="btn btn-primary btn-sm btn-full"><i class="fa-solid fa-stethoscope"></i> Manage</a>
      <?php if($a['status']==='pending'):?>
      <form method="POST" style="margin:0;flex:1;" onsubmit="return confirm('Confirm this appointment?')">
        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
        <input type="hidden" name="form_action" value="confirm">
        <input type="hidden" name="apt_id" value="<?=$a['id']?>">
        <button class="btn btn-outline btn-sm btn-full"><i class="fa-solid fa-check"></i> Confirm</button>
      </form>
      <?php endif;?>
    </div>
  </div>
</div>
<?php endforeach;?>
</div>
<?php if($pag['total_pages']>1):?>
<div style="display:flex;justify-content:center;gap:0.4rem;margin-top:1.25rem;">
  <?php for($i=1;$i<=$pag['total_pages'];$i++):?><a href="?status=<?=$filter?>&page=<?=$i?>" class="btn <?=$i==$page?'btn-primary':'btn-ghost'?> btn-sm"><?=$i?></a><?php endfor;?>
</div>
<?php endif;?>
<?php endif;?>
<?php endif;?>
</div></div></body></html>
