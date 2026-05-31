<?php
// ============================================================
//  Patient — Symptom Checker (Hybrid: Rules + Gemini AI)
//  File: patient/pages/symptom_checker.php
// ============================================================
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../includes/helpers.php';
requireRole('patient');

$db     = Database::connect();
$uid    = $_SESSION['user_id'];
$result = null;
$error  = '';

// ── HYBRID RECOMMENDATION ENGINE ─────────────────────────────
function getHybridRecommendations(string $input, PDO $db): array {
    $input = strtolower(trim($input));

    // STEP 1 — Rule engine: keyword matching against symptoms table
    $allSymptoms = $db->query('SELECT * FROM symptoms')->fetchAll();
    $matchedIds  = [];

    foreach ($allSymptoms as $sym) {
        $keywords = array_map('trim', explode(',', strtolower($sym['keywords'] ?? '')));
        $keywords[] = strtolower($sym['name']);
        foreach ($keywords as $kw) {
            if ($kw && str_contains($input, $kw)) {
                $matchedIds[] = $sym['id'];
                break;
            }
        }
    }

    $ruleRemedies = [];
    $rulePlants   = [];

    if (!empty($matchedIds)) {
        $ph = implode(',', array_fill(0, count($matchedIds), '?'));

        // Matched remedies via rule engine
        $stmt = $db->prepare("
            SELECT DISTINCT r.*
            FROM remedies r
            JOIN symptom_remedy_map srm ON srm.remedy_id = r.id
            WHERE srm.symptom_id IN ($ph) AND r.is_active = 1
            ORDER BY srm.relevance DESC LIMIT 4
        ");
        $stmt->execute($matchedIds);
        $ruleRemedies = $stmt->fetchAll();

        // Matched plants via rule engine
        $stmt = $db->prepare("
            SELECT DISTINCT p.*
            FROM plants p
            JOIN symptom_plant_map spm ON spm.plant_id = p.id
            WHERE spm.symptom_id IN ($ph) AND p.is_active = 1
            ORDER BY spm.relevance DESC LIMIT 5
        ");
        $stmt->execute($matchedIds);
        $rulePlants = $stmt->fetchAll();
    }

    // STEP 2 — Gemini AI call
    $geminiResponse = '';
    $geminiError    = '';
    $source         = 'rules';

    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== 'YOUR_GEMINI_API_KEY_HERE') {
        $prompt = <<<PROMPT
You are a knowledgeable herbal medicine assistant specialising in Cameroonian and African medicinal plants.

A patient has described the following symptoms or health concern:
"{$input}"

Please provide:
1. A brief explanation of what may be causing these symptoms (2-3 sentences, non-alarmist).
2. 2-3 specific herbal remedy recommendations using plants commonly found in Cameroon (e.g. Ginger, Lemongrass, Neem, Moringa, Bitter Leaf, Garlic, Eucalyptus, African Basil, Turmeric, Pawpaw).
3. For each remedy: the plant name, how to prepare it, and dosage.
4. Important safety warnings or when to see a doctor.
5. End with: "⚠️ This is informational only. Always consult a qualified healthcare provider for serious conditions."

Keep the response clear, friendly and practical. Use simple language suitable for a general audience.
PROMPT;

        $payload = json_encode([
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 800]
        ]);

        $ch = curl_init(GEMINI_API_URL.'?key='.GEMINI_API_KEY);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $geminiResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $source = empty($matchedIds) ? 'gemini' : 'hybrid';
        } else {
            $geminiError = 'AI service temporarily unavailable. Showing rule-based results only.';
            $source = 'rules';
        }
    } else {
        $geminiError = 'Gemini API key not configured. Showing rule-based results only.';
    }

    return [
        'matched_symptom_ids' => $matchedIds,
        'rule_remedies'       => $ruleRemedies,
        'rule_plants'         => $rulePlants,
        'gemini_response'     => $geminiResponse,
        'gemini_error'        => $geminiError,
        'source'              => $source,
    ];
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $input = sanitize($_POST['symptom_input'] ?? '');

    if (strlen(trim($input)) < 3) {
        $error = 'Please describe your symptoms in at least a few words.';
    } else {
        $result = getHybridRecommendations($input, $db);

        // Save to search history
        $stmt = $db->prepare('
            INSERT INTO search_history
                (user_id, symptom_input, matched_symptoms, remedy_ids, gemini_response, source)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $uid,
            $input,
            implode(',', $result['matched_symptom_ids']),
            implode(',', array_column($result['rule_remedies'], 'id')),
            $result['gemini_response'],
            $result['source'],
        ]);
        $result['search_id'] = $db->lastInsertId();
        $result['input']     = $input;
    }
}

