<aside class="app-sidebar" id="appSidebar">
    <nav class="nav flex-column">
        <a class="nav-link <?php echo is_active('/index.php'); ?>" href="/index.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a class="nav-link <?php echo is_active('/myday.php'); ?>" href="/myday.php"><i class="fa-solid fa-calendar-day"></i> My Day</a>
        <a class="nav-link <?php echo is_active('/projects.php'); ?>" href="/projects.php"><i class="fa-solid fa-layer-group"></i> Projects</a>
        <a class="nav-link <?php echo is_active('/bugs.php'); ?>" href="/bugs.php"><i class="fa-solid fa-bug"></i> Bugs</a>
        <a class="nav-link <?php echo is_active('/testplans.php'); ?>" href="/testplans.php"><i class="fa-solid fa-clipboard-list"></i> Test Plans</a>
        <a class="nav-link <?php echo is_active('/testruns.php'); ?>" href="/testruns.php"><i class="fa-solid fa-flask"></i> Test Runs</a>
        <a class="nav-link <?php echo is_active('/wiki.php'); ?>" href="/wiki.php"><i class="fa-solid fa-book"></i> Wiki</a>
        <a class="nav-link <?php echo is_active('/kanban.php'); ?>" href="/kanban.php"><i class="fa-solid fa-table-columns"></i> Kanban</a>
        <a class="nav-link <?php echo is_active('/calendar.php'); ?>" href="/calendar.php"><i class="fa-solid fa-calendar"></i> Calendar</a>
        <a class="nav-link <?php echo is_active('/reports.php'); ?>" href="/reports.php"><i class="fa-solid fa-chart-line"></i> Reports</a>
        <?php if (($user['role'] ?? '') === 'admin') : ?>
        <a class="nav-link <?php echo is_active('/admin/users.php'); ?>" href="/admin/users.php"><i class="fa-solid fa-users"></i> Users</a>
        <a class="nav-link <?php echo is_active('/admin/console.php'); ?>" href="/admin/console.php"><i class="fa-solid fa-terminal"></i> Admin Console</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="shortcut-title">Favorites</div>
        <?php
        $shortcuts = [];
        if ($user) {
            $shortcuts = fetch_all('SELECT * FROM user_shortcuts WHERE user_id = :id ORDER BY sort_order', [':id' => $user['id']]);
        }
        ?>
        <?php if (!$shortcuts) : ?>
            <div class="text-muted small">No favorites yet.</div>
        <?php else : ?>
            <?php foreach ($shortcuts as $shortcut) : ?>
                <a class="nav-link" href="#"><?php echo h($shortcut['title']); ?></a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>
