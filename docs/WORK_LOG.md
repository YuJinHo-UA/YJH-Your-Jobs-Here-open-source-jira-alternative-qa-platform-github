# Work Log

## 2026-03-29

### Completed
- Improved `Test Cases` UX in `testplan.php`:
  - compact row layout with expandable details section;
  - inline edit remains available without opening separate pages.
- Added quick bug creation from a test case in `testcase.php`:
  - new action `create_bug_from_case`;
  - button `Create Bug from this Test Case`;
  - auto-fills bug title/description/steps/expected from the case data.
- Updated `testrun.php`:
  - bug creation from failed execution now uses project from test plan (not hardcoded);
  - added expected result column in execution table;
  - added `Open bug` button when bug is already linked.
- Updated AI module security:
  - request rate limit in `api/ai.php`;
  - prompt/response redaction for sensitive data;
  - encrypted AI log storage (`enc:` values);
  - hidden internal exception details from API responses.
- Ran autotests (`tools/run_autotests.py`) and refreshed:
  - `docs/AUTOTEST_REPORT.md`
  - `docs/AUTOTEST_LOG.txt`
  - latest result: OVERALL PASS.

### Data updates requested by user
- Assigned bugs to project `YJH Your Job's Here` (project_id=4).
- Set bug environment to `Windows 11`.
- Filled empty `test_cases.checklist_json` values with a default QA checklist.

### Test Run module analysis (what is still needed)
- Add execution filters (`status`, `assignee`, `has_bug`) and quick search.
- Add bulk actions (`mark pass/fail/blocked` for selected cases).
- Add richer evidence support:
  - attach screenshot/file to execution;
  - direct link to related bug and reverse link in bug page.
- Add execution history/audit per case (who changed status and when).
- Add run progress widgets:
  - pass/fail/blocked counters;
  - completion percentage and remaining not tested.
- Add validation and helper UX:
  - require `actual_result` when `status=fail`;
  - show warning when fail has no linked bug.

### Calendar and assignment integration
- Updated `calendar.php`:
  - admin can create/edit absence records for any user (`target_user_id`);
  - non-admin users can modify only their own records;
  - absence table now shows reason and visual type icons;
  - current-day unavailability marker (`⚠️`) added per user row.
- Updated `includes/functions.php`:
  - added `is_user_available(userId, dateFrom, dateTo)` helper;
  - added `get_user_unavailability(userId, date)` helper.
- Updated `api/bugs.php`:
  - assignment is blocked if assignee is unavailable on selected due date;
  - unreachable legacy validation block removed and moved into active POST flow.
- Updated `myday.php`:
  - fixed user initialization order;
  - added warning banner when current user is marked unavailable today.
- Updated `bugs.php`:
  - added assignee availability hint in bug list (`⚠️ unavailable today (...)`).

### Admin console and logging
- Added `includes/logger.php` with channels:
  - `app.log`
  - `security.log`
  - `ai.log`
- Added `admin/console.php` (admin-only):
  - runtime counters (bugs/tests/wiki/users);
  - DB integrity check action;
  - DB backup action;
  - clear logs action;
  - live tail views for app/security/ai logs and PHP error log.
- Updated `includes/sidebar.php`:
  - added navigation entry `Admin Console` for admins.
- Updated logging integrations:
  - `includes/functions.php` now writes `record_activity` events to `app.log`;
  - `includes/security.php` writes security events to `security.log`;
  - `api/ai.php` writes AI metadata events to `ai.log`.
