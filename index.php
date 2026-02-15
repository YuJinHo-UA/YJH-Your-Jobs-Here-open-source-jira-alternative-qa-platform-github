<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$trendDays = (int)get_param('trend_days', 7);
if (!in_array($trendDays, [7, 14, 30], true)) {
    $trendDays = 7;
}

$passFail = fetch_one("SELECT
    SUM(CASE WHEN status='pass' THEN 1 ELSE 0 END) AS pass,
    SUM(CASE WHEN status='fail' THEN 1 ELSE 0 END) AS fail
    FROM test_executions");

$priorityStats = fetch_all("SELECT status, COUNT(*) as total FROM bugs WHERE priority IN ('highest','high') GROUP BY status");
$priorityLabels = [];
$priorityValues = [];
foreach ($priorityStats as $row) {
    $priorityLabels[] = $row['status'];
    $priorityValues[] = (int)$row['total'];
}

$myBugTasks = fetch_all(
    "SELECT id, title, due_date, status FROM bugs
     WHERE assignee_id = :id AND status NOT IN ('closed', 'verified')
     ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, created_at DESC
     LIMIT 5",
    [':id' => $user['id']]
);

$myRunTasks = fetch_all(
    "SELECT id, name, status FROM test_runs
     WHERE assigned_to = :id AND status IN ('in_progress', 'new')
     ORDER BY created_at DESC
     LIMIT 5",
    [':id' => $user['id']]
);

$runsTodayTotal = (int)fetch_one("SELECT COUNT(*) as total FROM test_runs WHERE date(created_at) = date('now')")['total'];
$runsTodayCompleted = (int)fetch_one(
    "SELECT COUNT(*) as total
     FROM test_runs tr
     WHERE date(tr.created_at) = date('now')
       AND NOT EXISTS (
           SELECT 1 FROM test_executions te
           WHERE te.test_run_id = tr.id AND te.status = 'not_tested'
       )"
)['total'];

$todayExecution = fetch_one(
    "SELECT
        SUM(CASE WHEN date(executed_at) = date('now') AND status = 'pass' THEN 1 ELSE 0 END) AS pass,
        SUM(CASE WHEN date(executed_at) = date('now') AND status = 'fail' THEN 1 ELSE 0 END) AS fail
     FROM test_executions"
);

$runsTodayProgress = $runsTodayTotal > 0 ? ($runsTodayCompleted / $runsTodayTotal) * 100 : 0;

$trendLabels = [];
$trendOpened = [];
$trendClosed = [];
for ($i = $trendDays - 1; $i >= 0; $i--) {
    $date = (new DateTime())->modify("-{$i} days")->format('Y-m-d');
    $trendLabels[] = $date;
    $opened = fetch_one("SELECT COUNT(*) as total FROM bugs WHERE date(created_at)=:d", [':d' => $date]);
    $closed = fetch_one("SELECT COUNT(*) as total FROM bugs WHERE date(closed_at)=:d", [':d' => $date]);
    $trendOpened[] = (int)$opened['total'];
    $trendClosed[] = (int)$closed['total'];
}

$totalBugs = (int)fetch_one('SELECT COUNT(*) as total FROM bugs')['total'];
$blockers = (int)fetch_one("SELECT COUNT(*) as total FROM bugs WHERE severity='blocker'")['total'];
$critical = (int)fetch_one("SELECT COUNT(*) as total FROM bugs WHERE severity='critical'")['total'];
$high = (int)fetch_one("SELECT COUNT(*) as total FROM bugs WHERE priority IN ('highest','high')")['total'];
$passRate = 0.0;
if (($passFail['pass'] + $passFail['fail']) > 0) {
    $passRate = $passFail['pass'] / ($passFail['pass'] + $passFail['fail']);
}
$risk = (($blockers * 5) + ($critical * 3) + ($high * 2)) / ($totalBugs + 1) * (1 - $passRate);
$riskLabel = $risk < 1.0 ? 'Low' : ($risk <= 2.5 ? 'Medium' : 'High');
$riskColor = $risk < 1.0 ? 'success' : ($risk <= 2.5 ? 'warning' : 'danger');
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Dashboard</h2>
            <div class="text-muted">One platform. One team. One source of truth.</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>My Tasks</h6>
                <?php if (!$myBugTasks && !$myRunTasks): ?>
                    <div class="text-muted">No open tasks.</div>
                <?php else: ?>
                    <?php foreach ($myBugTasks as $task): ?>
                        <div class="mb-2">
                            <a href="/bug.php?id=<?php echo (int)$task['id']; ?>">#<?php echo (int)$task['id']; ?> <?php echo h($task['title']); ?></a>
                        </div>
                    <?php endforeach; ?>
                    <?php foreach ($myRunTasks as $run): ?>
                        <div class="mb-2">
                            <a href="/testrun.php?id=<?php echo (int)$run['id']; ?>"><?php echo h($run['name']); ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Quick Actions</h6>
                <div class="quick-actions">
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/bug.php">Create Bug</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/testcase.php">Create Test Case</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/testplans.php">Create Test Plan</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/testruns.php">Create Test Run</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/wiki.php">Create Wiki Page</a>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Test Runs Today</h6>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Runs completed</span>
                    <strong><?php echo $runsTodayCompleted; ?>/<?php echo $runsTodayTotal; ?></strong>
                </div>
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo number_format($runsTodayProgress, 0); ?>%;"></div>
                </div>
                <div class="d-flex justify-content-between mt-3 small">
                    <span>Pass today: <?php echo (int)($todayExecution['pass'] ?? 0); ?></span>
                    <span>Fail today: <?php echo (int)($todayExecution['fail'] ?? 0); ?></span>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Pass / Fail Rate</h6>
                <canvas id="chartPassFail" class="chart-clickable" data-chart='<?php echo json_encode($passFail); ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>High Priority Bugs</h6>
                <canvas id="chartPriority" class="chart-clickable" data-chart='<?php echo json_encode(['labels' => $priorityLabels, 'values' => $priorityValues]); ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6 class="d-flex align-items-center gap-2">
                    Risk Engine
                    <span
                        class="text-muted"
                        title="Risk = ((blocker*5 + critical*3 + highPriority*2) / (totalBugs + 1)) * (1 - passRate)"
                    >
                        <i class="fa-solid fa-circle-info"></i>
                    </span>
                </h6>
                <div class="display-6 fw-bold text-<?php echo h($riskColor); ?>"><?php echo h($riskLabel); ?></div>
                <div class="text-muted">Score: <?php echo number_format($risk, 2); ?></div>
            </div>
        </div>
        <div class="col-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Bug Trends (<?php echo $trendDays; ?> days)</h6>
                    <div class="btn-group btn-group-sm">
                        <a class="btn <?php echo $trendDays === 7 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/index.php?trend_days=7">7</a>
                        <a class="btn <?php echo $trendDays === 14 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/index.php?trend_days=14">14</a>
                        <a class="btn <?php echo $trendDays === 30 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="/index.php?trend_days=30">30</a>
                    </div>
                </div>
                <canvas id="chartTrends" class="chart-clickable" data-chart='<?php echo json_encode(['labels' => $trendLabels, 'opened' => $trendOpened, 'closed' => $trendClosed]); ?>'></canvas>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