// Recent searches for quick re-search
$recent = $db->prepare('SELECT symptom_input FROM search_history WHERE user_id=? ORDER BY searched_at DESC LIMIT 5');
$recent->execute([$uid]);
$recent = $recent->fetchAll(PDO::FETCH_COLUMN);

// Common symptom suggestions
$suggestions = ['I have a cough','Fever and chills','Stomach ache','Headache','Sore throat','Nausea','Joint pain','Skin rash','High blood pressure','Malaria symptoms'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Symptom Checker — Herbal System</title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .gemini-response { white-space: pre-wrap; line-height: 1.8; font-size: 0.93rem; color: var(--text-dark); }
    .gemini-response strong { color: var(--green-dark); }
    .plant-chip { display:inline-flex; align-items:center; gap:0.4rem; background:var(--green-pale); color:var(--green-dark); padding:0.3rem 0.75rem; border-radius:99px; font-size:0.8rem; font-weight:600; margin:0.25rem; }
    .remedy-card { border:1px solid var(--green-pale); border-radius:var(--radius-md); padding:1.25rem; margin-bottom:1rem; background:var(--white); }
    .remedy-card h4 { color:var(--green-dark); margin-bottom:0.5rem; font-size:1rem; }
    .remedy-section { margin-top:0.75rem; }
    .remedy-section label { font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-light); letter-spacing:0.06em; display:block; margin-bottom:0.25rem; }
    .remedy-section p { font-size:0.88rem; margin:0; white-space:pre-line; }
    .source-badge { display:inline-flex; align-items:center; gap:0.4rem; padding:0.3rem 0.8rem; border-radius:99px; font-size:0.75rem; font-weight:600; }
    .source-hybrid { background:linear-gradient(135deg,var(--green-pale),#EBF8FF); color:var(--green-dark); border:1px solid var(--green-light); }
    .source-rules   { background:var(--green-pale); color:var(--green-dark); }
    .source-gemini  { background:#EBF8FF; color:#1A365D; }
    .spinner { display:none; }
    .loading .spinner { display:inline-block; }
    .loading .btn-text { display:none; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .fa-spin-custom { animation:spin 0.8s linear infinite; }
  </style>
</head>
<body>
<?php require __DIR__.'/../includes/sidebar.php'; ?>
<div class="main-wrapper"><div class="main-content">

<div class="page-title">
  <h2><i class="fa-solid fa-stethoscope" style="color:var(--green-mid);"></i> Symptom Checker</h2>
  <p>Describe your symptoms and our AI will suggest herbal remedies from Cameroonian medicinal plants.</p>
</div>

<!-- Search form -->
<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-body" style="padding:1.5rem;">
    <?php if($error): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="symptomForm">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group" style="margin-bottom:0.75rem;">
        <label class="form-label">Describe your symptoms or health concern</label>
        <textarea name="symptom_input" id="symptomInput" class="form-control" rows="4"
          placeholder="e.g. I have a persistent cough, sore throat and mild fever for the past two days…"
          style="resize:vertical;font-size:0.95rem;"><?= htmlspecialchars($_POST['symptom_input'] ?? '') ?></textarea>
        <p class="form-hint">Be as specific as possible — include how long you have had the symptoms, severity, and any other relevant details.</p>
      </div>

      <!-- Quick suggestions -->
      <div style="margin-bottom:1rem;">
        <p style="font-size:0.78rem;color:var(--text-light);margin-bottom:0.4rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Quick suggestions</p>
        <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
          <?php foreach($suggestions as $sug): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('symptomInput').value='<?= htmlspecialchars($sug) ?>'" style="font-size:0.78rem;padding:0.25rem 0.7rem;"><?= htmlspecialchars($sug) ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" style="min-width:220px;" onclick="this.classList.add('loading')">
        <i class="fa-solid fa-spinner fa-spin-custom spinner"></i>
        <span class="btn-text"><i class="fa-solid fa-brain"></i> Get Herbal Recommendations</span>
      </button>
    </form>

    <?php if(!empty($recent)): ?>
    <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #E2E8F0;">
      <p style="font-size:0.78rem;color:var(--text-light);margin-bottom:0.5rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;"><i class="fa-solid fa-clock-rotate-left"></i> Recent searches</p>
      <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
        <?php foreach($recent as $r): ?>
          <button type="button" onclick="document.getElementById('symptomInput').value='<?= htmlspecialchars(addslashes($r)) ?>'"
                  class="btn btn-ghost btn-sm" style="font-size:0.78rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars(substr($r, 0, 40)) ?><?= strlen($r)>40?'…':'' ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- RESULTS -->
<?php if($result): ?>
<div class="animate-fadeInUp">

  <!-- Source badge + input echo -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1.25rem;">
    <div>
      <h3 style="margin:0 0 0.3rem;">Results for: <em style="color:var(--green-mid);">"<?= htmlspecialchars($result['input']) ?>"</em></h3>
      <p style="margin:0;font-size:0.85rem;color:var(--text-light);">
        <?= count($result['rule_remedies']) ?> rule-based remedies · <?= count($result['rule_plants']) ?> matched plants
        <?= $result['gemini_response'] ? ' · AI response included' : '' ?>
      </p>
    </div>
    <span class="source-badge source-<?= $result['source'] ?>">
      <i class="fa-solid fa-<?= $result['source']==='hybrid'?'brain':($result['source']==='gemini'?'robot':'cogs') ?>"></i>
      <?= ucfirst($result['source']) ?> Recommendation
    </span>
  </div>

  <?php if($result['gemini_error']): ?>
    <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($result['gemini_error']) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:<?= $result['gemini_response']?'1fr 1fr':'1fr' ?>;gap:1.5rem;">

    <!-- LEFT: Rule-based results -->
    <div>
      <?php if(!empty($result['rule_plants'])): ?>
      <div class="card" style="margin-bottom:1.25rem;">
        <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-seedling"></i> Recommended Medicinal Plants</h4></div>
        <div class="card-body">
          <div style="display:flex;flex-wrap:wrap;margin-bottom:1rem;">
            <?php foreach($result['rule_plants'] as $p): ?>
              <span class="plant-chip">🌿 <?= htmlspecialchars($p['common_name']) ?></span>
            <?php endforeach; ?>
          </div>
          <?php foreach($result['rule_plants'] as $p): ?>
          <div style="border:1px solid #E2E8F0;border-radius:10px;padding:1rem;margin-bottom:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.6rem;">
              <?php if($p['image_filename']): ?>
                <img src="<?= APP_URL ?>/assets/images/plants/<?= htmlspecialchars($p['image_filename']) ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;" alt="">
              <?php else: ?>
                <div style="width:44px;height:44px;background:var(--green-pale);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">🌿</div>
              <?php endif; ?>
              <div>
                <div style="font-weight:700;"><?= htmlspecialchars($p['common_name']) ?></div>
                <div style="font-size:0.78rem;font-style:italic;color:var(--text-light);"><?= htmlspecialchars($p['scientific_name']) ?></div>
              </div>
              <?php $tc=['none'=>'badge-green','low'=>'badge-green','moderate'=>'badge-gold','high'=>'badge-red']; ?>
              <span class="badge <?= $tc[$p['toxicity_level']]??'badge-gray' ?>" style="margin-left:auto;"><?= $p['toxicity_level'] ?> toxicity</span>
            </div>
            <?php if($p['parts_used']): ?><p style="font-size:0.8rem;color:var(--text-light);margin-bottom:0.4rem;">🌿 Parts used: <strong><?= htmlspecialchars($p['parts_used']) ?></strong></p><?php endif; ?>
            <?php if($p['preparation']): ?>
              <details style="margin-top:0.5rem;">
                <summary style="cursor:pointer;font-size:0.85rem;font-weight:600;color:var(--green-mid);">View Preparation Method</summary>
                <p style="font-size:0.85rem;margin-top:0.5rem;white-space:pre-line;"><?= htmlspecialchars($p['preparation']) ?></p>
              </details>
            <?php endif; ?>
            <?php if($p['contraindications']): ?>
              <div style="background:var(--danger-light);border-radius:6px;padding:0.5rem 0.75rem;margin-top:0.5rem;">
                <p style="font-size:0.78rem;color:var(--danger);margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($p['contraindications']) ?></p>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(!empty($result['rule_remedies'])): ?>
      <div class="card">
        <div class="card-header"><h4 style="margin:0;font-size:0.95rem;"><i class="fa-solid fa-mortar-pestle"></i> Herbal Remedy Recipes</h4></div>
        <div class="card-body">
          <?php foreach($result['rule_remedies'] as $r): ?>
          <div class="remedy-card">
            <h4><?= htmlspecialchars($r['name']) ?></h4>
            <span class="badge badge-green" style="margin-bottom:0.75rem;text-transform:none;"><?= htmlspecialchars($r['target_illness']) ?></span>
            <div class="remedy-section"><label>Ingredients</label><p><?= htmlspecialchars($r['ingredients']) ?></p></div>
            <div class="remedy-section"><label>Preparation</label><p><?= htmlspecialchars($r['preparation']) ?></p></div>
            <?php if($r['dosage']): ?><div class="remedy-section"><label>Dosage</label><p><?= htmlspecialchars($r['dosage']) ?></p></div><?php endif; ?>
            <?php if($r['warnings']): ?>
              <div style="background:var(--danger-light);border-radius:6px;padding:0.5rem 0.75rem;margin-top:0.75rem;">
                <p style="font-size:0.78rem;color:var(--danger);margin:0;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($r['warnings']) ?></p>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php if(empty($result['rule_remedies'])): ?>
            <p style="color:var(--text-light);text-align:center;padding:1rem;">No exact rule matches found for these symptoms. See AI response for suggestions.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(empty($result['rule_plants']) && empty($result['rule_remedies']) && !$result['gemini_response']): ?>
        <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> No matching records found in the database for these symptoms. Try different keywords or consult a herbalist directly.</div>
      <?php endif; ?>
    </div>

    <!-- RIGHT: Gemini AI response -->
    <?php if($result['gemini_response']): ?>
    <div>
      <div class="card" style="border:1px solid var(--green-light);">
        <div class="card-header" style="background:linear-gradient(135deg,var(--green-pale),#EBF8FF);">
          <h4 style="margin:0;font-size:0.95rem;display:flex;align-items:center;gap:0.5rem;">
            <i class="fa-solid fa-robot" style="color:var(--green-mid);"></i> AI Herbal Assistant (Gemini)
            <span class="badge" style="background:var(--green-mid);color:#fff;font-size:0.7rem;margin-left:auto;">AI Response</span>
          </h4>
        </div>
        <div class="card-body">
          <div class="gemini-response"><?= nl2br(htmlspecialchars($result['gemini_response'])) ?></div>
        </div>
        <div class="card-footer">
          <p style="font-size:0.75rem;color:var(--text-light);margin:0;"><i class="fa-solid fa-shield-halved"></i> AI responses are informational only. Always consult a qualified healthcare professional for serious medical conditions.</p>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Book herbalist CTA -->
  <div class="card" style="margin-top:1.5rem;border:2px solid var(--green-pale);">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
      <div>
        <h4 style="margin:0 0 0.3rem;">Want personalised advice?</h4>
        <p style="margin:0;font-size:0.88rem;">Book a consultation with a qualified herbalist for hands-on guidance specific to your condition.</p>
      </div>
      <a href="<?= APP_URL ?>/patient/pages/herbalists.php" class="btn btn-primary"><i class="fa-solid fa-user-nurse"></i> Find a Herbalist</a>
    </div>
  </div>
</div>
<?php endif; ?>

</div></div>
<script>
// Reset button loading state if form validation fails
document.getElementById('symptomForm').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  setTimeout(() => btn.classList.remove('loading'), 15000);
});
</script>
</body></html>
