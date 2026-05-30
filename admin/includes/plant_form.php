<?php
// ============================================================
//  Plant Form Partial — admin/includes/plant_form.php
//  Reused in both Add and Edit modals on plants.php
//  $formData is set when editing, otherwise defaults to empty
// ============================================================
$fd = $formData ?? [];
$val = fn($k, $d='') => htmlspecialchars($fd[$k] ?? $d);
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
  <div class="form-group">
    <label class="form-label">Common Name *</label>
    <input type="text" name="common_name" class="form-control" required
           placeholder="e.g. Ginger" value="<?= $val('common_name') ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Scientific Name *</label>
    <input type="text" name="scientific_name" class="form-control" required
           placeholder="e.g. Zingiber officinale" value="<?= $val('scientific_name') ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Local / Cameroonian Name</label>
    <input type="text" name="local_name" class="form-control"
           placeholder="e.g. Tangawisi" value="<?= $val('local_name') ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Plant Family</label>
    <select name="family_id" class="form-control form-select">
      <option value="">— Select Family —</option>
      <?php
      global $families;
      foreach ($families as $f):
        $sel = ($fd['family_id'] ?? '') == $f['id'] ? 'selected' : '';
      ?>
        <option value="<?= $f['id'] ?>" <?= $sel ?>><?= htmlspecialchars($f['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="form-label">Description</label>
  <textarea name="description" class="form-control" rows="2"
            placeholder="Brief overview of the plant…"><?= $val('description') ?></textarea>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
  <div class="form-group">
    <label class="form-label">Parts Used</label>
    <input type="text" name="parts_used" class="form-control"
           placeholder="e.g. Leaves, Root, Bark" value="<?= $val('parts_used') ?>">
  </div>
  <div class="form-group">
    <label class="form-label">Toxicity Level</label>
    <select name="toxicity_level" class="form-control form-select">
      <?php foreach (['none','low','moderate','high'] as $t): ?>
        <option value="<?= $t ?>" <?= ($fd['toxicity_level']??'none')===$t?'selected':'' ?>>
          <?= ucfirst($t) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="form-group">
  <label class="form-label">Medicinal Uses *</label>
  <textarea name="medicinal_uses" class="form-control" rows="3" required
            placeholder="Describe the health conditions this plant treats…"><?= $val('medicinal_uses') ?></textarea>
</div>

<div class="form-group">
  <label class="form-label">Preparation Method</label>
  <textarea name="preparation" class="form-control" rows="3"
            placeholder="Step-by-step preparation instructions…"><?= $val('preparation') ?></textarea>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
  <div class="form-group">
    <label class="form-label">Dosage Notes</label>
    <textarea name="dosage_notes" class="form-control" rows="2"
              placeholder="Recommended dosage…"><?= $val('dosage_notes') ?></textarea>
  </div>
  <div class="form-group">
    <label class="form-label">Contraindications / Risks</label>
    <textarea name="contraindications" class="form-control" rows="2"
              placeholder="Who should avoid this plant…"><?= $val('contraindications') ?></textarea>
  </div>
</div>

<div class="form-group">
  <label class="form-label">Plant Image</label>
  <input type="file" name="plant_image" class="form-control" accept="image/jpeg,image/png,image/webp">
  <?php if (!empty($fd['image_filename'])): ?>
    <div style="margin-top:0.5rem;display:flex;align-items:center;gap:0.5rem;">
      <img src="<?= APP_URL ?>/assets/images/plants/<?= htmlspecialchars($fd['image_filename']) ?>"
           style="height:48px;border-radius:6px;object-fit:cover;" alt="">
      <span style="font-size:0.8rem;color:var(--text-light);">Current image (upload new to replace)</span>
    </div>
  <?php endif; ?>
  <p class="form-hint">Max 5MB · JPG, PNG or WebP</p>
</div>
