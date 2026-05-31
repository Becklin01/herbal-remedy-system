<?php
// ============================================================
//  Patient — Plant Detection (AI Image Upload)
//  File: patient/pages/plant_detect.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');

$db     = Database::connect();
$uid    = $_SESSION['user_id'];
$result = null;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {

    if (empty($_FILES['plant_image']['name'])) {
        $error = 'Please select an image to upload.';
    } else {
        // Save uploaded image
        $filename = uploadImage($_FILES['plant_image'], UPLOAD_DIR);
        if (!$filename) {
            $error = 'Upload failed. Please use a JPG or PNG image under 5MB.';
        } else {
            // Call Python microservice
            $modelResponse = null;
            $predictedPlant = null;
            $confidence     = null;
            $serviceError   = '';

            // Try Python TF microservice first
            $imageData = base64_encode(file_get_contents(UPLOAD_DIR.$filename));
            $payload   = json_encode(['image_base64' => $imageData, 'filename' => $filename]);

            $ch = curl_init(PYTHON_API_URL);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 20,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $modelResponse  = json_decode($response, true);
                $predictedPlant = $modelResponse['plant_name']  ?? null;
                $confidence     = $modelResponse['confidence']   ?? null;
            } else {
                // Fallback: use Gemini Vision if TF model unavailable
                $serviceError = 'TF model service offline. ';
                if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE') {
                    $geminiPayload = json_encode([
                        'contents' => [[
                            'parts' => [
                                ['text' => 'Identify this medicinal plant. Return ONLY a JSON object with keys: plant_name (common name), scientific_name, medicinal_uses (brief), preparation (brief), confidence_percent (0-100). If not a plant or unrecognisable, set plant_name to "Unknown".'],
                                ['inline_data' => ['mime_type' => $_FILES['plant_image']['type'], 'data' => $imageData]]
                            ]
                        ]],
                        'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 400]
                    ]);
                    $ch = curl_init(GEMINI_API_URL.'?key='.GEMINI_API_KEY);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $geminiPayload,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT        => 20,
                    ]);
                    $gr = curl_exec($ch);
                    $gc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($gc === 200) {
                        $gd   = json_decode($gr, true);
                        $text = $gd['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        // strip markdown fences
                        $text = preg_replace('/```(?:json)?|```/', '', $text);
                        $parsed = json_decode(trim($text), true);
                        if ($parsed) {
                            $predictedPlant = $parsed['plant_name']          ?? 'Unknown';
                            $confidence     = $parsed['confidence_percent']  ?? 50;
                            $modelResponse  = $parsed;
                        }
                    }
                }
            }

            // Try to match to DB plant
            $matchedPlant = null;
            $plantId      = null;
            if ($predictedPlant && strtolower($predictedPlant) !== 'unknown') {
                $stmt = $db->prepare("SELECT * FROM plants WHERE (common_name LIKE ? OR scientific_name LIKE ?) AND is_active=1 LIMIT 1");
                $stmt->execute(["%$predictedPlant%", "%$predictedPlant%"]);
                $matchedPlant = $stmt->fetch();
                if ($matchedPlant) $plantId = $matchedPlant['id'];
            }

            // Save scan to DB
            $stmt = $db->prepare('INSERT INTO plant_scans (user_id,image_filename,predicted_plant,confidence,plant_id,model_response) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$uid, $filename, $predictedPlant, $confidence, $plantId, json_encode($modelResponse)]);

            $result = [
                'filename'       => $filename,
                'predicted_plant'=> $predictedPlant,
                'confidence'     => $confidence,
                'matched_plant'  => $matchedPlant,
                'model_response' => $modelResponse,
                'service_error'  => $serviceError,
            ];
        }
    }
}

