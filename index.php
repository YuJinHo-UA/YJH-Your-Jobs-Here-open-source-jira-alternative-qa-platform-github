<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$projects = fetch_all('SELECT id, name FROM projects ORDER BY name');
$selectedProjectId = (int)get_param('project_id', 0);
$validProjectIds = array_map(static fn(array $project): int => (int)$project['id'], $projects);
if ($selectedProjectId > 0 && !in_array($selectedProjectId, $validProjectIds, true)) {
    $selectedProjectId = 0;
}
$selectedProject = null;
if ($selectedProjectId > 0) {
    $selectedProject = fetch_one('SELECT id, name FROM projects WHERE id = :id', [':id' => $selectedProjectId]);
    if (!$selectedProject) {
        $selectedProjectId = 0;
    }
}

$trendDays = (int)get_param('trend_days', 7);
if (!in_array($trendDays, [7, 14, 30], true)) {
    $trendDays = 7;
}

$dashboardQueryParams = [];
$projectBugClause = '';
$projectRunClause = '';
$projectExecutionClause = '';
if ($selectedProjectId > 0) {
    $dashboardQueryParams[':project_id'] = $selectedProjectId;
    $projectBugClause = ' AND b.project_id = :project_id';
    $projectRunClause = ' AND tp.project_id = :project_id';
    $projectExecutionClause = ' AND tp.project_id = :project_id';
}

$passFail = fetch_one(
    "SELECT
        SUM(CASE WHEN te.status='pass' THEN 1 ELSE 0 END) AS pass,
        SUM(CASE WHEN te.status='fail' THEN 1 ELSE 0 END) AS fail,
        SUM(CASE WHEN te.status IN ('blocked','not_tested','skipped') THEN 1 ELSE 0 END) AS in_progress
     FROM test_executions te
     JOIN test_runs tr ON tr.id = te.test_run_id
     JOIN test_plans tp ON tp.id = tr.plan_id
     WHERE 1=1 $projectExecutionClause",
    $dashboardQueryParams
);

$priorityStats = fetch_all(
    "SELECT b.status, COUNT(*) as total
     FROM bugs b
     WHERE b.priority IN ('highest','high') $projectBugClause
     GROUP BY b.status",
    $dashboardQueryParams
);
$priorityLabels = [];
$priorityValues = [];
foreach ($priorityStats as $row) {
    $priorityLabels[] = $row['status'];
    $priorityValues[] = (int)$row['total'];
}

$myBugTaskParams = [':id' => $user['id']];
if ($selectedProjectId > 0) {
    $myBugTaskParams[':project_id'] = $selectedProjectId;
}
$myBugTasks = fetch_all(
    "SELECT b.id, b.title, b.due_date, b.status
     FROM bugs b
     WHERE b.assignee_id = :id
       AND b.status NOT IN ('closed', 'verified')
       " . ($selectedProjectId > 0 ? 'AND b.project_id = :project_id' : '') . "
     ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END, due_date ASC, created_at DESC
     LIMIT 5",
    $myBugTaskParams
);

$myRunTaskParams = [':id' => $user['id']];
if ($selectedProjectId > 0) {
    $myRunTaskParams[':project_id'] = $selectedProjectId;
}
$myRunTasks = fetch_all(
    "SELECT tr.id, tr.name, tr.status
     FROM test_runs tr
     JOIN test_plans tp ON tp.id = tr.plan_id
     WHERE tr.assigned_to = :id
       AND tr.status IN ('in_progress', 'new')
       " . ($selectedProjectId > 0 ? 'AND tp.project_id = :project_id' : '') . "
     ORDER BY tr.created_at DESC
     LIMIT 5",
    $myRunTaskParams
);

$runsTodayTotal = (int)fetch_one(
    "SELECT COUNT(*) as total
     FROM test_runs tr
     JOIN test_plans tp ON tp.id = tr.plan_id
     WHERE date(tr.created_at) = date('now') $projectRunClause",
    $dashboardQueryParams
)['total'];
$runsTodayCompleted = (int)fetch_one(
    "SELECT COUNT(*) as total
     FROM test_runs tr
     JOIN test_plans tp ON tp.id = tr.plan_id
     WHERE date(tr.created_at) = date('now')
       $projectRunClause
       AND NOT EXISTS (
           SELECT 1 FROM test_executions te
           WHERE te.test_run_id = tr.id AND te.status = 'not_tested'
       )",
    $dashboardQueryParams
)['total'];

