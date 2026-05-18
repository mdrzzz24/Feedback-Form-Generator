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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $eid = $_POST['eid'];
    if ($_POST['action'] === 'restore_event') {
        $stmt = $pdo->prepare("UPDATE form_generator_config SET deleted_at = NULL WHERE event_id = ?");
        $stmt->execute([$eid]);
        header("Location: trash.php?msg=Event restored successfully&type=success"); exit;
    }
    if ($_POST['action'] === 'perm_del_event') {
        // Delete all related data
        $pdo->prepare("DELETE FROM answer WHERE id_feedback = ?")->execute([$eid]);
        $pdo->prepare("DELETE FROM respondent WHERE id_event = ?")->execute([$eid]);
        $pdo->prepare("DELETE FROM form_generator_questions WHERE id_event = ?")->execute([$eid]);
        $pdo->prepare("DELETE FROM form_generator_event_sections WHERE event_id = ?")->execute([$eid]);
        $pdo->prepare("DELETE FROM form_generator_config WHERE event_id = ?")->execute([$eid]);
        
        // Try to delete generated folder
        $dir = __DIR__ . "/generated_forms/$eid";
        if (is_dir($dir)) {
            array_map('unlink', glob("$dir/*.*"));
            rmdir($dir);
        }
        
        header("Location: trash.php?msg=Event permanently deleted&type=danger"); exit;
    }
}

// Fetch trashed events
$trashedEvents = $pdo->query("
    SELECT *, (SELECT COUNT(*) FROM respondent WHERE id_event = event_id) as response_count
    FROM form_generator_config 
    WHERE deleted_at IS NOT NULL
    ORDER BY deleted_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trash Bin | Feedback Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4a6fa5;
            --bg-light: #f8fafc;
            --border: #e2e8f0;
        }
        body { font-family: 'Inter', sans-serif; background: var(--bg-light); color: #1e293b; }
        .navbar { background: white; border-bottom: 1px solid var(--border); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .section-title { font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .btn-restore { background: #dcfce7; color: #166534; border: none; border-radius: 10px; font-weight: 600; }
        .btn-restore:hover { background: #bbf7d0; color: #14532d; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light py-3 mb-5">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="home.php">
            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-2"><path d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/></svg>
            Back to Home
        </a>
        <span class="navbar-text fw-bold text-danger">TRASH BIN</span>
    </div>
</nav>

<div class="container pb-5">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-<?php echo $_GET['type'] ?? 'info'; ?> alert-dismissible fade show mb-4 rounded-4 shadow-sm border-0 position-relative overflow-hidden" role="alert" id="autoAlert">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <?php echo htmlspecialchars($_GET['msg']); ?>
                </div>
                <span class="badge bg-white bg-opacity-25 rounded-pill ms-3 small" id="alertTimer">5s</span>
            </div>
            <div class="position-absolute bottom-0 start-0 bg-white bg-opacity-25" id="alertProgress" style="height: 4px; width: 100%; transition: width 5s linear;"></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const alert = document.getElementById('autoAlert');
                const timer = document.getElementById('alertTimer');
                const progress = document.getElementById('alertProgress');
                if (alert) {
                    let timeLeft = 5;
                    setTimeout(() => progress.style.width = '0%', 10);
                    const interval = setInterval(() => {
                        timeLeft--;
                        if (timer) timer.textContent = timeLeft + 's';
                        if (timeLeft <= 0) {
                            clearInterval(interval);
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        }
                    }, 1000);
                }
            });
        </script>
    <?php endif; ?>

    <h4 class="section-title text-danger mb-4">
        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        Trashed Events
    </h4>

    <?php if (empty($trashedEvents)): ?>
        <div class="card p-5 text-center">
            <div class="mb-3 text-muted">
                <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h5>Trash Bin is Empty</h5>
            <p class="text-muted">Events you delete will appear here for 30 days before being permanently removed.</p>
            <a href="home.php" class="btn btn-primary rounded-pill px-4 mt-3">Go to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($trashedEvents as $e): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 p-4 border-start border-danger border-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($e['event_name']); ?></h5>
                                <code class="small text-muted"><?php echo htmlspecialchars($e['event_id']); ?></code>
                            </div>
                            <span class="badge bg-light text-danger rounded-pill px-3 py-2">
                                <?php echo $e['response_count']; ?> Responses
                            </span>
                        </div>
                        
                        <p class="text-muted small mb-4 flex-grow-1">
                            Deleted on: <?php echo date('M d, Y H:i', strtotime($e['deleted_at'])); ?>
                        </p>

                        <div class="d-grid gap-2">
                            <div class="d-flex gap-2">
                                <a href="preview.php?evt=<?php echo urlencode($e['event_id']); ?>&show_trashed=1" target="_blank" class="btn btn-outline-secondary btn-sm rounded-pill flex-fill">Preview Form</a>
                                <a href="feedback.php?evt=<?php echo urlencode($e['event_id']); ?>&show_trashed=1" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill flex-fill">Analytics</a>
                            </div>
                            <div class="d-flex gap-2">
                                <form method="POST" class="flex-fill">
                                    <input type="hidden" name="action" value="restore_event">
                                    <input type="hidden" name="eid" value="<?php echo htmlspecialchars($e['event_id']); ?>">
                                    <button type="submit" class="btn btn-restore btn-sm w-100 py-2">Restore Event</button>
                                </form>
                                <form method="POST" class="flex-fill" onsubmit="return confirm('PERMANENTLY delete this event? This cannot be undone.');">
                                    <input type="hidden" name="action" value="perm_del_event">
                                    <input type="hidden" name="eid" value="<?php echo htmlspecialchars($e['event_id']); ?>">
                                    <button type="submit" class="btn btn-danger btn-sm w-100 py-2 rounded-pill">Delete Forever</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>