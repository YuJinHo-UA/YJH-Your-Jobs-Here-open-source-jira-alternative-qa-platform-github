<?php
declare(strict_types=1);

/**
 * Optional SQLite authorizer layer for deployments using SQLite3 API directly.
 * Note: current app uses PDO for DB access; keep this as hardening scaffold.
 */
function setup_authorizer(SQLite3 $db, string $userRole): void
{
    $db->setAuthorizer(static function (int $action, ?string $arg1 = null) use ($userRole): int {
        if ($userRole === 'admin') {
            return SQLite3::OK;
        }

        if ($action === SQLite3::READ) {
            return SQLite3::OK;
        }

        if ($action === SQLite3::INSERT || $action === SQLite3::UPDATE) {
            $allowedTables = ['bugs', 'test_cases', 'bug_comments', 'card_comments'];
            return in_array((string)$arg1, $allowedTables, true) ? SQLite3::OK : SQLite3::DENY;
        }

        if (in_array($action, [SQLite3::DELETE, SQLite3::DROP_TABLE, SQLite3::ALTER_TABLE], true)) {
            return SQLite3::DENY;
        }

        return SQLite3::DENY;
    });
}

