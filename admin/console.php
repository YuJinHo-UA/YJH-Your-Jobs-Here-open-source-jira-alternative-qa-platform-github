<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_role(['admin']);
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/logger.php';

$currentUser = current_user();
$actionResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)post_param('action');

    if ($action === 'clear_logs') {
        yjh_log_clear('app');
        yjh_log_clear('security');
        yjh_log_clear('ai');
        add_toast('Log files cleared', 'success');
        log_security_event('admin_console_clear_logs', [], (int)$currentUser['id']);
        redirect('/admin/console.php');
    }

    if ($action === 'check_db') {
        $integrity = fetch_one('PRAGMA integrity_check');
        $actionResult = 'DB integrity_check: ' . (string)array_values($integrity ?? ['unknown'])[0];
        log_security_event('admin_console_db_check', ['result' => $actionResult], (int)$currentUser['id']);
    }

    if ($action === 'backup_db') {
        $backupDir = __DIR__ . '/../backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $source = __DIR__ . '/../database.sqlite';
        $target = $backupDir . '/database_' . date('Ymd_His') . '.sqlite';
        if (@copy($source, $target)) {
            $actionResult = 'Backup created: ' . basename($target);
            add_toast('Backup created', 'success');
            log_security_event('admin_console_backup_created', ['file' => basename($target)], (int)$currentUser['id']);
        } else {
            $actionResult = 'Backup failed';
            add_toast('Backup failed', 'danger');
            log_security_event('admin_console_backup_failed', [], (int)$currentUser['id']);
        }
    }
}

$stats = [
    'bugs_total' => (int)(fetch_one('SELECT COUNT(*) as c FROM bugs')['c'] ?? 0),
    'bugs_open' => (int)(fetch_one("SELECT COUNT(*) as c FROM bugs WHERE status NOT IN ('closed','verified','fixed')")['c'] ?? 0),
    'test_cases' => (int)(fetch_one('SELECT COUNT(*) as c FROM test_cases')['c'] ?? 0),
    'test_runs' => (int)(fetch_one('SELECT COUNT(*) as c FROM test_runs')['c'] ?? 0),
    'wiki_pages' => (int)(fetch_one('SELECT COUNT(*) as c FROM wiki_pages')['c'] ?? 0),
    'users' => (int)(fetch_one('SELECT COUNT(*) as c FROM users')['c'] ?? 0),
];

$appLog = yjh_log_read_tail('app', 120);
$securityLog = yjh_log_read_tail('security', 120);
$aiLog = yjh_log_read_tail('ai', 120);
$phpErrorLogPath = ini_get('error_log');
$phpErrorLogLines = [];
if (is_string($phpErrorLogPath) && $phpErrorLogPath !== '' && is_file($phpErrorLogPath)) {
    $content = (string)@file_get_contents($phpErrorLogPath);
    if ($content !== '') {
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $phpErrorLogLines = array_slice($lines, -120);
    }
}
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Admin Console</h2>
        <div class="text-muted small">Operational control and logs</div>
    </div>

    <?php if ($actionResult): ?>
        <div class="alert alert-info"><?php echo h($actionResult); ?></div>
    <?php endif; ?>

    <div class="card p-3 mb-3">
        <div class="row g-2">
            <div class="col-md-2"><div class="border rounded p-2">Bugs: <strong><?php echo $stats['bugs_total']; ?></strong></div></div>
            <div class="col-md-2"><div class="border rounded p-2">Open bugs: <strong><?php echo $stats['bugs_open']; ?></strong></div></div>
            <div class="col-md-2"><div class="border rounded p-2">Test cases: <strong><?php echo $stats['test_cases']; ?></strong></div></div>
            <div class="col-md-2"><div class="border rounded p-2">Test runs: <strong><?php echo $stats['test_runs']; ?></strong></div></div>
            <div class="col-md-2"><div class="border rounded p-2">Wiki pages: <strong><?php echo $stats['wiki_pages']; ?></strong></div></div>
            <div class="col-md-2"><div class="border rounded p-2">Users: <strong><?php echo $stats['users']; ?></strong></div></div>
        </div>
        <form method="post" class="d-flex gap-2 mt-3">
            <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
            <button class="btn btn-outline-primary" name="action" value="check_db" type="submit">Check DB</button>
            <button class="btn btn-outline-success" name="action" value="backup_db" type="submit">Backup DB</button>
            <button class="btn btn-outline-danger" name="action" value="clear_logs" type="submit" onclick="return confirm('Clear app/security/ai logs?');">Clear Logs</button>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card p-3">
                <h6 class="mb-2">App Log</h6>
                <pre class="compare-pre p-2 rounded small" style="max-height:280px;overflow:auto;"><?php echo h(implode(PHP_EOL, $appLog)); ?></pre>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3">
                <h6 class="mb-2">Security Log</h6>
                <pre class="compare-pre p-2 rounded small" style="max-height:280px;overflow:auto;"><?php echo h(implode(PHP_EOL, $securityLog)); ?></pre>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3">
                <h6 class="mb-2">AI Log</h6>
                <pre class="compare-pre p-2 rounded small" style="max-height:280px;overflow:auto;"><?php echo h(implode(PHP_EOL, $aiLog)); ?></pre>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-3">
                <h6 class="mb-2">PHP Error Log<?php echo $phpErrorLogPath ? ' (' . h((string)$phpErrorLogPath) . ')' : ''; ?></h6>
                <pre class="compare-pre p-2 rounded small" style="max-height:280px;overflow:auto;"><?php echo h(implode(PHP_EOL, $phpErrorLogLines)); ?></pre>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

