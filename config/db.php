<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/encryption.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = __DIR__ . '/../database.sqlite';
    $isNew = !file_exists($dbPath);

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    initialize_schema($pdo);
    apply_security_migrations($pdo);
    apply_ai_migrations($pdo);

    if ($isNew) {
        seed_demo_data($pdo);
    } else {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            seed_demo_data($pdo);
        }
    }

    return $pdo;
}

function initialize_schema(PDO $pdo): void
{
    $schema = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT CHECK(role IN ('admin','qa','developer','viewer')) NOT NULL,
    avatar TEXT,
    theme TEXT DEFAULT 'light',
    language TEXT DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS user_settings (
    user_id INTEGER PRIMARY KEY,
    settings_json TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS user_availability (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT CHECK(type IN ('vacation','sick_leave','day_off','conference','other')),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS user_shortcuts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    title TEXT,
    sort_order INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT CHECK(status IN ('active','archived')) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME
);

CREATE TABLE IF NOT EXISTS releases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    release_date DATE,
    status TEXT CHECK(status IN ('planned','in_progress','released','cancelled')) DEFAULT 'planned',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS bugs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    release_id INTEGER,
    title TEXT NOT NULL,
    description TEXT,
    steps_to_reproduce TEXT,
    expected_result TEXT,
    actual_result TEXT,
    environment TEXT,
    severity TEXT CHECK(severity IN ('blocker','critical','major','minor','trivial')) NOT NULL,
    priority TEXT CHECK(priority IN ('highest','high','medium','low','lowest')) NOT NULL,
    status TEXT CHECK(status IN ('new','assigned','in_progress','fixed','verified','closed','reopened')) DEFAULT 'new',
    assignee_id INTEGER,
    reporter_id INTEGER NOT NULL,
    resolution TEXT CHECK(resolution IN ('fixed','duplicate','cannot_reproduce','wontfix','worksforme',NULL)),
    duplicate_of INTEGER,
    similarity_score FLOAT,
    due_date DATE,
    estimated_time INTEGER,
    actual_time INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    closed_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (release_id) REFERENCES releases(id),
    FOREIGN KEY (assignee_id) REFERENCES users(id),
    FOREIGN KEY (reporter_id) REFERENCES users(id),
    FOREIGN KEY (duplicate_of) REFERENCES bugs(id)
);

CREATE TABLE IF NOT EXISTS bug_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    parent_id INTEGER,
    message TEXT NOT NULL,
    attachments_json TEXT,
    is_system BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES bug_comments(id)
);

CREATE TABLE IF NOT EXISTS bug_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    field TEXT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bug_mentions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL,
    comment_id INTEGER,
    user_id INTEGER NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (comment_id) REFERENCES bug_comments(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bug_watchers (
    bug_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bug_id, user_id),
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bug_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    title_template TEXT,
    description_template TEXT,
    steps_template TEXT,
    severity TEXT,
    priority TEXT,
    environment_template TEXT,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS bug_similarity_cache (
    bug_id_1 INTEGER NOT NULL,
    bug_id_2 INTEGER NOT NULL,
    similarity_score FLOAT NOT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bug_id_1, bug_id_2),
    FOREIGN KEY (bug_id_1) REFERENCES bugs(id),
    FOREIGN KEY (bug_id_2) REFERENCES bugs(id)
);

CREATE TABLE IF NOT EXISTS git_integrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    provider TEXT CHECK(provider IN ('github','gitlab','bitbucket')) NOT NULL,
    repository_url TEXT NOT NULL,
    webhook_secret TEXT,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS git_commits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER,
    test_case_id INTEGER,
    commit_hash TEXT NOT NULL,
    commit_message TEXT,
    author_name TEXT,
    author_email TEXT,
    committed_at DATETIME,
    branch TEXT,
    repository_url TEXT,
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (test_case_id) REFERENCES test_cases(id)
);

