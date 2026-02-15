<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = db()->prepare('INSERT INTO user_availability (user_id, type, start_date, end_date, reason) VALUES (:user_id, :type, :start_date, :end_date, :reason)');
    $stmt->execute([
        ':user_id' => $user['id'],
        ':type' => post_param('type'),
        ':start_date' => post_param('start_date'),
        ':end_date' => post_param('end_date'),
        ':reason' => post_param('reason'),
    ]);
    add_toast('Availability added', 'success');
    redirect('/calendar.php');
}

$availability = fetch_all('SELECT a.*, u.username FROM user_availability a JOIN users u ON u.id=a.user_id ORDER BY start_date DESC');
?>
<div class="app-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Team Calendar</h2>
    </div>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-3">
                <h6>Add absence</h6>
                <form method="post" data-draft-key="availability">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <select class="form-select mb-2" name="type">
                        <option value="vacation">Vacation</option>
                        <option value="sick_leave">Sick leave</option>
                        <option value="day_off">Day off</option>
                        <option value="conference">Conference</option>
                        <option value="other">Other</option>
                    </select>
                    <input class="form-control mb-2" type="date" name="start_date" required>
                    <input class="form-control mb-2" type="date" name="end_date" required>
                    <input class="form-control mb-2" name="reason" placeholder="Reason">
                    <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card p-3">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>From</th>
                            <th>To</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($availability as $row): ?>
                        <tr>
                            <td><?php echo h($row['username']); ?></td>
                            <td><?php echo h($row['type']); ?></td>
                            <td><?php echo h($row['start_date']); ?></td>
                            <td><?php echo h($row['end_date']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
