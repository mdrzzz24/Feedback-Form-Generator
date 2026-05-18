<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$dbname = 'feedback_db';
$username = 'root';
$password = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('DB Error: ' . $e->getMessage());
}

$tplId = isset($_GET['tpl']) && is_numeric($_GET['tpl']) ? intval($_GET['tpl']) : 0;
$evtId = isset($_GET['evt']) && !empty($_GET['evt']) ? $_GET['evt'] : '';

$event = null;
$tplQ = [];
$tplS = [];
$evtQ = [];
$evtS = [];
$evtName = '';
$headerImage = '';
$description = '';

if ($evtId) {
    // Preview event
    $stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?");
    $stmt->execute([$evtId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $evtName = $event['event_name'];
        $headerImage = $event['header_image'];
        $description = $event['description'];

        // Get event sections and questions
        $stmt = $pdo->prepare("SELECT * FROM form_generator_event_sections WHERE event_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$evtId]);
        $evtS = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE id_event = ? ORDER BY sort_order ASC");
        $stmt->execute([$evtId]);
        $evtQ = $stmt->fetchAll();
    }
} elseif ($tplId) {
    // Preview template
    $stmt = $pdo->prepare("SELECT * FROM form_generator_template WHERE id = ?");
    $stmt->execute([$tplId]);
    $tpl = $stmt->fetch();

    if ($tpl) {
        $evtName = $tpl['template_name'];

        $stmt = $pdo->prepare("SELECT * FROM form_generator_template_sections WHERE template_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$tplId]);
        $tplS = $stmt->fetchAll();

        $stmt = $pdo->prepare("SELECT * FROM form_generator_template_questions WHERE template_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$tplId]);
        $tplQ = $stmt->fetchAll();
    }
}

if (!$evtName && !$tplId) {
    echo '<div style="padding:40px;text-align:center;color:#64748b;">';
    echo '<svg width="64" height="64" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>';
    echo '<p style="margin-top:20px;">Select a template or event to see the preview</p>';
    echo '</div>';
    exit;
}

$tQS = [0 => []];
foreach ($tplS as $s) $tQS[$s['id']] = [];
foreach ($tplQ as $q) $tQS[$q['section_id'] ?? 0][] = $q;

$eQS = [0 => []];
foreach ($evtS as $s) $eQS[$s['id']] = [];
foreach ($evtQ as $q) $eQS[$q['section_id'] ?? 0][] = $q;

$qNames = [];
foreach ($tplQ as $q) {
    $n = 'p_' . $q['id'];
    if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $n = 'name';
    elseif (stripos($q['question_text'], 'email') !== false) $n = 'email';
    elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
    elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
    elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';
    $qNames['p_' . $q['id']] = $n;
}
foreach ($evtQ as $q) {
    $qNames['q_' . $q['id']] = 'q_' . $q['id'];
}

function renderQ($q, $prefix, $qNames) {
    $n = $qNames[$prefix . $q['id']] ?? ($prefix . $q['id']);
    $txt = htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8');

    $t = $q['question_type'];
    $r = $q['is_required'] ? 'required' : '';
    $opts = !empty($q['options']) ? json_decode($q['options'], true) ?: [] : [];

    $parentName = '';
    if (isset($q['parent_question_id']) && $q['parent_question_id']) {
        $parentName = $qNames[$prefix . $q['parent_question_id']] ?? '';
    }
    $parent = $parentName ? " data-parent-q='{$parentName}' data-parent-opt='" . htmlspecialchars($q['parent_option_value'], ENT_QUOTES, 'UTF-8') . "' style='display:none;'" : '';

    $h = "<div class='mb-4' id='wrap_{$n}'$parent><label class='form-label'>$txt " . ($r ? '<span class="text-danger">*</span>' : '') . "</label>";
    if (in_array($t, ['text', 'email', 'tel'])) {
        $h .= "<input type='$t' class='form-control' name='$n' placeholder='...' $r>";
    } elseif ($t === 'textarea') {
        $h .= "<textarea class='form-control' name='$n' rows='3' placeholder='...' $r></textarea>";
    } elseif (in_array($t, ['radio', 'checkbox'])) {
        $h = "<div class='mb-4' id='wrap_{$n}'$parent><p class='mb-3 fw-semibold'>$txt " . ($r ? '<span class="text-danger">*</span>' : '') . "</p><div class='row g-2'>";
        foreach ($opts as $o) {
            $oid = md5($n . $o);
            $h .= "<div class='col-6'><div class='form-check'><input type='$t' class='form-check-input' name='{$n}[]' id='$oid' value='" . htmlspecialchars($o, ENT_QUOTES, 'UTF-8') . "' $r><label class='form-check-label' for='$oid'>$o</label></div></div>";
        }
        $h .= "</div>";
    }
    $h .= "</div>";
    return $h;
}

$html = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
$html .= "<title>Preview | " . htmlspecialchars($evtName, ENT_QUOTES, 'UTF-8') . "</title>";
$html .= "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css' rel='stylesheet'>";
$html .= "<style>body{background:#f0f2f5;font-family:system-ui,sans-serif}.card{border:0;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,0.04)}.form-control{border-radius:10px;padding:12px;border:1px solid #e2e8f0}.form-control:focus{border-color:#4a6fa5;box-shadow:0 0 0 3px rgba(74,111,165,.15)}.form-check-input:checked{background-color:#4a6fa5;border-color:#4a6fa5}.btn-submit{background:#4a6fa5;color:#fff;border:none;border-radius:12px;padding:16px;font-weight:600;width:100%;font-size:1.1rem}.btn-submit:hover{background:#3d5d8a}.section-title{color:#1e293b;font-weight:600;border-bottom:2px solid #4a6fa5;padding-bottom:8px;margin-bottom:20px}.type-badge{background:#e0e7ff;color:#4338ca;padding:3px 8px;border-radius:12px;font-size:0.7rem}</style>";
$html .= "<script>document.addEventListener('DOMContentLoaded',function(){\n  var all = document.querySelectorAll('[data-parent-q]');\n  all.forEach(function(el){\n    var parentQ = el.getAttribute('data-parent-q');\n    var parentOpt = el.getAttribute('data-parent-opt');\n    var parentInputs = document.getElementsByName(parentQ + '[]');\n    if(parentInputs.length === 0) parentInputs = document.getElementsByName(parentQ);\n    var showIf = function(){\n      var show = false;\n      parentInputs.forEach(function(inp){\n        if(inp.type === 'radio' || inp.type === 'checkbox') {\n          if(inp.checked && inp.value==parentOpt) show=true;\n        } else {\n          if(inp.value == parentOpt) show=true;\n        }\n      });\n      el.style.display = show ? '' : 'none';\n    };\n    parentInputs.forEach(function(inp){\n      inp.addEventListener('change', showIf);\n      inp.addEventListener('input', showIf);\n    });\n    showIf();\n  });\n});</script>";
$html .= "</head><body>";

$html .= "<div class='container py-4'><div class='row justify-content-center'><div class='col-lg-8'>";

// Header Card
$html .= "<div class='card mb-4'>";
$imgSrc = '';
if (!empty($headerImage)) {
    if (!filter_var($headerImage, FILTER_VALIDATE_URL)) {
        $imgSrc = "../form-generator/" . $headerImage;
    } else {
        $imgSrc = $headerImage;
    }
    $html .= "<img src='$imgSrc' class='rounded-top-4' style='width:100%;max-height:200px;object-fit:cover;'>";
}
$html .= "<div class='card-body'>";
$html .= "<h4 class='mb-2'>" . htmlspecialchars($evtName, ENT_QUOTES, 'UTF-8') . "</h4>";
if (!empty($description)) {
    $html .= "<p class='text-muted mb-0'>" . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . "</p>";
}
$html .= "</div></div>";

// Template Sections
foreach ($tplS as $s) {
    $qs = $tQS[$s['id']] ?? [];
    if (empty($qs)) continue;
    $html .= "<div class='card mb-4'><div class='card-body'>";
    $html .= "<h5 class='section-title'>" . htmlspecialchars($s['section_title'], ENT_QUOTES, 'UTF-8') . "</h5>";
    foreach ($qs as $q) $html .= renderQ($q, 'p_', $qNames);
    $html .= "</div></div>";
}

// Template General (section 0)
if (!empty($tQS[0])) {
    $html .= "<div class='card mb-4'><div class='card-body'>";
    foreach ($tQS[0] as $q) $html .= renderQ($q, 'p_', $qNames);
    $html .= "</div></div>";
}

// Event Sections
foreach ($evtS as $s) {
    $qs = $eQS[$s['id']] ?? [];
    if (empty($qs)) continue;
    $html .= "<div class='card mb-4'><div class='card-body'>";
    $html .= "<h5 class='section-title'>" . htmlspecialchars($s['section_title'], ENT_QUOTES, 'UTF-8') . "</h5>";
    foreach ($qs as $q) $html .= renderQ($q, 'q_', $qNames);
    $html .= "</div></div>";
}

// Event General
if (!empty($eQS[0])) {
    $html .= "<div class='card mb-4'><div class='card-body'>";
    foreach ($eQS[0] as $q) $html .= renderQ($q, 'q_', $qNames);
    $html .= "</div></div>";
}

$html .= "<button type='button' class='btn btn-submit'>Submit Feedback</button>";
$html .= "</div></div></div></body></html>";

echo $html;
?>