<?php
$fd  = $formData ?? [];
$val = fn($k,$d='') => htmlspecialchars($fd[$k] ?? $d);
?>
<div class="form-group">
  <label class="form-label">Remedy Name *</label>
  <input type="text" name="name" class="form-control" required
         placeholder="e.g. Ginger and Honey Cough Remedy" value="<?= $val('name') ?>">
</div>
<div class="form-group">
  <label class="form-label">Target Illness / Condition *</label>
  <input type="text" name="target_illness" class="form-control" required
         placeholder="e.g. Cough, sore throat, cold" value="<?= $val('target_illness') ?>">
</div>
<div class="form-group">
  <label class="form-label">Ingredients *</label>
  <textarea name="ingredients" class="form-control" rows="3" required
            placeholder="List all plant ingredients and quantities…"><?= $val('ingredients') ?></textarea>
</div>
<div class="form-group">
  <label class="form-label">Preparation Instructions *</label>
  <textarea name="preparation" class="form-control" rows="5" required
            placeholder="Step 1: …&#10;Step 2: …&#10;Step 3: …"><?= $val('preparation') ?></textarea>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
  <div class="form-group">
    <label class="form-label">Dosage</label>
    <textarea name="dosage" class="form-control" rows="2"
              placeholder="How much and how often…"><?= $val('dosage') ?></textarea>
  </div>
  <div class="form-group">
    <label class="form-label">Warnings / Contraindications</label>
    <textarea name="warnings" class="form-control" rows="2"
              placeholder="Any side effects or who should avoid…"><?= $val('warnings') ?></textarea>
  </div>
</div>