$todayExecution = fetch_one(
    "SELECT
        SUM(CASE WHEN date(te.executed_at) = date('now') AND te.status = 'pass' THEN 1 ELSE 0 END) AS pass,
        SUM(CASE WHEN date(te.executed_at) = date('now') AND te.status = 'fail' THEN 1 ELSE 0 END) AS fail
     FROM test_executions te
     JOIN test_runs tr ON tr.id = te.test_run_id
     JOIN test_plans tp ON tp.id = tr.plan_id
     WHERE 1=1 $projectExecutionClause",
    $dashboardQueryParams
);

$runsTodayProgress = $runsTodayTotal > 0 ? ($runsTodayCompleted / $runsTodayTotal) * 100 : 0;

$trendLabels = [];
$trendOpened = [];
$trendClosed = [];
$trendBugProjectClause = $selectedProjectId > 0 ? 'AND project_id = :project_id' : '';
for ($i = $trendDays - 1; $i >= 0; $i--) {
    $date = (new DateTime())->modify("-{$i} days")->format('Y-m-d');
    $trendLabels[] = $date;
    $trendParams = [':d' => $date];
    if ($selectedProjectId > 0) {
        $trendParams[':project_id'] = $selectedProjectId;
    }
    $opened = fetch_one("SELECT COUNT(*) as total FROM bugs WHERE date(created_at)=:d $trendBugProjectClause", $trendParams);
    $closed = fetch_one("SELECT COUNT(*) as total FROM bugs WHERE date(closed_at)=:d $trendBugProjectClause", $trendParams);
    $trendOpened[] = (int)$opened['total'];
    $trendClosed[] = (int)$closed['total'];
}

$totalBugs = (int)fetch_one("SELECT COUNT(*) as total FROM bugs b WHERE 1=1 $projectBugClause", $dashboardQueryParams)['total'];
$blockers = (int)fetch_one("SELECT COUNT(*) as total FROM bugs b WHERE b.severity='blocker' $projectBugClause", $dashboardQueryParams)['total'];
$critical = (int)fetch_one("SELECT COUNT(*) as total FROM bugs b WHERE b.severity='critical' $projectBugClause", $dashboardQueryParams)['total'];
$high = (int)fetch_one("SELECT COUNT(*) as total FROM bugs b WHERE b.priority IN ('highest','high') $projectBugClause", $dashboardQueryParams)['total'];

$projectTestPlans = (int)fetch_one(
    "SELECT COUNT(*) as total FROM test_plans tp WHERE 1=1 " . ($selectedProjectId > 0 ? 'AND tp.project_id = :project_id' : ''),
    $dashboardQueryParams
)['total'];
$projectTestCases = (int)fetch_one(
    "SELECT COUNT(*) as total
     FROM test_cases tc
     JOIN test_suites ts ON ts.id = tc.suite_id
     JOIN test_plans tp ON tp.id = ts.plan_id
     WHERE 1=1 " . ($selectedProjectId > 0 ? 'AND tp.project_id = :project_id' : ''),
    $dashboardQueryParams
)['total'];
$projectWikiPages = (int)fetch_one(
    "SELECT COUNT(*) as total FROM wiki_pages wp WHERE 1=1 " . ($selectedProjectId > 0 ? 'AND wp.project_id = :project_id' : ''),
    $dashboardQueryParams
)['total'];

