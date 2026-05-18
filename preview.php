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
$layout = 'standard';

if ($evtId) {
    // Preview event
    $stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?");
    $stmt->execute([$evtId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($event) {
        $evtName = $event['event_name'];
        $headerImage = $event['header_image'];
        $description = $event['description'];
        $layout = $event['layout'] ?? 'standard';

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
        $layout = 'standard';

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
    $n = 'q_' . $q['id'];
    if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $n = 'name';
    elseif (stripos($q['question_text'], 'email') !== false) $n = 'email';
    elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
    elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
    elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';
    $qNames['q_' . $q['id']] = $n;
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

$theme = $event['theme'] ?? 'default';
$styles = [
    'default' => ['bg' => '#f0f2f5', 'card_bg' => '#ffffff', 'text' => '#1e293b', 'primary' => '#4a6fa5', 'primary_hover' => '#3d5d8a', 'label' => '#444', 'section_title' => '#1e293b'],
    'dark' => ['bg' => '#0f172a', 'card_bg' => '#1e293b', 'text' => '#f8fafc', 'primary' => '#38bdf8', 'primary_hover' => '#0ea5e9', 'label' => '#cbd5e1', 'section_title' => '#38bdf8'],
    'nature' => ['bg' => '#f0f4f0', 'card_bg' => '#ffffff', 'text' => '#1b4332', 'primary' => '#2d6a4f', 'primary_hover' => '#1b4332', 'label' => '#40916c', 'section_title' => '#2d6a4f'],
    'modern' => ['bg' => '#fafafa', 'card_bg' => '#ffffff', 'text' => '#2d3436', 'primary' => '#6c5ce7', 'primary_hover' => '#a29bfe', 'label' => '#636e72', 'section_title' => '#6c5ce7'],
    'sunset' => ['bg' => '#fff7ed', 'card_bg' => '#ffffff', 'text' => '#431407', 'primary' => '#ea580c', 'primary_hover' => '#c2410c', 'label' => '#9a3412', 'section_title' => '#ea580c'],
    'ocean' => ['bg' => '#e0f2fe', 'card_bg' => '#ffffff', 'text' => '#0c4a6e', 'primary' => '#0ea5e9', 'primary_hover' => '#0369a1', 'label' => '#0369a1', 'section_title' => '#0ea5e9'],
    'cherry' => ['bg' => '#fdf2f8', 'card_bg' => '#ffffff', 'text' => '#831843', 'primary' => '#ec4899', 'primary_hover' => '#be185d', 'label' => '#db2777', 'section_title' => '#ec4899'],
    'cyberpunk' => ['bg' => '#000000', 'card_bg' => '#111111', 'text' => '#fef08a', 'primary' => '#facc15', 'primary_hover' => '#eab308', 'label' => '#fde047', 'section_title' => '#facc15'],
    'minimalist' => ['bg' => '#ffffff', 'card_bg' => '#ffffff', 'text' => '#000000', 'primary' => '#000000', 'primary_hover' => '#333333', 'label' => '#666666', 'section_title' => '#000000'],
    'vintage' => ['bg' => '#f5f5dc', 'card_bg' => '#fffaf0', 'text' => '#5d4037', 'primary' => '#8d6e63', 'primary_hover' => '#5d4037', 'label' => '#795548', 'section_title' => '#8d6e63']
];
$s = $styles[$theme] ?? $styles['default'];

$html = "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
$html .= "<title>Preview | " . htmlspecialchars($evtName, ENT_QUOTES, 'UTF-8') . "</title>";
$html .= "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css' rel='stylesheet'>";
$html .= "<style>body{background:{$s['bg']};color:{$s['text']};font-family:system-ui,sans-serif;padding:40px 0}.card{background:{$s['card_bg']};border:0;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);color:{$s['text']}}.form-control{background:{$s['card_bg']};color:{$s['text']};border-radius:10px;padding:12px;border:1px solid rgba(0,0,0,0.1)}.form-control:focus{background:{$s['card_bg']};color:{$s['text']};border-color:{$s['primary']};box-shadow:0 0 0 3px rgba(0,0,0,0.05)}.form-check-input:checked{background-color:{$s['primary']};border-color:{$s['primary']}}.btn-submit{background:{$s['primary']};color:#fff;border:none;border-radius:12px;padding:16px;font-weight:600;width:100%;font-size:1.1rem}.btn-submit:hover{background:{$s['primary_hover']};color:#fff}.btn-nav{background:rgba(0,0,0,0.05);color:{$s['text']};border:none;border-radius:10px;padding:10px 20px;font-weight:600}.section-title{color:{$s['section_title']};font-weight:600;border-bottom:2px solid {$s['primary']};padding-bottom:8px;margin-bottom:20px}.grid-layout{display:grid;grid-template-columns:1fr 1fr;gap:20px} .grid-layout > .section-title {grid-column: span 2} .form-step{display:none} .form-step.active{display:block;animation:fadeIn 0.4s} @keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}} " .
    (($theme === 'dark' || $theme === 'cyberpunk') ? ".btn-nav{background:rgba(255,255,255,0.1);color:white}" : "") . "</style>";
$html .= "<script>document.addEventListener('DOMContentLoaded',function(){\n  var all = document.querySelectorAll('[data-parent-q]');\n  all.forEach(function(el){\n    var parentQ = el.getAttribute('data-parent-q');\n    var parentOpt = el.getAttribute('data-parent-opt');\n    var parentInputs = document.getElementsByName(parentQ + '[]');\n    if(parentInputs.length === 0) parentInputs = document.getElementsByName(parentQ);\n    var showIf = function(){\n      var show = false;\n      parentInputs.forEach(function(inp){\n        if(inp.type === 'radio' || inp.type === 'checkbox') {\n          if(inp.checked && inp.value==parentOpt) show=true;\n        } else {\n          if(inp.value == parentOpt) show=true;\n        }\n      });\n      el.style.display = show ? '' : 'none';\n    };\n    parentInputs.forEach(function(inp){\n      inp.addEventListener('change', showIf);\n      inp.addEventListener('input', showIf);\n    });\n    showIf();\n  });\n" .
    "  var steps = document.querySelectorAll('.form-step');\n  var currentStep = 0;\n  window.moveStep = function(dir){\n    steps[currentStep].classList.remove('active');\n    currentStep += dir;\n    steps[currentStep].classList.add('active');\n    window.scrollTo(0,0);\n  };\n});</script>";
$html .= "</head><body>";

$html .= "<div class='container'><div class='row justify-content-center'><div class='col-lg-8'>";

$rawSections = [];
$rawSections[] = ['title' => $evtName, 'desc' => $description, 'img' => $headerImage, 'qs' => array_merge($tQS[0], $eQS[0]), 'prefix' => $evtId ? 'q_' : 'p_', 'is_header' => true, 'layout' => 'standard'];

foreach ($tplS as $s) {
    $qs = $tQS[$s['id']] ?? [];
    if (!empty($qs)) $rawSections[] = ['title' => $s['section_title'], 'qs' => $qs, 'prefix' => 'p_', 'layout' => $s['layout']];
}
foreach ($evtS as $s) {
    $qs = $eQS[$s['id']] ?? [];
    if (!empty($qs)) $rawSections[] = ['title' => $s['section_title'], 'qs' => $qs, 'prefix' => 'q_', 'layout' => $s['layout']];
}

$steps = [];
foreach ($rawSections as $sec) {
    if ($layout === 'stepper' || ($sec['layout'] ?? 'standard') === 'stepper') {
        if (!empty($sec['is_header'])) {
            $steps[] = $sec;
        } else if (($sec['layout'] ?? 'standard') === 'stepper') {
            foreach ($sec['qs'] as $q) {
                $steps[] = ['title' => $sec['title'], 'qs' => [$q], 'prefix' => $sec['prefix'], 'layout' => 'standard'];
            }
        } else {
            $steps[] = $sec;
        }
    } else {
        $steps[] = $sec;
    }
}

foreach ($steps as $i => $sec) {
    $isStepperMode = $layout === 'stepper' || array_search('stepper', array_column($rawSections, 'layout')) !== false;
    
    $activeClass = ($isStepperMode && $i === 0) ? ' active' : '';
    $stepStyle = ($isStepperMode) ? " class='form-step$activeClass'" : "";
    $sectionLayout = $sec['layout'] ?? 'standard';
    $gridClass = ($sectionLayout === 'grid' && empty($sec['is_header'])) ? ' grid-layout' : '';
    
    $html .= "<div$stepStyle><div class='card mb-4 shadow-sm'><div class='card-body$gridClass'>";
    
    if (!empty($sec['is_header'])) {
        if ($sec['img']) {
            $imgSrc = filter_var($sec['img'], FILTER_VALIDATE_URL) ? $sec['img'] : "../form-generator/" . $sec['img'];
            $html .= "<img src='$imgSrc' class='rounded-3 mb-4 w-100' style='max-height:200px;object-fit:cover;'>";
        }
        $html .= "<h4 class='mb-2'>" . htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') . "</h4>";
        if ($sec['desc']) $html .= "<p class='text-muted mb-4'>" . htmlspecialchars($sec['desc'], ENT_QUOTES, 'UTF-8') . "</p>";
        foreach ($sec['qs'] as $q) $html .= renderQ($q, $sec['prefix'], $qNames);
    } else {
        if ($sec['title']) $html .= "<h5 class='section-title'>" . htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8') . "</h5>";
        foreach ($sec['qs'] as $q) $html .= renderQ($q, $sec['prefix'], $qNames);
    }

    if ($isStepperMode) {
        $html .= "<div class='mt-4 d-flex justify-content-between'>";
        if ($i > 0) $html .= "<button type='button' class='btn-nav' onclick='moveStep(-1)'>← Back</button>";
        else $html .= "<div></div>";
        
        if ($i < count($steps) - 1) {
            $html .= "<button type='button' class='btn-nav' onclick='moveStep(1)'>Next →</button>";
        } else {
            $html .= "<button type='button' class='btn btn-submit' style='width:auto;padding-left:40px;padding-right:40px;'>Submit Feedback</button>";
        }
        $html .= "</div>";
    }
    
    $html .= "</div></div></div>";
}

if (!$isStepperMode) {
    $html .= "<button type='button' class='btn btn-submit'>Submit Feedback</button>";
}

$html .= "</div></div></div></body></html>";

echo $html;
?>