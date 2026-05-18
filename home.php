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

$templates = $pdo->query("SELECT * FROM form_generator_template ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch events and count respondents for each
$events = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM respondent r WHERE r.id_event = c.event_id) as response_count 
    FROM form_generator_config c 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalResponses = $pdo->query("SELECT COUNT(*) FROM respondent")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Home | Feedback Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4a6fa5;
            --primary-dark: #3d5d8a;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-light); color: #1e293b; }
        .navbar { background: white; border-bottom: 1px solid var(--border); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
        .btn-primary { background: var(--primary); border: none; border-radius: 10px; }
        .btn-primary:hover { background: var(--primary-dark); }
        .stat-card { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; }
        .section-title { font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .badge-event { background: #e0e7ff; color: #4338ca; }
        .badge-template { background: #fef9c3; color: #854d0e; }
        .badge-responses { background: #dcfce7; color: #166534; font-weight: 600; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light py-3 mb-5">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="home.php">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Feedback Generator
        </a>
        <div class="d-flex gap-2">
            <a href="index.php?tab=templates" class="btn btn-outline-primary btn-sm rounded-pill">Manage Templates</a>
            <a href="index.php?tab=events" class="btn btn-primary btn-sm rounded-pill">Manage Events</a>
        </div>
    </div>
</nav>

<div class="container pb-5">
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <h6 class="text-white-50 text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Total Templates</h6>
                <h2 class="mb-0 fw-bold"><?php echo count($templates); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <h6 class="text-white-50 text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Total Events</h6>
                <h2 class="mb-0 fw-bold"><?php echo count($events); ?></h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-4">
                <h6 class="text-white-50 text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Total Responses</h6>
                <h2 class="mb-0 fw-bold"><?php echo $totalResponses; ?></h2>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-5">
            <h4 class="section-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Manage Events & Feedback
            </h4>
            <div class="row g-4">
                <?php if (empty($events)): ?>
                    <div class="col-12"><p class="text-muted">No events found. Start by creating an event.</p></div>
                <?php endif; ?>
                <?php foreach ($events as $e): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($e['event_name']); ?></h5>
                                        <?php if ($e['is_active']): ?>
                                            <span class="badge bg-success rounded-pill" style="font-size: 0.6rem;">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge badge-event rounded-pill small" style="font-size: 0.65rem;"><?php echo htmlspecialchars($e['event_id']); ?></span>
                                </div>
                                <span class="badge badge-responses rounded-pill px-3 py-2">
                                    <?php echo $e['response_count']; ?> Responses
                                </span>
                            </div>
                            <p class="text-muted small mb-4 flex-grow-1">
                                <?php echo htmlspecialchars($e['description'] ?? 'No description'); ?>
                            </p>
                            <div class="d-grid gap-2">
                                <div class="d-flex gap-2">
                                    <a href="index.php?tab=events&evt=<?php echo urlencode($e['event_id']); ?>" class="btn btn-outline-secondary btn-sm rounded-pill flex-fill">Edit Form</a>
                                    <?php 
                                    $formPath = "generated_forms/" . $e['event_id'] . "/index.php";
                                    $fullUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/generated_forms/" . $e['event_id'] . "/";
                                    if (file_exists($formPath)): 
                                    ?>
                                        <a href="<?php echo $formPath; ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill flex-fill">View Live</a>
                                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill" onclick="copyToClipboard('<?php echo $fullUrl; ?>')" title="Copy Share Link">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" action="index.php" class="flex-fill">
                                            <input type="hidden" name="action" value="generate">
                                            <input type="hidden" name="eid" value="<?php echo htmlspecialchars($e['event_id']); ?>">
                                            <button type="submit" class="btn btn-warning btn-sm rounded-pill w-100">Generate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <a href="feedback.php?evt=<?php echo urlencode($e['event_id']); ?>" class="btn btn-primary rounded-pill">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    View Feedback & Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-lg-12">
            <h4 class="section-title">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                Template Library
            </h4>
            <div class="row g-3">
                <?php if (empty($templates)): ?>
                    <div class="col-12"><p class="text-muted">No templates found.</p></div>
                <?php endif; ?>
                <?php foreach ($templates as $t): ?>
                    <div class="col-md-4 col-xl-3">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($t['template_name']); ?></h6>
                                    <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($t['created_at'])); ?></small>
                                </div>
                                <a href="index.php?tab=templates&tpl=<?php echo $t['id']; ?>" class="btn btn-sm btn-light rounded-pill px-3">Edit</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-4 col-xl-3">
                    <a href="index.php?tab=templates" class="card p-3 bg-light border-dashed text-center text-decoration-none h-100 d-flex align-items-center justify-content-center">
                        <span class="text-primary fw-bold">+ Create New Template</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-4 text-muted border-top mt-5" style="font-size: 0.8rem;">
    &copy; <?php echo date('Y'); ?> Feedback Form Generator. All rights reserved.
</footer>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Share link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}
</script>
</body>
</html>