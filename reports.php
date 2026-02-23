<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$leadTime = fetch_one("SELECT AVG(julianday(closed_at) - julianday(created_at)) as avg_days FROM bugs WHERE closed_at IS NOT NULL");
$reopenRate = fetch_one("SELECT (SUM(CASE WHEN status='reopened' THEN 1 ELSE 0 END) * 1.0) / (COUNT(*) + 1) as rate FROM bugs");
$velocity = fetch_one("SELECT COUNT(*) as total FROM bugs WHERE status='closed' AND date(closed_at) >= date('now','-7 day')");
$topModules = fetch_all("SELECT p.name, COUNT(*) as total FROM bugs b JOIN projects p ON p.id=b.project_id GROUP BY p.id ORDER BY total DESC LIMIT 5");
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Reports</h2>
        <button type="button" class="btn btn-outline-primary btn-sm" data-ai-action="generate_report">📈 Згенерувати AI-звіт</button>
    </div>
    <div class="alert alert-info d-none" data-ai-target="report_result"></div>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card p-3">
                <h6>Lead Time</h6>
                <div class="display-6 fw-bold"><?php echo number_format((float)($leadTime['avg_days'] ?? 0), 1); ?>d</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <h6>Reopen Rate</h6>
                <div class="display-6 fw-bold"><?php echo number_format((float)($reopenRate['rate'] ?? 0) * 100, 1); ?>%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <h6>Velocity (7d)</h6>
                <div class="display-6 fw-bold"><?php echo h((string)($velocity['total'] ?? 0)); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card p-3">
                <h6>Buggiest Modules</h6>
                <?php foreach ($topModules as $module): ?>
                    <div><?php echo h($module['name']); ?> <span class="text-muted">(<?php echo $module['total']; ?>)</span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
