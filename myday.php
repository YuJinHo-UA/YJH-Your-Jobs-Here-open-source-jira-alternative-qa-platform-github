<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();
$unavailable = get_user_unavailability((int)$user['id']);
$bugs = fetch_all('SELECT * FROM bugs WHERE assignee_id = :id ORDER BY due_date LIMIT 5', [':id' => $user['id']]);
$testRuns = fetch_all('SELECT * FROM test_runs WHERE assigned_to = :id ORDER BY created_at DESC LIMIT 5', [':id' => $user['id']]);
$mentions = fetch_all('SELECT m.*, b.title FROM bug_mentions m JOIN bugs b ON b.id = m.bug_id WHERE m.user_id = :id ORDER BY m.created_at DESC LIMIT 5', [':id' => $user['id']]);
?>
<div class="app-content">
    <h2 class="mb-3">My Day</h2>
    <?php if ($unavailable): ?>
        <div class="alert alert-warning mb-3">
            ⚠️ You are marked as <strong><?php echo h((string)$unavailable['type']); ?></strong>
            until <strong><?php echo h((string)$unavailable['end_date']); ?></strong>.
            Tasks are temporarily not assigned to you.
        </div>
    <?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>My Bugs</h6>
                <?php if (!$bugs): ?>
                    <div class="text-muted">No assigned bugs.</div>
                <?php else: ?>
                    <?php foreach ($bugs as $bug): ?>
                        <div class="mb-2"><a href="/bug.php?id=<?php echo $bug['id']; ?>" data-preview-type="bug" data-preview-id="<?php echo $bug['id']; ?>">#<?php echo $bug['id']; ?> <?php echo h($bug['title']); ?></a></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>My Test Runs</h6>
                <?php if (!$testRuns): ?>
                    <div class="text-muted">No test runs.</div>
                <?php else: ?>
                    <?php foreach ($testRuns as $run): ?>
                        <div class="mb-2"><a href="/testrun.php?id=<?php echo $run['id']; ?>"><?php echo h($run['name']); ?></a></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Mentions</h6>
                <?php if (!$mentions): ?>
                    <div class="text-muted">No mentions.</div>
                <?php else: ?>
                    <?php foreach ($mentions as $mention): ?>
                        <div class="mb-2">@<?php echo h($user['username']); ?> in <a href="/bug.php?id=<?php echo $mention['bug_id']; ?>">#<?php echo $mention['bug_id']; ?> <?php echo h($mention['title']); ?></a></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