$passCount = (int)($passFail['pass'] ?? 0);
$failCount = (int)($passFail['fail'] ?? 0);
$inProgressCount = (int)($passFail['in_progress'] ?? 0);
$passRate = 0.0;
if (($passCount + $failCount) > 0) {
    $passRate = $passCount / ($passCount + $failCount);
}
$risk = (($blockers * 5) + ($critical * 3) + ($high * 2)) / ($totalBugs + 1) * (1 - $passRate);
$riskLabel = $risk < 1.0 ? 'Low' : ($risk <= 2.5 ? 'Medium' : 'High');
$riskColor = $risk < 1.0 ? 'success' : ($risk <= 2.5 ? 'warning' : 'danger');
$riskPercent = max(0, min(100, (int)round(($risk / 3.0) * 100)));
$projectAwareQuery = $selectedProjectId > 0 ? ('?project_id=' . $selectedProjectId) : '';
$buildTrendLink = static function (int $days, int $projectId): string {
    $params = ['trend_days' => $days];
    if ($projectId > 0) {
        $params['project_id'] = $projectId;
    }
    return '/index.php?' . http_build_query($params);
};
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Dashboard</h2>
            <div class="text-muted">One platform. One team. One source of truth.</div>
        </div>
        <form method="get" class="d-flex align-items-center gap-2">
            <label for="projectFilter" class="small text-muted mb-0">Project</label>
            <select id="projectFilter" name="project_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="0">All projects</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?php echo (int)$project['id']; ?>" <?php echo $selectedProjectId === (int)$project['id'] ? 'selected' : ''; ?>>
                        <?php echo h($project['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="trend_days" value="<?php echo (int)$trendDays; ?>">
        </form>
    </div>

    <div class="row g-3 dashboard-grid-compact">
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
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/testplans.php<?php echo h($projectAwareQuery); ?>">Create Test Plan</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/testruns.php<?php echo h($projectAwareQuery); ?>">Create Test Run</a>
                    <a class="btn btn-outline-primary btn-sm quick-action-btn" href="/wiki.php<?php echo h($projectAwareQuery); ?>">Create Wiki Page</a>
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
                <h6>Execution Overview</h6>
                <canvas id="chartPassFail" class="chart-clickable" data-chart='<?php echo json_encode([
                    'pass' => $passCount,
                    'fail' => $failCount,
                    'in_progress' => $inProgressCount,
                    'project_id' => $selectedProjectId,
                ]); ?>'></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>High Priority Bugs</h6>
                <canvas id="chartPriority" class="chart-clickable" data-chart='<?php echo json_encode(['labels' => $priorityLabels, 'values' => $priorityValues, 'project_id' => $selectedProjectId]); ?>'></canvas>
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
                <div class="progress mt-2" style="height: 8px;">
                    <div class="progress-bar bg-<?php echo h($riskColor); ?>" role="progressbar" style="width: <?php echo $riskPercent; ?>%;"></div>
                </div>
                <div class="small mt-2 d-flex justify-content-between">
                    <span><?php echo h('Blocker bugs:'); ?> <strong><?php echo (int)$blockers; ?></strong></span>
                    <span><?php echo h('Critical bugs:'); ?> <strong><?php echo (int)$critical; ?></strong></span>
                    <span><?php echo h('High priority bugs:'); ?> <strong><?php echo (int)$high; ?></strong></span>
                </div>
                <details class="risk-details mt-1">
                    <summary><?php echo h('Show risk drivers'); ?></summary>
                    <div class="small mt-2">
                        <div><?php echo h('Pass rate:'); ?> <strong><?php echo (int)round($passRate * 100); ?>%</strong></div>
                        <a class="d-inline-block mt-1" href="/bugs.php?<?php echo h(http_build_query(array_filter(['priority_group' => 'high', 'project_id' => $selectedProjectId ?: null]))); ?>"><?php echo h('Open risky bugs'); ?></a>
                    </div>
                </details>
            </div>
        </div>
        <div class="col-12">
            <div class="card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Bug Trends (<?php echo $trendDays; ?> days)</h6>
                    <div class="btn-group btn-group-sm">
                        <a class="btn <?php echo $trendDays === 7 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($buildTrendLink(7, $selectedProjectId)); ?>">7</a>
                        <a class="btn <?php echo $trendDays === 14 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($buildTrendLink(14, $selectedProjectId)); ?>">14</a>
                        <a class="btn <?php echo $trendDays === 30 ? 'btn-primary' : 'btn-outline-primary'; ?>" href="<?php echo h($buildTrendLink(30, $selectedProjectId)); ?>">30</a>
                    </div>
                </div>
                <canvas id="chartTrends" class="chart-clickable" data-chart='<?php echo json_encode(['labels' => $trendLabels, 'opened' => $trendOpened, 'closed' => $trendClosed, 'project_id' => $selectedProjectId]); ?>'></canvas>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