CREATE TABLE IF NOT EXISTS test_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    release_id INTEGER,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT CHECK(status IN ('draft','active','completed')) DEFAULT 'draft',
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (release_id) REFERENCES releases(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS test_suites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id INTEGER NOT NULL,
    parent_suite_id INTEGER,
    name TEXT NOT NULL,
    description TEXT,
    order_index INTEGER DEFAULT 0,
    FOREIGN KEY (plan_id) REFERENCES test_plans(id),
    FOREIGN KEY (parent_suite_id) REFERENCES test_suites(id)
);

CREATE TABLE IF NOT EXISTS test_cases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    suite_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    preconditions TEXT,
    steps_json TEXT NOT NULL,
    expected_result_json TEXT NOT NULL,
    checklist_json TEXT,
    type TEXT CHECK(type IN ('functional','ui','performance','security','integration')),
    priority TEXT CHECK(priority IN ('critical','high','medium','low')),
    estimated_time INTEGER,
    automation_status TEXT CHECK(automation_status IN ('manual','automated','partially')) DEFAULT 'manual',
    created_by INTEGER NOT NULL,
    updated_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (suite_id) REFERENCES test_suites(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS test_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    plan_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT CHECK(status IN ('in_progress','completed','aborted')) DEFAULT 'in_progress',
    assigned_to INTEGER,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (plan_id) REFERENCES test_plans(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS test_executions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    test_run_id INTEGER NOT NULL,
    test_case_id INTEGER NOT NULL,
    executed_by INTEGER NOT NULL,
    status TEXT CHECK(status IN ('pass','fail','blocked','not_tested','skipped')) NOT NULL,
    actual_result TEXT,
    bug_id INTEGER,
    execution_time INTEGER,
    attachments_json TEXT,
    notes TEXT,
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_run_id) REFERENCES test_runs(id),
    FOREIGN KEY (test_case_id) REFERENCES test_cases(id),
    FOREIGN KEY (executed_by) REFERENCES users(id),
    FOREIGN KEY (bug_id) REFERENCES bugs(id)
);

