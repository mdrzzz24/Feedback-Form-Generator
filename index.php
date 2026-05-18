<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
$host = 'localhost';
$dbname = 'feedback_db';
$username = 'root';
$password = '';

$pdo = null;
$error = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $error = "DB Error: " . $e->getMessage();
}

$msg = '';
$msgType = 'success';
$tab = 'templates';
if (isset($_GET['tab'])) {
    $tab = $_GET['tab'] == 'events' ? 'events' : 'templates';
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        switch ($action) {
            case 'create_template':
                $stmt = $pdo->prepare("INSERT INTO form_generator_template (template_name) VALUES (?)");
                $stmt->execute([$_POST['name']]);
                $newId = $pdo->lastInsertId();
                // Create default section
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_sections (template_id, section_title, sort_order) VALUES (?, 'Participant Information', 1)");
                $stmt->execute([$newId]);
                $sectionId = $pdo->lastInsertId();
                // Add default questions
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_questions (template_id, section_id, question_text, question_type, is_required, options, sort_order) VALUES (?, ?, 'Full Name', 'text', 1, '', 1)");
                $stmt->execute([$newId, $sectionId]);
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_questions (template_id, section_id, question_text, question_type, is_required, options, sort_order) VALUES (?, ?, 'Email', 'email', 1, '', 2)");
                $stmt->execute([$newId, $sectionId]);
                $msg = "Template successfully created!";
                break;

            case 'create_template_section':
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_generator_template_sections WHERE template_id = ?");
                $stmt->execute([$_POST['tid']]);
                $order = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_sections (template_id, section_title, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['tid'], $_POST['stitle'], $order]);
                $newSectionId = $pdo->lastInsertId();
                // Add default question
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_questions (template_id, section_id, question_text, question_type, is_required, sort_order) VALUES (?, ?, 'New question', 'text', 0, 1)");
                $stmt->execute([$_POST['tid'], $newSectionId]);
                $msg = "Section added!";
                break;

            case 'del_template_section':
                $stmt = $pdo->prepare("DELETE FROM form_generator_template_questions WHERE section_id = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_template_sections WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Section deleted!";
                break;

            case 'add_template_q':
                $opts = '';
                if (!empty($_POST['opts'])) {
                    $arr = explode(',', $_POST['opts']);
                    $arr = array_map('trim', $arr);
                    $opts = json_encode($arr);
                }
                $parent_qid = !empty($_POST['parent_question_id']) ? $_POST['parent_question_id'] : null;
                $parent_opt = !empty($_POST['parent_option_value']) ? $_POST['parent_option_value'] : null;
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_generator_template_questions WHERE section_id = ?");
                $stmt->execute([$_POST['sid']]);
                $order = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO form_generator_template_questions (template_id, section_id, question_text, question_type, is_required, options, sort_order, parent_question_id, parent_option_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['tid'], $_POST['sid'], $_POST['qtext'], $_POST['qtype'], isset($_POST['req']) ? 1 : 0, $opts, $order, $parent_qid, $parent_opt
                ]);
                $msg = "Question added!";
                break;

            case 'del_template_q':
                $stmt = $pdo->prepare("DELETE FROM form_generator_template_questions WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Question deleted!";
                break;

            case 'update_template_q':
                $stmt = $pdo->prepare("UPDATE form_generator_template_questions SET question_text = ?, question_type = ?, is_required = ?, options = ?, parent_question_id = ?, parent_option_value = ? WHERE id = ?");
                $opts = '';
                if (!empty($_POST['opts'])) {
                    $arr = array_map('trim', explode(',', $_POST['opts']));
                    $opts = json_encode($arr);
                }
                $parent_qid = !empty($_POST['parent_question_id']) ? $_POST['parent_question_id'] : null;
                $parent_opt = !empty($_POST['parent_option_value']) ? $_POST['parent_option_value'] : null;
                $stmt->execute([
                    $_POST['qtext'], $_POST['qtype'], isset($_POST['req']) ? 1 : 0, $opts, $parent_qid, $parent_opt, $_POST['id']
                ]);
                $msg = "Question updated!";
                break;

            case 'del_template':
                $stmt = $pdo->prepare("DELETE FROM form_generator_template_questions WHERE template_id = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_template_sections WHERE template_id = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_template WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Template deleted!";
                $msgType = 'danger';
                break;

            case 'create_event':
                // Check if new or update
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM form_generator_config WHERE event_id = ?");
                $stmt->execute([$_POST['eid']]);
                $isNew = $stmt->fetchColumn() == 0;

                $img = $_POST['eimg'] ?? '';
                if (isset($_FILES['eupload']) && $_FILES['eupload']['error'] === UPLOAD_ERR_OK) {
                    if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                    $ext = pathinfo($_FILES['eupload']['name'], PATHINFO_EXTENSION);
                    $newName = 'header_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['eupload']['tmp_name'], 'uploads/' . $newName)) {
                        $img = 'uploads/' . $newName;
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO form_generator_config (event_name, event_id, header_image, description, template_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE event_name=VALUES(event_name), header_image=VALUES(header_image), description=VALUES(description), template_id=VALUES(template_id)");
                $tplId = empty($_POST['etpl']) ? null : $_POST['etpl'];
                $edesc = $_POST['edesc'] ?? '';
                $stmt->execute([$_POST['ename'], $_POST['eid'], $img, $edesc, $tplId]);

                // If template selected AND it's a new event, copy sections and questions
                if ($isNew && !empty($_POST['etpl'])) {
                    // Copy sections
                    $stmt = $pdo->prepare("SELECT * FROM form_generator_template_sections WHERE template_id = ?");
                    $stmt->execute([$_POST['etpl']]);
                    $tplSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $sectionMap = [];
                    foreach ($tplSections as $s) {
                        $stmt = $pdo->prepare("INSERT INTO form_generator_event_sections (event_id, section_title, sort_order) VALUES (?, ?, ?)");
                        $stmt->execute([$_POST['eid'], $s['section_title'], $s['sort_order']]);
                        $sectionMap[$s['id']] = $pdo->lastInsertId();
                    }
                    // Copy questions
                    $stmt = $pdo->prepare("SELECT * FROM form_generator_template_questions WHERE template_id = ?");
                    $stmt->execute([$_POST['etpl']]);
                    $tplQs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tplQs as $q) {
                        $newSectionId = isset($sectionMap[$q['section_id']]) ? $sectionMap[$q['section_id']] : 0;
                        $stmt = $pdo->prepare("INSERT INTO form_generator_questions (id_event, section_id, question_text, question_type, is_required, options, sort_order, parent_question_id, parent_option_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$_POST['eid'], $newSectionId, $q['question_text'], $q['question_type'], $q['is_required'], $q['options'], $q['sort_order'], $q['parent_question_id'], $q['parent_option_value']]);
                    }
                }
                $msg = "Event successfully created/updated!";
                $tab = 'events';
                break;

            case 'move_template_section':
                $stmt = $pdo->prepare("SELECT sort_order FROM form_generator_template_sections WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $current = $stmt->fetchColumn();
                if ($_POST['dir'] === 'up') {
                    $stmt = $pdo->prepare("SELECT id, sort_order FROM form_generator_template_sections WHERE template_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
                } else {
                    $stmt = $pdo->prepare("SELECT id, sort_order FROM form_generator_template_sections WHERE template_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
                }
                $stmt->execute([$_POST['tid'], $current]);
                $other = $stmt->fetch();
                if ($other) {
                    $pdo->prepare("UPDATE form_generator_template_sections SET sort_order = ? WHERE id = ?")->execute([$other['sort_order'], $_POST['id']]);
                    $pdo->prepare("UPDATE form_generator_template_sections SET sort_order = ? WHERE id = ?")->execute([$current, $other['id']]);
                }
                break;

            case 'move_event_section':
                $stmt = $pdo->prepare("SELECT sort_order FROM form_generator_event_sections WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $current = $stmt->fetchColumn();
                if ($_POST['dir'] === 'up') {
                    $stmt = $pdo->prepare("SELECT id, sort_order FROM form_generator_event_sections WHERE event_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1");
                } else {
                    $stmt = $pdo->prepare("SELECT id, sort_order FROM form_generator_event_sections WHERE event_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1");
                }
                $stmt->execute([$_POST['eid'], $current]);
                $other = $stmt->fetch();
                if ($other) {
                    $pdo->prepare("UPDATE form_generator_event_sections SET sort_order = ? WHERE id = ?")->execute([$other['sort_order'], $_POST['id']]);
                    $pdo->prepare("UPDATE form_generator_event_sections SET sort_order = ? WHERE id = ?")->execute([$current, $other['id']]);
                }
                $tab = 'events';
                break;

            case 'create_event_section':
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_generator_event_sections WHERE event_id = ?");
                $stmt->execute([$_POST['eid']]);
                $order = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO form_generator_event_sections (event_id, section_title, sort_order) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['eid'], $_POST['stitle'], $order]);
                $newSectionId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO form_generator_questions (id_event, section_id, question_text, question_type, is_required, sort_order) VALUES (?, ?, 'New question', 'text', 0, 1)");
                $stmt->execute([$_POST['eid'], $newSectionId]);
                $msg = "Section added!";
                $tab = 'events';
                break;

            case 'del_event_section':
                $stmt = $pdo->prepare("DELETE FROM form_generator_questions WHERE section_id = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_event_sections WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Section deleted!";
                $tab = 'events';
                break;

            case 'add_event_q':
                $opts = '';
                if (!empty($_POST['opts'])) {
                    $arr = array_map('trim', explode(',', $_POST['opts']));
                    $opts = json_encode($arr);
                }
                $parent_qid = !empty($_POST['parent_question_id']) ? $_POST['parent_question_id'] : null;
                $parent_opt = !empty($_POST['parent_option_value']) ? $_POST['parent_option_value'] : null;
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM form_generator_questions WHERE section_id = ?");
                $stmt->execute([$_POST['sid']]);
                $order = $stmt->fetchColumn();
                $stmt = $pdo->prepare("INSERT INTO form_generator_questions (id_event, section_id, question_text, question_type, is_required, options, sort_order, parent_question_id, parent_option_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['eid'], $_POST['sid'], $_POST['qtext'], $_POST['qtype'], isset($_POST['req']) ? 1 : 0, $opts, $order, $parent_qid, $parent_opt
                ]);
                $msg = "Question added!";
                $tab = 'events';
                break;

            case 'del_event_q':
                $stmt = $pdo->prepare("DELETE FROM form_generator_questions WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Question deleted!";
                $tab = 'events';
                break;

            case 'update_event_q':
                $opts = '';
                if (!empty($_POST['opts'])) {
                    $arr = array_map('trim', explode(',', $_POST['opts']));
                    $opts = json_encode($arr);
                }
                $parent_qid = !empty($_POST['parent_question_id']) ? $_POST['parent_question_id'] : null;
                $parent_opt = !empty($_POST['parent_option_value']) ? $_POST['parent_option_value'] : null;
                $stmt = $pdo->prepare("UPDATE form_generator_questions SET question_text = ?, question_type = ?, is_required = ?, options = ?, parent_question_id = ?, parent_option_value = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['qtext'], $_POST['qtype'], isset($_POST['req']) ? 1 : 0, $opts, $parent_qid, $parent_opt, $_POST['id']
                ]);
                $msg = "Question updated!";
                $tab = 'events';
                break;

            case 'del_event':
                $stmt = $pdo->prepare("DELETE FROM form_generator_questions WHERE id_event = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_event_sections WHERE event_id = ?");
                $stmt->execute([$_POST['id']]);
                $stmt = $pdo->prepare("DELETE FROM form_generator_config WHERE event_id = ?");
                $stmt->execute([$_POST['id']]);
                $msg = "Event deleted!";
                $msgType = 'danger';
                $tab = 'events';
                break;

            case 'generate':
                $eid = $_POST['eid'];
                $stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?");
                $stmt->execute([$eid]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($event) {
                    $tplQ = [];
                    $tplS = [];
                    $stmt = $pdo->prepare("SELECT * FROM form_generator_event_sections WHERE event_id = ? ORDER BY sort_order ASC, id ASC");
                    $stmt->execute([$eid]);
                    $evtS = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE id_event = ? ORDER BY sort_order ASC, id ASC");
                    $stmt->execute([$eid]);
                    $evtQ = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $dir = __DIR__ . "/generated_forms/$eid";
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    file_put_contents("$dir/index.php", buildForm($event, $tplQ, $evtQ, $tplS, $evtS));
                    file_put_contents("$dir/submit.php", buildSubmit($eid, $tplQ, $evtQ));
                    $msg = "Form successfully generated! <a href='generated_forms/$eid/index.php' target='_blank' class='alert-link'>Open Form</a>";
                }
                $tab = 'events';
                break;
        }
    } catch (Exception $e) {
        $msg = "Error: " . $e->getMessage();
        $msgType = 'danger';
    }
}

// Load Data
$templates = [];
$events = [];
$tplId = 0;
$evtId = '';
$tpl = null;
$tplQs = [];
$tplSections = [];
$evt = null;
$evtQs = [];
$evtSections = [];

if ($pdo) {
    $templates = $pdo->query("SELECT * FROM form_generator_template ORDER BY id DESC")->fetchAll();
    $events = $pdo->query("SELECT * FROM form_generator_config ORDER BY created_at DESC")->fetchAll();

    if (isset($_GET['tpl']) && is_numeric($_GET['tpl'])) {
        $tplId = intval($_GET['tpl']);
        $stmt = $pdo->prepare("SELECT * FROM form_generator_template WHERE id = ?");
        $stmt->execute([$tplId]);
        $tpl = $stmt->fetch();
        if ($tpl) {
            $stmt = $pdo->prepare("SELECT * FROM form_generator_template_sections WHERE template_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$tplId]);
            $tplSections = $stmt->fetchAll();
            foreach ($tplSections as $s) {
                $stmt = $pdo->prepare("SELECT * FROM form_generator_template_questions WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
                $stmt->execute([$s['id']]);
                $tplQs[$s['id']] = $stmt->fetchAll();
            }
        }
    }

    if (isset($_GET['evt']) && !empty($_GET['evt'])) {
        $evtId = $_GET['evt'];
        $stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?");
        $stmt->execute([$evtId]);
        $evt = $stmt->fetch();
        if ($evt) {
            $stmt = $pdo->prepare("SELECT * FROM form_generator_event_sections WHERE event_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$evtId]);
            $evtSections = $stmt->fetchAll();
            foreach ($evtSections as $s) {
                $stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE section_id = ? ORDER BY sort_order ASC, id ASC");
                $stmt->execute([$s['id']]);
                $evtQs[$s['id']] = $stmt->fetchAll();
            }
        }
        $tab = 'events';
    }
}

function buildForm($ev, $tQ, $eQ, $tS = [], $eS = []) {
    $name = htmlspecialchars($ev['event_name'], ENT_QUOTES, 'UTF-8');
    $eid = htmlspecialchars($ev['event_id'], ENT_QUOTES, 'UTF-8');
    $img = htmlspecialchars($ev['header_image'] ?? '', ENT_QUOTES, 'UTF-8');
    $desc = htmlspecialchars($ev['description'] ?? '', ENT_QUOTES, 'UTF-8');

    $tQS = [0 => []];
    foreach ($tS as $s) $tQS[$s['id']] = [];
    foreach ($tQ as $q) $tQS[$q['section_id'] ?? 0][] = $q;

    $eQS = [0 => []];
    foreach ($eS as $s) $eQS[$s['id']] = [];
    foreach ($eQ as $q) $eQS[$q['section_id'] ?? 0][] = $q;

    $qNames = [];
    foreach ($tQ as $q) {
        $n = 'p_' . $q['id'];
        if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $n = 'name';
        elseif (stripos($q['question_text'], 'email') !== false) $n = 'email';
        elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';
        $qNames['p_' . $q['id']] = $n;
    }
    foreach ($eQ as $q) {
        $n = 'q_' . $q['id'];
        if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $n = 'name';
        elseif (stripos($q['question_text'], 'email') !== false) $n = 'email';
        elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';
        $qNames['q_' . $q['id']] = $n;
    }

    $renderQ = function($q, $prefix) use ($qNames) {
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
            $h .= "<input type='$t' class='form-control' name='$n' $r>";
        } elseif ($t === 'textarea') {
            $h .= "<textarea class='form-control' name='$n' rows='3' $r></textarea>";
        } elseif (in_array($t, ['radio', 'checkbox'])) {
            $h = "<div class='mb-4' id='wrap_{$n}'$parent><p class='mb-3 fw-semibold'>$txt " . ($r ? '<span class=\"text-danger\">*</span>' : '') . "</p><div class='row g-2'>";
            foreach ($opts as $o) {
                $oid = md5($n . $o);
                $h .= "<div class='col-md-6'><div class='form-check'><input type='$t' class='form-check-input' name='{$n}[]' id='$oid' value='" . htmlspecialchars($o, ENT_QUOTES, 'UTF-8') . "' $r><label class='form-check-label' for='$oid'>$o</label></div></div>";
            }
            $h .= "</div>";
        }
        $h .= "</div>";
        return $h;
    };

    $allSectionsHtml = '';

    $imgSrc = $img;
    if (!filter_var($img, FILTER_VALIDATE_URL) && !empty($img)) {
        $imgSrc = "../form-generator/" . $img;
    }

    // Header section
    $pZeroHtml = '';
    foreach ($tQS[0] as $q) $pZeroHtml .= $renderQ($q, 'p_');
    $allSectionsHtml .= "<div class='card mb-4 shadow-sm'><div class='card-body'>";
    if (!empty($img)) {
        $allSectionsHtml .= "<img src='$imgSrc' class='rounded-3 mb-4 w-100' style='max-height:200px;object-fit:cover;'>";
    }
    $allSectionsHtml .= "<h4 class='mb-2'>$name</h4>";
    if (!empty($desc)) {
        $allSectionsHtml .= "<p class='text-muted mb-0'>$desc</p>";
    }
    $allSectionsHtml .= "$pZeroHtml</div></div>";

    foreach ($tS as $s) {
        $qs = $tQS[$s['id']] ?? [];
        if (empty($qs)) continue;
        $sh = "<div class='card mb-4 shadow-sm'><div class='card-body'><h5 class='border-bottom pb-2 mb-3'>" . htmlspecialchars($s['section_title'], ENT_QUOTES, 'UTF-8') . "</h5>";
        foreach ($qs as $q) $sh .= $renderQ($q, 'p_');
        $allSectionsHtml .= $sh . "</div></div>";
    }

    if (!empty($eQS[0])) {
        $eh = "<div class='card mb-4 shadow-sm'><div class='card-body'>";
        foreach ($eQS[0] as $q) $eh .= $renderQ($q, 'q_');
        $allSectionsHtml .= $eh . "</div></div>";
    }

    foreach ($eS as $s) {
        $qs = $eQS[$s['id']] ?? [];
        if (empty($qs)) continue;
        $sh = "<div class='card mb-4 shadow-sm'><div class='card-body'><h5 class='border-bottom pb-2 mb-3'>" . htmlspecialchars($s['section_title'], ENT_QUOTES, 'UTF-8') . "</h5>";
        foreach ($qs as $q) $sh .= $renderQ($q, 'q_');
        $allSectionsHtml .= $sh . "</div></div>";
    }

    return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Feedback | $name</title>" .
        "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css' rel='stylesheet'>" .
        "<style>body{background:#f0f2f5;padding:40px 0}.card{border:0;border-radius:16px}.form-label{font-weight:600;color:#444}.form-control{border-radius:10px;padding:12px;border:1px solid #ddd}.form-control:focus{border-color:#4a6fa5;box-shadow:0 0 0 3px rgba(74,111,165,.15)}.form-check-input:checked{background-color:#4a6fa5;border-color:#4a6fa5}.btn-submit{background:#4a6fa5;color:#fff;border:none;border-radius:12px;padding:16px;font-weight:600;width:100%;font-size:1.1rem}.btn-submit:hover{background:#3d5d8a}</style>" .
        "<script>document.addEventListener('DOMContentLoaded',function(){\n  var all = document.querySelectorAll('[data-parent-q]');\n  all.forEach(function(el){\n    var parentQ = el.getAttribute('data-parent-q');\n    var parentOpt = el.getAttribute('data-parent-opt');\n    var parentInputs = document.getElementsByName(parentQ + '[]');\n    if(parentInputs.length === 0) parentInputs = document.getElementsByName(parentQ);\n    var showIf = function(){\n      var show = false;\n      parentInputs.forEach(function(inp){\n        if(inp.type === 'radio' || inp.type === 'checkbox') {\n          if(inp.checked && inp.value==parentOpt) show=true;\n        } else {\n          if(inp.value == parentOpt) show=true;\n        }\n      });\n      el.style.display = show ? '' : 'none';\n    };\n    parentInputs.forEach(function(inp){\n      inp.addEventListener('change', showIf);\n      inp.addEventListener('input', showIf);\n    });\n    showIf();\n  });\n});</script>" .
        "</head><body>" .
        "<div class='container'><div class='row justify-content-center'><div class='col-lg-8'>" .
        "<form action='submit.php' method='POST'>" .
        "<input type='hidden' name='id_event' value='$eid'>" .
        $allSectionsHtml .
        "<button type='submit' class='btn btn-submit w-100'>Submit Feedback</button></form></div></div></div>" .
        "</body></html>";
}

function buildSubmit($eid, $tQ, $eQ) {
    $n = [];
    foreach ($tQ as $q) {
        $name = 'p_' . $q['id'];
        if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $name = 'name';
        elseif (stripos($q['question_text'], 'email') !== false) $name = 'email';
        elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $name = 'companyName';
        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $name = 'jobTitle';
        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $name = 'mobileNumber';
        $n[] = $name;
    }
    foreach ($eQ as $q) {
        $name = 'q_' . $q['id'];
        if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $name = 'name';
        elseif (stripos($q['question_text'], 'email') !== false) $name = 'email';
        elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $name = 'companyName';
        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $name = 'jobTitle';
        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $name = 'mobileNumber';
        $n[] = $name;
    }
    $nj = json_encode($n);
    return "<?php " .
        "try { \$p=new PDO('mysql:host=localhost;dbname=feedback_db', 'root', ''); } catch(Exception \$e) { die('DB Error'); } " .
        "if (\$_SERVER['REQUEST_METHOD']==='POST') { " .
        "\$d=\$_POST; " .
        "\$s=\$p->prepare('INSERT INTO respondent(id_event,full_name,email_1,company_name,job_title,mobile_phone)VALUES(?,?,?,?,?,?)'); " .
        "\$s->execute([\$d['id_event']??'$eid', \$d['name']??'', \$d['email']??'', \$d['companyName']??'', \$d['jobTitle']??'', \$d['mobileNumber']??'']); " .
        "\$rid=\$p->lastInsertId(); " .
        "\$s=\$p->prepare('INSERT INTO answer(id_feedback,id_respondent,id_question,answer_text)VALUES(?,?,?,?)'); " .
        "foreach($nj as \$k) { if(isset(\$d[\$k])) { \$v=is_array(\$d[\$k])?implode('; ',\$d[\$k]):\$d[\$k]; \$s->execute([\$d['id_event']??'$eid', \$rid, \$k, \$v]); } } " .
        "header('Location:index.php?ok'); exit; } " .
        "header('Location:index.php');";
}

$tplName = $tpl ? $tpl['template_name'] : '';
$evtName = $evt ? $evt['event_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback Form Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #4a6fa5;
            --primary-dark: #3d5d8a;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-light); }
        .sidebar { background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); }
        .sidebar-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding: 20px; }
        .nav-item { padding: 12px 20px; transition: all 0.2s; border-radius: 0; text-decoration: none; color: rgba(255,255,255,0.7); display: flex; align-items: center; gap: 10px; }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: white; }
        .nav-item.active { background: var(--primary); color: white; }
        .section-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: box-shadow 0.3s;
        }
        .section-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header h5 { margin: 0; font-weight: 600; }
        .section-body { padding: 20px; }
        .question-item {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }
        .question-item:hover { border-color: var(--primary); }
        .question-item:last-child { margin-bottom: 0; }
        .btn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .btn-add-section {
            background: white;
            border: 2px dashed var(--border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-add-section:hover { border-color: var(--primary); color: var(--primary); }
        .type-badge { background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .required-badge { background: #fee2e2; color: #b91c1c; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 500; }
        .form-control, .form-select { border-radius: 10px; padding: 10px 14px; border: 1px solid var(--border); }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(74,111,165,0.15); }
        .btn-primary { background: var(--primary); border: none; border-radius: 10px; padding: 10px 20px; font-weight: 500; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: none; border-radius: 10px; }
        .btn-secondary:hover { background: #e2e8f0; }
        .action-btn { width: 36px; height: 36px; border-radius: 8px; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; }
        .action-btn.edit { background: #e0e7ff; color: #4338ca; }
        .action-btn.delete { background: #fee2e2; color: #b91c1c; }
        .action-btn.move { background: #f1f5f9; color: #64748b; }
        .action-btn:hover { transform: scale(1.1); }
        .modal-section { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-section.active { display: flex; align-items: center; justify-content: center; }
        .modal-content-section { background: white; border-radius: 16px; padding: 24px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .empty-section { text-align: center; padding: 40px; color: #64748b; }
        .section-number {
            background: var(--primary);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <h3 class="text-white mb-0 fw-bold">Form Generator</h3>
    </div>
    <nav class="sidebar-nav">
        <a href="home.php" class="nav-item">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Home
        </a>
        <div class="px-3 py-2 mt-2">
            <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.05em;">Menu</small>
        </div>
        <a href="?tab=templates" class="nav-item <?php echo $tab === 'templates' ? 'active' : ''; ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Templates
        </a>
        <a href="?tab=events" class="nav-item <?php echo $tab === 'events' ? 'active' : ''; ?>">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Events
        </a>

        <div class="px-3 py-2 mt-3">
            <small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;letter-spacing:0.05em;">
                <?php echo $tab === 'templates' ? 'Templates' : 'Events'; ?>
            </small>
        </div>

        <?php if ($tab === 'templates'): ?>
            <?php foreach ($templates as $t): ?>
                <a href="?tab=templates&tpl=<?php echo $t['id']; ?>" class="nav-item <?php echo $tplId == $t['id'] ? 'active' : ''; ?>" style="font-size:0.85rem; padding-left: 52px;">
                    <?php echo htmlspecialchars($t['template_name']); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($events as $e): ?>
                <a href="?tab=events&evt=<?php echo urlencode($e['event_id']); ?>" class="nav-item <?php echo $evtId == $e['event_id'] ? 'active' : ''; ?>" style="font-size:0.85rem; padding-left: 52px;">
                    <?php echo htmlspecialchars($e['event_name']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
</aside>

<main class="main-content">
    <section class="editor-pane">
        <?php if ($msg): ?>
            <div class="alert alert-<?php echo $msgType; ?> py-3 px-4 rounded-3 mb-4" style="animation: fadeIn 0.3s;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($tab === 'templates'): ?>
            <?php if (!$tplId): ?>
                <div class="card border-0 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <h4 class="mb-4 fw-bold">Create New Template</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_template">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Template Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g.: Workshop Feedback Form" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Create Template
                            </button>
                        </form>
                    </div>
                </div>
                <div class="empty-state mt-5 text-center">
                    <svg width="64" height="64" fill="none" stroke="#cbd5e1" stroke-width="1.5" viewBox="0 0 24 24" class="mb-4"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <p class="text-muted">Select a template from the sidebar or create a new one</p>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($tplName); ?></h2>
                        <small class="text-muted"><?php echo count($tplSections); ?> sections</small>
                    </div>
                    <form method="POST" onsubmit="return confirm('Delete this template?');">
                        <input type="hidden" name="action" value="del_template">
                        <input type="hidden" name="id" value="<?php echo $tplId; ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Delete Template</button>
                    </form>
                </div>

                <!-- List Sections -->
                <?php
                $sectionNum = 1;
                foreach ($tplSections as $s):
                    $qs = isset($tplQs[$s['id']]) ? $tplQs[$s['id']] : [];
                ?>
                    <div class="section-card" data-section-id="<?php echo $s['id']; ?>">
                        <div class="section-header">
                            <div class="d-flex align-items-center gap-3">
                                <span class="section-number"><?php echo $sectionNum++; ?></span>
                                <h5 class="mb-0"><?php echo htmlspecialchars($s['section_title']); ?></h5>
                                <span class="badge" style="background:rgba(255,255,255,0.2);color:white;"><?php echo count($qs); ?> questions</span>
                            </div>
                            <div class="d-flex gap-1">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="move_template_section">
                                    <input type="hidden" name="tid" value="<?php echo $tplId; ?>">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="dir" value="up">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">↑</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="move_template_section">
                                    <input type="hidden" name="tid" value="<?php echo $tplId; ?>">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="dir" value="down">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">↓</button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section?');">
                                    <input type="hidden" name="action" value="del_template_section">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">×</button>
                                </form>
                            </div>
                        </div>
                        <div class="section-body">
                            <?php foreach ($qs as $q): ?>
                                <div class="question-item" data-q-id="<?php echo $q['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <input type="text" class="form-control fw-semibold q-text-input" value="<?php echo htmlspecialchars($q['question_text']); ?>"
                                                onblur="updateQuestion(<?php echo $q['id']; ?>, this.value, '<?php echo $tab; ?>')">
                                        </div>
                                        <div class="d-flex gap-1 ms-3">
                                            <button type="button" class="action-btn edit" onclick="openQModal('edit', <?php echo $q['id']; ?>, '<?php echo addslashes($q['question_text']); ?>', '<?php echo $q['question_type']; ?>', <?php echo $q['is_required']; ?>, '<?php echo addslashes($q['options'] ?? ''); ?>', <?php echo $tplId; ?>, <?php echo $s['id']; ?>, '<?php echo $q['parent_question_id']; ?>', '<?php echo addslashes($q['parent_option_value'] ?? ''); ?>')" title="Edit">✎</button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="del_template_q">
                                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete">×</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="type-badge" style="background:#f1f5f9;color:#475569;">ID: <?php echo $q['id']; ?></span>
                                        <span class="type-badge"><?php echo $q['question_type']; ?></span>
                                        <?php if ($q['is_required']): ?>
                                            <span class="required-badge">Required</span>
                                        <?php endif; ?>
                                        <?php if ($q['parent_question_id']): ?>
                                            <span class="type-badge" style="background:#fef9c3;color:#854d0e;">Parent: <?php echo $q['parent_question_id']; ?> (<?php echo htmlspecialchars($q['parent_option_value']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Add Question Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="add_template_q">
                                <input type="hidden" name="tid" value="<?php echo $tplId; ?>">
                                <input type="hidden" name="sid" value="<?php echo $s['id']; ?>">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" name="qtext" class="form-control" placeholder="Add question..." required>
                                    </div>
                                    <div class="col-3">
                                        <select name="qtype" class="form-select">
                                            <option value="text">Text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone</option>
                                            <option value="textarea">Paragraph</option>
                                            <option value="radio">Single Choice</option>
                                            <option value="checkbox">Multiple Choice</option>
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-primary w-100">Add</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Add New Section -->
                <form method="POST">
                    <input type="hidden" name="action" value="create_template_section">
                    <input type="hidden" name="tid" value="<?php echo $tplId; ?>">
                    <div class="btn-add-section" onclick="this.querySelector('input').focus()">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="mb-2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <input type="text" name="stitle" class="form-control mx-auto" style="max-width:300px;" placeholder="New section name..." required>
                        <button type="submit" class="btn btn-primary mt-2" style="width:200px;">Add Section</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php elseif ($tab === 'events'): ?>
            <?php if (!$evtId): ?>
                <div class="card border-0 shadow-sm" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <h4 class="mb-4 fw-bold">Create New Event</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_event">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Event Name</label>
                                <input type="text" name="ename" class="form-control" placeholder="e.g.: National Seminar 2024" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Event ID (URL Slug)</label>
                                <input type="text" name="eid" class="form-control" placeholder="e.g.: seminar-2024" required pattern="[a-zA-Z0-9_-]+">
                                <small class="text-muted">Used for form URL: localhost/generated_forms/[id-event]/</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Base Template</label>
                                <select name="etpl" class="form-select">
                                    <option value="">-- No Template --</option>
                                    <?php foreach ($templates as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['template_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Template will automatically copy sections and questions</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Create Event</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1 fw-bold"><?php echo htmlspecialchars($evtName); ?></h2>
                        <small class="text-muted">ID: <?php echo htmlspecialchars($evtId); ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST">
                            <input type="hidden" name="action" value="generate">
                            <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                            <button type="submit" class="btn btn-primary">Generate Form</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete this event?');">
                            <input type="hidden" name="action" value="del_event">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($evtId); ?>">
                            <button type="submit" class="btn btn-secondary">Delete</button>
                        </form>
                    </div>
                </div>

                <!-- Event Settings -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:16px;">
                    <div class="card-body p-4">
                        <h5 class="mb-3 fw-semibold">Event Settings</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="create_event">
                            <input type="hidden" name="ename" value="<?php echo htmlspecialchars($evt['event_name']); ?>">
                            <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                            <input type="hidden" name="etpl" value="<?php echo $evt['template_id']; ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Header Image</label>
                                <div class="d-flex gap-2">
                                    <input type="file" name="eupload" class="form-control" accept="image/*">
                                    <input type="text" name="eimg" class="form-control" value="<?php echo htmlspecialchars($evt['header_image'] ?? ''); ?>" placeholder="Image URL">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="edesc" class="form-control" rows="2"><?php echo htmlspecialchars($evt['description'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-secondary">Save Settings</button>
                        </form>
                    </div>
                </div>

                <!-- List Sections -->
                <?php
                $sectionNum = 1;
                foreach ($evtSections as $s):
                    $qs = isset($evtQs[$s['id']]) ? $evtQs[$s['id']] : [];
                ?>
                    <div class="section-card" data-section-id="<?php echo $s['id']; ?>">
                        <div class="section-header">
                            <div class="d-flex align-items-center gap-3">
                                <span class="section-number"><?php echo $sectionNum++; ?></span>
                                <h5 class="mb-0"><?php echo htmlspecialchars($s['section_title']); ?></h5>
                                <span class="badge" style="background:rgba(255,255,255,0.2);color:white;"><?php echo count($qs); ?> questions</span>
                            </div>
                            <div class="d-flex gap-1">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="move_event_section">
                                    <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="dir" value="up">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">↑</button>
                                </form>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="move_event_section">
                                    <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <input type="hidden" name="dir" value="down">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">↓</button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section?');">
                                    <input type="hidden" name="action" value="del_event_section">
                                    <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-icon" style="background:rgba(255,255,255,0.2);color:white;">×</button>
                                </form>
                            </div>
                        </div>
                        <div class="section-body">
                            <?php foreach ($qs as $q): ?>
                                <div class="question-item" data-q-id="<?php echo $q['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="flex-grow-1">
                                            <input type="text" class="form-control fw-semibold q-text-input" value="<?php echo htmlspecialchars($q['question_text']); ?>"
                                                onblur="updateQuestion(<?php echo $q['id']; ?>, this.value, '<?php echo $tab; ?>')">
                                        </div>
                                        <div class="d-flex gap-1 ms-3">
                                            <button type="button" class="action-btn edit" onclick="openQModal('edit', <?php echo $q['id']; ?>, '<?php echo addslashes($q['question_text']); ?>', '<?php echo $q['question_type']; ?>', <?php echo $q['is_required']; ?>, '<?php echo addslashes($q['options'] ?? ''); ?>', '<?php echo htmlspecialchars($evtId); ?>', <?php echo $s['id']; ?>, '<?php echo $q['parent_question_id']; ?>', '<?php echo addslashes($q['parent_option_value'] ?? ''); ?>')" title="Edit">✎</button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="del_event_q">
                                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete">×</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="type-badge" style="background:#f1f5f9;color:#475569;">ID: <?php echo $q['id']; ?></span>
                                        <span class="type-badge"><?php echo $q['question_type']; ?></span>
                                        <?php if ($q['is_required']): ?>
                                            <span class="required-badge">Required</span>
                                        <?php endif; ?>
                                        <?php if ($q['parent_question_id']): ?>
                                            <span class="type-badge" style="background:#fef9c3;color:#854d0e;">Parent: <?php echo $q['parent_question_id']; ?> (<?php echo htmlspecialchars($q['parent_option_value']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Add Question Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="action" value="add_event_q">
                                <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                                <input type="hidden" name="sid" value="<?php echo $s['id']; ?>">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" name="qtext" class="form-control" placeholder="Add question..." required>
                                    </div>
                                    <div class="col-3">
                                        <select name="qtype" class="form-select">
                                            <option value="text">Text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone</option>
                                            <option value="textarea">Paragraph</option>
                                            <option value="radio">Single Choice</option>
                                            <option value="checkbox">Multiple Choice</option>
                                        </select>
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-primary w-100">Add</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Add New Section -->
                <form method="POST">
                    <input type="hidden" name="action" value="create_event_section">
                    <input type="hidden" name="eid" value="<?php echo htmlspecialchars($evtId); ?>">
                    <div class="btn-add-section" onclick="this.querySelector('input').focus()">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="mb-2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <input type="text" name="stitle" class="form-control mx-auto" style="max-width:300px;" placeholder="New section name..." required>
                        <button type="submit" class="btn btn-primary mt-2" style="width:200px;">Add Section</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="preview-pane">
        <div class="preview-header">
            <span>LIVE PREVIEW</span>
            <button class="btn btn-sm btn-secondary" onclick="location.reload()">Refresh</button>
        </div>
        <?php
            $previewUrl = 'preview.php';
            if ($evtId) $previewUrl .= '?evt=' . urlencode($evtId);
            elseif ($tplId) $previewUrl .= '?tpl=' . $tplId;
        ?>
        <iframe src="<?php echo $previewUrl; ?>"></iframe>
    </section>
</main>

<!-- Question Edit Modal -->
<div id="qModal" class="modal-section">
    <div class="modal-content-section">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0 fw-bold">Edit Question</h5>
            <button type="button" class="btn-close" onclick="closeQModal()"></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="qModalAction" value="">
            <input type="hidden" name="id" id="qModalId" value="">
            <input type="hidden" name="tid" id="qModalTid" value="">
            <input type="hidden" name="eid" id="qModalEid" value="">
            <input type="hidden" name="sid" id="qModalSid" value="">

            <div class="mb-3">
                <label class="form-label fw-semibold">Question</label>
                <input type="text" name="qtext" id="qModalText" class="form-control" required>
            </div>
            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label fw-semibold">Input Type</label>
                    <select name="qtype" id="qModalType" class="form-select">
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="tel">Phone</option>
                        <option value="textarea">Paragraph</option>
                        <option value="radio">Single Choice</option>
                        <option value="checkbox">Multiple Choice</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">&nbsp;</label>
                    <div class="form-check mt-2">
                        <input type="checkbox" name="req" id="qModalReq" class="form-check-input" value="1">
                        <label class="form-check-label" for="qModalReq">Required</label>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Options (comma separated)</label>
                <input type="text" name="opts" id="qModalOpts" class="form-control" placeholder="Option 1, Option 2, Option 3">
                <small class="text-muted">Use commas to separate options. Leave empty for normal text input.</small>
            </div>
            <div class="row mb-4">
                <div class="col-6">
                    <label class="form-label fw-semibold">Parent Question ID</label>
                    <input type="number" name="parent_question_id" id="qModalParentId" class="form-control" placeholder="Question ID">
                </div>
                <div class="col-6">
                    <label class="form-label fw-semibold">Parent Option Value</label>
                    <input type="text" name="parent_option_value" id="qModalParentOpt" class="form-control" placeholder="Trigger value">
                </div>
                <div class="col-12 mt-2">
                    <small class="text-muted">Fill this if this question should only appear if another question is answered with a specific value.</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Save</button>
        </form>
    </div>
</div>

<script>
function openQModal(mode, id, text, type, required, options, parentId, sectionId, parentQId = '', parentQOpt = '') {
    document.getElementById('qModal').classList.add('active');
    document.getElementById('qModalAction').value = mode === 'edit' ? 'update_' + (window.location.search.includes('evt=') ? 'event' : 'template') + '_q' : 'add_' + (window.location.search.includes('evt=') ? 'event' : 'template') + '_q';
    document.getElementById('qModalId').value = id;
    document.getElementById('qModalText').value = text;
    document.getElementById('qModalType').value = type;
    document.getElementById('qModalReq').checked = required == 1;
    document.getElementById('qModalOpts').value = options;
    document.getElementById('qModalParentId').value = parentQId;
    document.getElementById('qModalParentOpt').value = parentQOpt;

    if (window.location.search.includes('evt=')) {
        document.getElementById('qModalEid').value = parentId;
    } else {
        document.getElementById('qModalTid').value = parentId;
    }
    document.getElementById('qModalSid').value = sectionId;
}

function closeQModal() {
    document.getElementById('qModal').classList.remove('active');
}

function updateQuestion(id, text, tab) {
    console.log('Question ' + id + ' updated to: ' + text);
}

document.addEventListener('click', function(e) {
    if (e.target.id === 'qModal') closeQModal();
});
</script>

</body>
</html>