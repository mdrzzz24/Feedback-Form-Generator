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

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM form_generator_config WHERE event_id = ?");
$stmt->execute([$evtId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}

// Fetch respondents
$stmt = $pdo->prepare("SELECT * FROM respondent WHERE id_event = ? ORDER BY created_at DESC");
$stmt->execute([$evtId]);
$respondents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all questions for this event (including template questions)
$stmt = $pdo->prepare("SELECT * FROM form_generator_questions WHERE id_event = ? ORDER BY sort_order ASC");
$stmt->execute([$evtId]);
$eventQs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Analytics Logic
$analytics = [];
foreach ($eventQs as $q) {
    if (in_array($q['question_type'], ['radio', 'checkbox'])) {
        $qId = 'q_' . $q['id'];
        // Also check for mapped names if needed, but the answer table usually uses the generated ID
        // Let's check how buildSubmit saves it.
        // buildSubmit uses 'name', 'email' etc for template Qs and 'q_ID' for event Qs.
        // Wait, I updated buildSubmit to use intelligent mapping for ALL questions now.
        // So I need to use the same mapping logic here to find answers.

        $n = 'q_' . $q['id'];
        if (stripos($q['question_text'], 'nama') !== false || stripos($q['question_text'], 'name') !== false) $n = 'name';
        elseif (stripos($q['question_text'], 'email') !== false) $n = 'email';
        elseif (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';

        $stmt = $pdo->prepare("SELECT answer_text FROM answer WHERE id_feedback = ? AND id_question = ?");
        $stmt->execute([$evtId, $n]);
        $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stats = [];
        $options = json_decode($q['options'] ?? '[]', true) ?: [];
        foreach ($options as $opt) $stats[$opt] = 0;

        foreach ($answers as $ans) {
            // Checkbox answers might be separated by '; '
            $parts = explode('; ', $ans);
            foreach ($parts as $p) {
                if (isset($stats[$p])) $stats[$p]++;
                else $stats[$p] = 1; // Catch custom values
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback Analytics | <?php echo htmlspecialchars($event['event_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary: #4a6fa5; --bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1e293b; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; }
        .nav-tabs { border-bottom: 2px solid #e2e8f0; }
        .nav-link { border: none; color: #64748b; font-weight: 600; padding: 12px 24px; }
        .nav-link.active { color: var(--primary); border-bottom: 2px solid var(--primary); background: none; }
        .table thead { background: #f1f5f9; }
        .progress { height: 10px; border-radius: 5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="home.php">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-2"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
        <span class="navbar-text fw-bold text-dark">
            <?php echo htmlspecialchars($event['event_name']); ?> Analytics
        </span>
    </div>
</nav>

<div class="container pb-5">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card p-4 text-center">
                <h6 class="text-muted text-uppercase small fw-bold">Total Respondents</h6>
                <h2 class="mb-0 fw-bold"><?php echo count($respondents); ?></h2>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4" id="feedbackTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button">Visual Analytics</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" id="responses-tab" data-bs-toggle="tab" data-bs-target="#responses" type="button">Raw Responses</button>
        </li>
    </ul>

    <div class="tab-content" id="feedbackTabsContent">
        <!-- Analytics Tab -->
        <div class="tab-pane fade show active" id="analytics" role="tabpanel">
            <div class="row">
                <?php if (empty($analytics)): ?>
                    <div class="col-12">
                        <div class="card p-5 text-center">
                            <p class="text-muted mb-0">No choice-based questions found for analytics.</p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($analytics as $index => $stat): ?>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-4"><?php echo htmlspecialchars($stat['question']); ?></h6>
                                <?php 
                                $chartId = "chart_" . $index;
                                $labels = array_keys($stat['stats']);
                                $data = array_values($stat['stats']);
                                ?>
                                <canvas id="<?php echo $chartId; ?>" class="mb-4" style="max-height: 250px;"></canvas>
                                
                                <div class="mt-3">
                                    <?php foreach ($stat['stats'] as $label => $count): 
                                        $percent = $stat['total'] > 0 ? round(($count / $stat['total']) * 100) : 0;
                                    ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="small fw-medium"><?php echo htmlspecialchars($label); ?></span>
                                                <span class="small text-muted"><?php echo $count; ?> (<?php echo $percent; ?>%)</span>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: <?php echo $percent; ?>%; background-color: var(--primary)"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <script>
                        new Chart(document.getElementById('<?php echo $chartId; ?>'), {
                            type: 'doughnut',
                            data: {
                                labels: <?php echo json_encode($labels); ?>,
                                datasets: [{
                                    data: <?php echo json_encode($data); ?>,
                                    backgroundColor: ['#4a6fa5', '#6c5ce7', '#2d6a4f', '#ea580c', '#ec4899', '#0ea5e9']
                                }]
                            },
                            options: { plugins: { legend: { display: false } } }
                        });
                        </script>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Responses Tab -->
        <div class="tab-pane fade" id="responses" role="tabpanel">
            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Organization</th>
                                <?php foreach ($eventQs as $q): 
                                    // Skip generic personal info already in columns
                                    if (stripos($q['question_text'], 'name') !== false || stripos($q['question_text'], 'email') !== false) continue;
                                ?>
                                    <th style="min-width: 200px;"><?php echo htmlspecialchars($q['question_text']); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($respondents)): ?>
                                <tr><td colspan="10" class="text-center py-5 text-muted">No responses yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($respondents as $r): ?>
                                <tr>
                                    <td class="ps-4 small text-muted"><?php echo date('d M Y H:i', strtotime($r['created_at'])); ?></td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($r['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['email_1']); ?></td>
                                    <td><?php echo htmlspecialchars($r['company_name']); ?></td>
                                    <?php foreach ($eventQs as $q): 
                                        if (stripos($q['question_text'], 'name') !== false || stripos($q['question_text'], 'email') !== false) continue;
                                        
                                        $n = 'q_' . $q['id'];
                                        if (stripos($q['question_text'], 'perusahaan') !== false || stripos($q['question_text'], 'company') !== false) $n = 'companyName';
                                        elseif (stripos($q['question_text'], 'jabatan') !== false || stripos($q['question_text'], 'title') !== false) $n = 'jobTitle';
                                        elseif (stripos($q['question_text'], 'telepon') !== false || stripos($q['question_text'], 'phone') !== false || stripos($q['question_text'], 'hp') !== false) $n = 'mobileNumber';
                                        
                                        $ans = $answersByRespondent[$r['id']][$n] ?? '-';
                                    ?>
                                        <td class="small"><?php echo htmlspecialchars($ans); ?></td>
                                    <?php endforeach; ?>
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