CREATE TABLE IF NOT EXISTS testcase_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    title_template TEXT,
    steps_template TEXT,
    expected_template TEXT,
    checklist_template TEXT,
    type TEXT,
    priority TEXT,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wiki_pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    parent_id INTEGER,
    slug TEXT NOT NULL,
    title TEXT NOT NULL,
    content TEXT,
    author_id INTEGER NOT NULL,
    editor_id INTEGER,
    is_published BOOLEAN DEFAULT 1,
    version INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (parent_id) REFERENCES wiki_pages(id),
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (editor_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wiki_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content_diff TEXT,
    version INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS wiki_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    uploaded_by INTEGER NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES wiki_pages(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS boards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    settings_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS board_columns (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    order_index INTEGER NOT NULL,
    wip_limit INTEGER,
    FOREIGN KEY (board_id) REFERENCES boards(id)
);

CREATE TABLE IF NOT EXISTS board_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    board_id INTEGER NOT NULL,
    column_id INTEGER NOT NULL,
    bug_id INTEGER,
    test_case_id INTEGER,
    wiki_page_id INTEGER,
    title TEXT NOT NULL,
    description TEXT,
    assignee_id INTEGER,
    label_json TEXT,
    due_date DATE,
    order_index INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
    FOREIGN KEY (board_id) REFERENCES boards(id),
    FOREIGN KEY (column_id) REFERENCES board_columns(id),
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (test_case_id) REFERENCES test_cases(id),
    FOREIGN KEY (wiki_page_id) REFERENCES wiki_pages(id),
    FOREIGN KEY (assignee_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS card_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES board_cards(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS card_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
    uploaded_by INTEGER NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES board_cards(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    uploaded_by INTEGER NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    details_json TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS saved_filters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    filter_json TEXT NOT NULL,
    is_default BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    events_json TEXT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    secret_key TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS public_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_type TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    token TEXT UNIQUE NOT NULL,
    expires_at DATETIME,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    icon TEXT
);

CREATE TABLE IF NOT EXISTS user_achievements (
    user_id INTEGER NOT NULL,
    achievement_id INTEGER NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (achievement_id) REFERENCES achievements(id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    message TEXT,
    level TEXT CHECK(level IN ('success','error','info','warning')) DEFAULT 'info',
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS translation_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_lang TEXT NOT NULL,
    target_lang TEXT NOT NULL,
    text_hash TEXT NOT NULL,
    source_text TEXT NOT NULL,
    translated_text TEXT NOT NULL,
    provider TEXT DEFAULT 'libretranslate',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(source_lang, target_lang, text_hash)
);
SQL;

    $pdo->exec($schema);
}

function seed_demo_data(PDO $pdo): void
{
    $pdo->beginTransaction();

    $users = [
        ['admin', 'admin@yujin.ho', 'admin123', 'admin'],
        ['qa', 'qa@yujin.ho', 'qa123', 'qa'],
        ['dev', 'dev@yujin.ho', 'dev123', 'developer'],
        ['viewer', 'viewer@yujin.ho', 'viewer123', 'viewer'],
    ];

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (:u, :e, :p, :r)');
    foreach ($users as $user) {
        $stmt->execute([
            ':u' => $user[0],
            ':e' => $user[1],
            ':p' => password_hash($user[2], PASSWORD_DEFAULT),
            ':r' => $user[3],
        ]);
    }

    $pdo->exec("INSERT INTO projects (name, description, status) VALUES
        ('Mobile App', 'iOS and Android delivery', 'active'),
        ('Web Portal', 'Customer-facing portal', 'active')");

    $pdo->exec("INSERT INTO releases (project_id, name, description, release_date, status) VALUES
        (1, 'v1.0', 'Initial release', date('now','-30 day'), 'released'),
        (1, 'v2.0', 'Major update', date('now','+15 day'), 'in_progress')");

    $pdo->exec("INSERT INTO bug_templates (project_id, name, title_template, description_template, steps_template, severity, priority, environment_template, created_by) VALUES
        (1, 'Crash Template', 'App crashes on launch', 'Crash on startup', '1. Open app\n2. Observe crash', 'critical', 'highest', 'iOS 17', 2)");

    $pdo->exec("INSERT INTO bugs (project_id, release_id, title, description, severity, priority, status, assignee_id, reporter_id, due_date) VALUES
        (1, 2, 'Login fails with 2FA enabled', 'Users cannot login when 2FA is enabled', 'critical', 'high', 'assigned', 3, 2, date('now','+7 day')),
        (1, 2, 'Push notifications not delivered', 'No notifications on Android', 'major', 'medium', 'in_progress', 3, 2, date('now','+10 day')),
        (2, NULL, 'Dashboard layout breaks on Safari', 'Cards overlap on Safari 17', 'minor', 'low', 'new', NULL, 2, date('now','+14 day')),
        (2, NULL, 'Export CSV missing headers', 'CSV export lacks header row', 'major', 'high', 'fixed', 3, 2, date('now','-2 day')),
        (1, 2, 'Settings screen freeze', 'Scrolling freezes after toggling theme', 'major', 'medium', 'verified', 3, 2, date('now','+3 day')),
        (1, 1, 'Crash on profile edit', 'Editing profile triggers crash', 'critical', 'highest', 'closed', 3, 2, date('now','-20 day')),
        (2, NULL, 'Search results empty', 'Search returns zero for valid queries', 'major', 'high', 'reopened', 3, 2, date('now','+5 day')),
        (1, 2, 'Slow cold start', 'App cold start takes 12s', 'minor', 'low', 'new', NULL, 2, date('now','+20 day')),
        (2, NULL, 'Kanban drag drop glitch', 'Cards jump on drop', 'minor', 'medium', 'assigned', 3, 2, date('now','+9 day')),
        (1, 2, 'Billing page fails', 'Payment screen shows error 500', 'blocker', 'highest', 'in_progress', 3, 2, date('now','+1 day'))");

    $pdo->exec("INSERT INTO test_plans (project_id, release_id, name, description, status, created_by) VALUES
        (1, 2, 'Regression v2.0', 'Full regression for v2.0', 'active', 2)");

    $pdo->exec("INSERT INTO test_suites (plan_id, parent_suite_id, name, description, order_index) VALUES
        (1, NULL, 'Authentication', 'Auth and sessions', 1),
        (1, NULL, 'Payments', 'Payment flows', 2)");

    $pdo->exec(<<<'SQL'
INSERT INTO test_cases (suite_id, title, description, preconditions, steps_json, expected_result_json, checklist_json, type, priority, estimated_time, automation_status, created_by) VALUES
    (1, 'Login with valid credentials', 'Basic login', 'User exists', '["Open app","Enter credentials","Submit"]', '["User lands on dashboard"]', '["Token stored","Session active"]', 'functional', 'high', 5, 'manual', 2),
    (1, 'Password reset flow', 'Forgot password', 'User exists', '["Open login","Tap forgot","Submit email"]', '["Reset email sent"]', '["Email received","Link valid"]', 'functional', 'medium', 8, 'manual', 2),
    (1, '2FA toggle', 'Enable 2FA', 'User logged in', '["Open settings","Enable 2FA"]', '["2FA enabled"]', '["Code required"]', 'security', 'high', 10, 'manual', 2),
    (2, 'Card payment success', 'Visa payment', 'User logged in', '["Open billing","Enter card","Pay"]', '["Payment succeeds"]', '["Receipt generated"]', 'functional', 'critical', 12, 'manual', 2),
    (2, 'Refund flow', 'Refund payment', 'Payment exists', '["Open order","Request refund"]', '["Refund initiated"]', '["Status updated"]', 'functional', 'medium', 9, 'manual', 2)
SQL);

    $pdo->exec("INSERT INTO test_runs (plan_id, name, description, status, assigned_to, created_by) VALUES
        (1, 'Smoke Test', 'Quick sanity run', 'in_progress', 2, 2)");

    $pdo->exec("INSERT INTO test_executions (test_run_id, test_case_id, executed_by, status, actual_result, execution_time, notes) VALUES
        (1, 1, 2, 'pass', 'Login successful', 4, 'OK'),
        (1, 2, 2, 'pass', 'Reset email sent', 6, 'OK'),
        (1, 4, 2, 'fail', 'Payment error 500', 7, 'Create bug')");

    $pdo->exec("INSERT INTO wiki_pages (project_id, parent_id, slug, title, content, author_id, editor_id) VALUES
        (1, NULL, 'home', 'Home', 'Welcome to YJH knowledge base.', 2, 2),
        (1, 1, 'release-checklist', 'Release Checklist', 'Checklist for releases.', 2, 2),
        (1, 1, 'onboarding', 'Onboarding Guide', 'Steps for new team members.', 2, 2)");

    $pdo->exec("INSERT INTO boards (project_id, name, description) VALUES
        (1, 'Development', 'Main workflow')");

    $pdo->exec("INSERT INTO board_columns (board_id, name, order_index, wip_limit) VALUES
        (1, 'To Do', 1, 5),
        (1, 'In Progress', 2, 3),
        (1, 'Review', 3, 3),
        (1, 'Done', 4, 10)");

    $pdo->exec(<<<'SQL'
INSERT INTO board_cards (board_id, column_id, bug_id, title, description, assignee_id, label_json, due_date, order_index) VALUES
    (1, 1, 1, 'Login fails with 2FA enabled', 'Investigate regression', 3, '["auth","urgent"]', date('now','+7 day'), 1),
    (1, 2, 2, 'Push notifications not delivered', 'Fix Android push', 3, '["android"]', date('now','+10 day'), 1),
    (1, 3, NULL, 'Design review for billing', 'Review UI changes', 2, '["ui"]', date('now','+2 day'), 1),
    (1, 4, 4, 'Export CSV missing headers', 'Ready for release', 3, '["export"]', date('now','-1 day'), 1)
SQL);

    $pdo->exec(<<<'SQL'
INSERT INTO activity_log (user_id, action, target_type, target_id, details_json) VALUES
    (2, 'created', 'bug', 1, '{"title":"Login fails with 2FA enabled"}'),
    (3, 'status_changed', 'bug', 4, '{"from":"new","to":"fixed"}'),
    (2, 'created', 'test_run', 1, '{"name":"Smoke Test"}')
SQL);

    $pdo->exec("INSERT INTO achievements (code, name, description, icon) VALUES
        ('bug_hunter', 'Bug Hunter', 'Reported 10 bugs', 'fa-bug'),
        ('automation_master', 'Automation Master', 'Automated 20 tests', 'fa-robot'),
        ('release_guardian', 'Release Guardian', 'Closed 50 release bugs', 'fa-shield-halved')");

    $pdo->exec("INSERT INTO user_achievements (user_id, achievement_id) VALUES (2, 1), (2, 2), (3, 1)");

    $pdo->commit();
}

function apply_security_migrations(PDO $pdo): void
{
    ensure_column($pdo, 'users', 'email_encrypted', 'TEXT');
    ensure_column($pdo, 'users', 'email_hash', 'TEXT');
    ensure_column($pdo, 'users', 'twofa_secret_encrypted', 'TEXT');
    ensure_column($pdo, 'users', 'twofa_enabled', 'INTEGER NOT NULL DEFAULT 0');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS security_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rate_limit_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT NOT NULL,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_hash ON users(email_hash)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_security_log_action_created ON security_log(action, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_security_log_user_created ON security_log(user_id, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limit_key_time ON rate_limit_entries(key, attempted_at)');

    backfill_encrypted_emails($pdo);
}

function apply_ai_migrations(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_cache (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            prompt_hash TEXT UNIQUE NOT NULL,
            prompt TEXT NOT NULL,
            response TEXT NOT NULL,
            model TEXT NOT NULL,
            tokens_used INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            prompt TEXT,
            response TEXT,
            tokens_used INTEGER,
            duration_ms INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            template_type TEXT NOT NULL,
            template_text TEXT NOT NULL,
            is_public BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_cache_hash ON ai_cache(prompt_hash)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_logs_user_created ON ai_logs(user_id, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_logs_action_created ON ai_logs(action_type, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_templates_user_type ON ai_templates(user_id, template_type)');
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query("PRAGMA table_info($table)");
    $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
}

function backfill_encrypted_emails(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT id, email, email_encrypted, email_hash FROM users');
    $users = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!$users) {
        return;
    }

    $update = $pdo->prepare(
        'UPDATE users
         SET email = :email_marker,
             email_encrypted = :email_encrypted,
             email_hash = :email_hash
         WHERE id = :id'
    );

    foreach ($users as $user) {
        $emailEncrypted = (string)($user['email_encrypted'] ?? '');
        $emailHash = (string)($user['email_hash'] ?? '');
        $rawEmail = (string)($user['email'] ?? '');
        $plainEmail = $emailEncrypted !== '' ? decrypt_value($emailEncrypted) : $rawEmail;

        if ($plainEmail === '') {
            continue;
        }

        $nextHash = email_hash($plainEmail);
        $nextEncrypted = $emailEncrypted !== '' ? $emailEncrypted : encrypt_value($plainEmail);
        $marker = 'enc:' . substr($nextHash, 0, 24);

        if ($rawEmail === $marker && $emailHash === $nextHash && $emailEncrypted !== '') {
            continue;
        }

        $update->execute([
            ':id' => (int)$user['id'],
            ':email_marker' => $marker,
            ':email_encrypted' => $nextEncrypted,
            ':email_hash' => $nextHash,
        ]);
    }
}
