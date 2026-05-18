<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
$host = 'localhost';
$dbname = 'feedback_db';
$username = 'root';
$password = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$evtId = $_GET['evt'] ?? '';

if (!$evtId) {
    header("Location: home.php");
    exit;
}

$showTrashedEvent = isset($_GET['show_trashed']);

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?" . ($showTrashedEvent ? "" : " AND deleted_at IS NULL"));
$stmt->execute([$evtId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}

// Fetch respondents
$resQuery = "SELECT * FROM respondent WHERE id_event = ? " . ($showTrashedEvent ? "" : " AND deleted_at IS NULL") . " ORDER BY created_at DESC";
$stmt = $pdo->prepare($resQuery);
$stmt->execute([$evtId]);
$respondents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all sections and questions
$stmt = $pdo->prepare("SELECT * FROM form_generator_event_sections WHERE event_id = ? ORDER BY sort_order ASC");
$stmt->execute([$evtId]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE id_event = ? ORDER BY section_id ASC, sort_order ASC");
$stmt->execute([$evtId]);
$allQs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$qsBySection = [];
foreach ($allQs as $q) {
    $qsBySection[$q['section_id']][] = $q;
}

// Analytics Logic
$analytics = [];
foreach ($allQs as $q) {
    if (in_array($q['question_type'], ['radio', 'checkbox'])) {
        $n = getQuestionName($q);

        $stmt = $pdo->prepare("SELECT a.answer_text FROM answer a JOIN respondent r ON a.id_respondent = r.id WHERE r.id_event = ? AND a.id_question = ?" . ($showTrashedEvent ? "" : " AND r.deleted_at IS NULL"));
        $stmt->execute([$evtId, $n]);
        $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stats = [];
        $options = json_decode($q['options'] ?? '[]', true) ?: [];
        foreach ($options as $opt) $stats[$opt] = 0;

        foreach ($answers as $ans) {
            $parts = explode('; ', $ans);
            foreach ($parts as $p) {
                if (isset($stats[$p])) $stats[$p]++;
                else $stats[$p] = 1;
            }
        }
        $analytics[] = [
            'question' => $q['question_text'],
            'stats' => $stats,
            'total' => count($answers)
        ];
    }
}

// Group answers by respondent for the table
$answersByRespondent = [];
$stmt = $pdo->prepare("SELECT * FROM answer WHERE id_feedback = ?");
$stmt->execute([$evtId]);
$rawAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rawAnswers as $a) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback Analytics | <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #4a6fa5; --bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1e293b; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .nav-tabs { border-bottom: 2px solid #e2e8f0; }
        .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; }
        .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); background: none; }
        .table thead { background: #f1f5f9; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="home.php">Back to Dashboard</a>
        <span class="navbar-text fw-bold text-dark">
            <?php echo htmlspecialchars($event['event_name']); ?> Analytics
        </span>
        <a href="export.php?evt=<?php echo urlencode($evtId); ?>" class="btn btn-success ms-3">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
        </div>
        </nav>


<div class="container pb-5">
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#analytics">Visual Analytics</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#responses">Raw Responses</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="analytics">
            <div class="row">
                <?php foreach ($analytics as $index => $stat): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card p-3">
                            <h6 class="fw-bold mb-3 small"><?php echo htmlspecialchars($stat['question']); ?></h6>
                            <div style="height: 150px;">
                                <canvas id="chart_<?php echo $index; ?>"></canvas>
                            </div>
                        </div>
                        <script>
                        (function() {
                            const ctx = document.getElementById('chart_<?php echo $index; ?>').getContext('2d');
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: <?php echo json_encode(array_keys($stat['stats'])); ?>,
                                    datasets: [{
                                        data: <?php echo json_encode(array_values($stat['stats'])); ?>,
                                        backgroundColor: ['#4a6fa5', '#6c5ce7', '#2d6a4f', '#ea580c', '#ec4899', '#0ea5e9']
                                    }]
                                },
                                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 10, fontSize: 10 } } } }
                            });
                        })();
                        </script>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="responses">
            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Date</th>
                                <?php 
                                foreach ($sections as $s): 
                                    foreach ($qsBySection[$s['id']] ?? [] as $q):
                                ?>
                                    <th style="min-width: 150px;"><?php echo htmlspecialchars($q['question_text']); ?></th>
                                <?php endforeach; endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($respondents as $r): ?>
                                <tr>
                                    <td class="ps-4 small text-muted"><?php echo date('d M Y H:i', strtotime($r['created_at'])); ?></td>
                                    <?php foreach ($sections as $s): 
                                        foreach ($qsBySection[$s['id']] ?? [] as $q): 
                                            $n = getQuestionName($q);
                                            if ($n === 'name') $ans = $r['full_name'];
                                            elseif ($n === 'email') $ans = $r['email_1'];
                                            elseif ($n === 'companyName') $ans = $r['company_name'];
                                            elseif ($n === 'jobTitle') $ans = $r['job_title'];
                                            elseif ($n === 'mobileNumber') $ans = $r['mobile_phone'];
                                            else $ans = $answersByRespondent[$r['id']][$n] ?? '-';
                                    ?>
                                        <td class="small"><?php echo htmlspecialchars($ans); ?></td>
                                    <?php endforeach; endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
