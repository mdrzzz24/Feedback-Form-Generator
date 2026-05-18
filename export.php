<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost'; $dbname = 'feedback_db'; $username = 'root'; $password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
} catch (PDOException $e) { die("DB Error"); }

$evtId = $_GET['evt'] ?? '';
if (!$evtId) die("Event ID missing");

// Fetch data
$stmt = $pdo->prepare("SELECT * FROM respondent WHERE id_event = ? AND deleted_at IS NULL ORDER BY created_at DESC");
$stmt->execute([$evtId]);
$respondents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE id_event = ? ORDER BY section_id ASC, sort_order ASC");
$stmt->execute([$evtId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$answersByRespondent = [];
$stmt = $pdo->prepare("SELECT * FROM answer WHERE id_feedback = ?");
$stmt->execute([$evtId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $answersByRespondent[$a['id_respondent']][$a['id_question']] = $a['answer_text'];
}

function getQuestionName($q) {
    $n = 'q_' . $q['id'];
    $txt = strtolower(trim($q['question_text']));
    if (in_array($txt, ['nama', 'name', 'full name', 'nama lengkap'])) return 'name';
    if ($txt === 'email') return 'email';
    if (in_array($txt, ['perusahaan', 'company', 'company name'])) return 'companyName';
    if (in_array($txt, ['jabatan', 'title', 'job title'])) return 'jobTitle';
    if (in_array($txt, ['telepon', 'phone', 'hp', 'mobile phone'])) return 'mobileNumber';
    return $n;
}

// Prepare CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=export_'.$evtId.'_'.date('Ymd').'.csv');
$output = fopen('php://output', 'w');

// Headers: Dynamically built from existing form questions
$header = ['Date']; // Always include Date
$questionMap = [];
foreach ($questions as $q) {
    $opts = json_decode($q['options'] ?? '[]', true);
    if (!empty($opts) && is_array($opts)) {
        foreach ($opts as $opt) {
            $colName = $q['question_text'] . ' (' . $opt . ')';
            $header[] = $colName;
            $questionMap[$q['id']][$opt] = $colName;
        }
    } else {
        $header[] = $q['question_text'];
        $questionMap[$q['id']]['text'] = $q['question_text'];
    }
}
fputcsv($output, $header);

// Rows
foreach ($respondents as $r) {
    $row = [$r['created_at']];
    
    foreach ($questions as $q) {
        $n = getQuestionName($q);
        
        // Map data from respondent row if it's a known field, otherwise from answer table
        if ($n === 'name') $ans = $r['full_name'];
        elseif ($n === 'email') $ans = $r['email_1'];
        elseif ($n === 'companyName') $ans = $r['company_name'];
        elseif ($n === 'jobTitle') $ans = $r['job_title'];
        elseif ($n === 'mobileNumber') $ans = $r['mobile_phone'];
        else $ans = $answersByRespondent[$r['id']][$n] ?? '';
        
        $opts = json_decode($q['options'] ?? '[]', true);
        
        if (!empty($opts) && is_array($opts)) {
            $selected = explode('; ', $ans);
            foreach ($opts as $opt) {
                $row[] = in_array($opt, $selected) ? $opt : '-';
            }
        } else {
            $row[] = $ans;
        }
    }
    fputcsv($output, $row);
}
fclose($output);
