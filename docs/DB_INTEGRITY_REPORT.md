# DB Integrity Report

Date: 2026-03-29  
Database: `database.sqlite`  
Mode: `PRAGMA foreign_keys=ON`, transactional checks with `SAVEPOINT` + `ROLLBACK`

## Scope

Validated 5 critical constraints:

1. Data persistence in `bugs`
2. Cascade delete from `projects` to `bugs`
3. Test case to bug relationship via `test_executions.bug_id`
4. `UNIQUE` behavior on `users.email`
5. Foreign key protection for invalid `bugs.project_id`

## Fix Applied

- Updated schema definition in `config/db.php`:
  - `bugs.project_id` now uses `FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE`.
- Added migration routine:
  - `apply_fk_migrations()` recreates `bugs` safely using `bugs_new` and table swap.
  - `repair_legacy_bug_fk_targets()` repairs legacy references to `bugs_old` in dependent tables.

## Execution Logs

```text
=== DB INTEGRITY REPORT WITH LOGS ===
RESULTS:
PASS | 1 save data | (25, 'QA_BUG_201226')
PASS | 2 cascade delete | bug lookup=None
PASS | 3 tc-bug link | ('QA_TC_201226', 'QA_LINKBUG_201226')
PASS | 4 unique email | error=UNIQUE constraint failed: users.email
PASS | 5 foreign key | error=FOREIGN KEY constraint failed
OVERALL | PASS
LOGS:
- INIT: users.id=1, projects.id=1, suites.id=1, plans.id=1
- SQL1: INSERT INTO bugs (project_id,title,description,severity,priority,reporter_id) VALUES (?,?,?,?,?,?) | params=(1,QA_BUG_201226,qa,major,high,1)
- SQL2: delete project id=5, expect bug id=26 cascade
```

## Conclusion

All checks pass after migration and repair of legacy foreign key targets.