// Recent scans
$recentScans = $db->prepare('SELECT ps.*,p.common_name AS db_plant FROM plant_scans ps LEFT JOIN plants p ON p.id=ps.plant_id WHERE ps.user_id=? ORDER BY ps.scanned_at DESC LIMIT 6');
$recentScans->execute([$uid]);
$recentScans = $recentScans->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Plant Detection — Herbal System</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .upload-zone { border:2px dashed var(--green-light); border-radius:var(--radius-md); padding:2.5rem; text-align:center; cursor:pointer; transition:var(--transition); background:var(--cream); }
    .upload-zone:hover,.upload-zone.drag-over { border-color:var(--green-mid); background:var(--green-pale); }
    .upload-zone input[type=file] { display:none; }
    .preview-img { max-height:280px; border-radius:var(--radius-md); margin:0 auto; display:block; box-shadow:var(--shadow-md); }
    .confidence-high   { color:#276749; }
    .confidence-medium { color:#92400E; }
    .confidence-low    { color:var(--danger); }
    .scan-thumb { width:56px; height:56px; border-radius:8px; object-fit:cover; }
  </style>
</head>
<body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper"><div class="main-content">

<div class="page-title">
  <h2><i class="fa-solid fa-camera" style="color:var(--green-mid);"></i> Plant Detection</h2>
  <p>Upload a photo of a plant to identify it and learn about its medicinal uses.</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

  <!-- Upload form -->
  <div>
    <div class="card">
      <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-upload"></i> Upload Plant Image</h4></div>
      <div class="card-body">
        <?php if($error): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="uploadForm">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

          <div class="upload-zone" id="uploadZone" onclick="document.getElementById('plantImage').click()">
            <input type="file" id="plantImage" name="plant_image" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
            <div id="uploadContent">
              <div style="font-size:3rem;margin-bottom:0.75rem;">📷</div>
              <h4 style="margin-bottom:0.3rem;">Click to upload or drag & drop</h4>
              <p style="font-size:0.85rem;color:var(--text-light);margin:0;">JPG, PNG or WebP · Max 5MB</p>
            </div>
            <img id="imagePreview" class="preview-img" style="display:none;" alt="Preview">
          </div>

          <div style="margin-top:1rem;">
            <button type="submit" class="btn btn-primary btn-full btn-lg" id="scanBtn">
              <i class="fa-solid fa-magnifying-glass-plus"></i> Identify This Plant
            </button>
          </div>
        </form>

        <div style="margin-top:1.25rem;padding:1rem;background:var(--green-pale);border-radius:var(--radius-sm);">
          <p style="margin:0;font-size:0.82rem;color:var(--green-dark);"><strong>📌 Tips for best results:</strong></p>
          <ul style="margin:0.4rem 0 0 1.2rem;padding:0;font-size:0.8rem;color:var(--green-dark);">
            <li>Take a clear, well-lit photo of the leaves or flowers</li>
            <li>Avoid blurry or dark images</li>
            <li>Include both the leaf shape and texture if possible</li>
            <li>Avoid including hands or other objects in the frame</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- How it works -->
    <div class="card" style="margin-top:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;">How It Works</h4></div>
      <div class="card-body" style="padding:1rem;">
        <?php
        $steps = [
            ['🖼️','Upload a plant photo','Take or choose a clear photo of the plant'],
            ['🤖','AI Analysis','Our TensorFlow model analyses the image features'],
            ['🌿','Plant Identified','Get the plant name, uses and preparation guide'],
            ['📅','Book Herbalist','Connect with a herbalist for expert advice'],
        ];
        foreach($steps as $i=>[$icon,$title,$desc]): ?>
        <div style="display:flex;align-items:flex-start;gap:0.75rem;<?=$i<3?'margin-bottom:0.85rem;':''?>">
          <div style="width:32px;height:32px;background:var(--green-pale);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;"><?=$icon?></div>
          <div><div style="font-weight:600;font-size:0.85rem;"><?=htmlspecialchars($title)?></div><div style="font-size:0.78rem;color:var(--text-light);"><?=htmlspecialchars($desc)?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Results -->
  <div>
    <?php if($result): ?>
    <div class="card animate-fadeInUp" style="margin-bottom:1.25rem;<?= $result['confidence']>=70?'border:2px solid var(--green-light)':'' ?>">
      <div class="card-header" style="<?= $result['confidence']>=70?'background:var(--green-pale);':'' ?>">
        <h4 style="margin:0;font-size:0.95rem;display:flex;align-items:center;gap:0.5rem;">
          <i class="fa-solid fa-<?= $result['confidence']>=70?'circle-check':'circle-question' ?>" style="color:<?= $result['confidence']>=70?'var(--green-mid)':'#D69E2E' ?>;"></i>
          Detection Result
        </h4>
      </div>
      <div class="card-body">
        <!-- Uploaded image -->
        <img src="<?= APP_URL ?>/assets/images/uploads/<?= htmlspecialchars($result['filename']) ?>" class="preview-img" style="margin-bottom:1.25rem;" alt="Uploaded plant">

        <?php if($result['service_error']): ?>
          <div class="alert alert-warning" style="font-size:0.82rem;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($result['service_error']) ?>Using Gemini Vision as fallback.</div>
        <?php endif; ?>

        <?php if($result['predicted_plant'] && strtolower($result['predicted_plant']) !== 'unknown'): ?>
          <div style="text-align:center;margin-bottom:1.25rem;">
            <h3 style="margin:0 0 0.3rem;"><?= htmlspecialchars($result['predicted_plant']) ?></h3>
            <?php if($result['confidence']): ?>
              <?php $conf=$result['confidence']; $cls=$conf>=70?'confidence-high':($conf>=40?'confidence-medium':'confidence-low'); ?>
              <div style="margin:0.5rem 0;">
                <div style="height:8px;width:100%;background:#E2E8F0;border-radius:99px;overflow:hidden;">
                  <div style="height:100%;width:<?=$conf?>%;background:<?=$conf>=70?'var(--green-light)':($conf>=40?'#F6AD55':'var(--danger)')?>;border-radius:99px;transition:width 1s ease;"></div>
                </div>
                <p class="<?=$cls?>" style="font-size:0.88rem;font-weight:600;margin:0.3rem 0 0;"><?=number_format($conf,1)?>% confidence</p>
              </div>
            <?php endif; ?>
            <?php if($result['confidence'] < 70): ?>
              <div class="alert alert-warning" style="font-size:0.82rem;text-align:left;margin-top:0.75rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> Low confidence result. The model is not certain about this identification. Please verify with a qualified herbalist.
              </div>
            <?php endif; ?>
          </div>

          <!-- Model response details (from Gemini vision fallback) -->
          <?php if(is_array($result['model_response']) && isset($result['model_response']['medicinal_uses'])): ?>
          <div style="background:var(--green-pale);border-radius:var(--radius-sm);padding:1rem;margin-bottom:1rem;">
            <p style="font-size:0.78rem;font-weight:700;text-transform:uppercase;color:var(--green-dark);margin-bottom:0.4rem;">Medicinal Uses</p>
            <p style="font-size:0.88rem;margin:0;"><?= htmlspecialchars($result['model_response']['medicinal_uses']) ?></p>
          </div>
          <?php endif; ?>

          <!-- Matched DB plant -->
          <?php if($result['matched_plant']): ?>
          <div style="border:1px solid var(--green-light);border-radius:var(--radius-sm);padding:1rem;">
            <p style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--text-light);margin-bottom:0.6rem;"><i class="fa-solid fa-database"></i> Matched from Plant Database</p>
            <div style="font-weight:700;"><?= htmlspecialchars($result['matched_plant']['common_name']) ?></div>
            <div style="font-size:0.78rem;font-style:italic;color:var(--text-light);margin-bottom:0.6rem;"><?= htmlspecialchars($result['matched_plant']['scientific_name']) ?></div>
            <p style="font-size:0.85rem;"><?= htmlspecialchars(substr($result['matched_plant']['medicinal_uses'],0,180)) ?>…</p>
            <?php if($result['matched_plant']['preparation']): ?>
            <details style="margin-top:0.5rem;">
              <summary style="cursor:pointer;font-size:0.85rem;font-weight:600;color:var(--green-mid);">View Preparation</summary>
              <p style="font-size:0.85rem;margin-top:0.5rem;white-space:pre-line;"><?= htmlspecialchars($result['matched_plant']['preparation']) ?></p>
            </details>
            <?php endif; ?>
            <?php if($result['matched_plant']['contraindications']): ?>
            <div style="background:var(--danger-light);border-radius:6px;padding:0.5rem;margin-top:0.6rem;">
              <p style="font-size:0.78rem;color:var(--danger);margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($result['matched_plant']['contraindications']) ?></p>
            </div>
            <?php endif; ?>
          </div>
          <?php else: ?>
            <div class="alert alert-info" style="font-size:0.82rem;"><i class="fa-solid fa-circle-info"></i> Plant identified but not yet in our local database. Contact an admin to add it.</div>
          <?php endif; ?>

        <?php else: ?>
          <div style="text-align:center;padding:1.5rem;">
            <div style="font-size:3rem;margin-bottom:0.75rem;">🤔</div>
            <h4>Plant Not Recognised</h4>
            <p style="font-size:0.88rem;">The AI could not identify a medicinal plant in this image. Try a clearer photo with better lighting.</p>
          </div>
        <?php endif; ?>

        <a href="<?= APP_URL ?>/patient/pages/herbalists.php" class="btn btn-outline btn-full" style="margin-top:1rem;"><i class="fa-solid fa-user-nurse"></i> Ask a Herbalist</a>
      </div>
    </div>

    <?php else: ?>
    <!-- Placeholder when no scan yet -->
    <div class="card">
      <div class="card-body" style="text-align:center;padding:3rem 2rem;">
        <div style="font-size:4rem;margin-bottom:1rem;">🌿</div>
        <h3>No Scan Yet</h3>
        <p>Upload a plant image on the left to get started. Our AI will identify it and show you its medicinal properties.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent scans -->
    <?php if(!empty($recentScans)): ?>
    <div class="card" style="margin-top:1.25rem;">
      <div class="card-header"><h4 style="margin:0;font-size:0.9rem;"><i class="fa-solid fa-clock-rotate-left"></i> Recent Scans</h4></div>
      <div class="card-body" style="padding:0.75rem;">
        <?php foreach($recentScans as $s): ?>
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid #F1F5F9;">
          <?php if($s['image_filename']): ?>
            <img src="<?= APP_URL ?>/assets/images/uploads/<?= htmlspecialchars($s['image_filename']) ?>" class="scan-thumb" alt="">
          <?php else: ?>
            <div class="scan-thumb" style="background:var(--green-pale);display:flex;align-items:center;justify-content:center;font-size:1.3rem;">🌿</div>
          <?php endif; ?>
          <div style="flex:1;">
            <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($s['predicted_plant'] ?? 'Unknown') ?></div>
            <div style="font-size:0.75rem;color:var(--text-light);"><?= $s['confidence']?number_format($s['confidence'],1).'% · ':'' ?><?= timeAgo($s['scanned_at']) ?></div>
          </div>
          <?php if($s['db_plant']): ?><span class="badge badge-green" style="font-size:0.7rem;">✓ Matched</span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

</div></div>
<script>
function previewImage(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById('imagePreview');
      const content = document.getElementById('uploadContent');
      preview.src = e.target.result;
      preview.style.display = 'block';
      content.style.display  = 'none';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// Drag and drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('plantImage');
    const dt    = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    previewImage(input);
  }
});

// Show loading on submit
document.getElementById('uploadForm').addEventListener('submit', function() {
  const btn = document.getElementById('scanBtn');
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analysing image…';
  btn.disabled = true;
});
</script>
</body></html>
