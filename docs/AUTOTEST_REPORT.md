# Autotest Report

Date: 2026-04-06
Mode: transactional (`SAVEPOINT` + `ROLLBACK`)

## Results

- PASS | AT-1 bug create/read | (33, 'AT_BUG_141025')
- PASS | AT-2 unique email | duplicate blocked
- PASS | AT-3 testcase-bug link | ('AT_TC_141025', 'AT_LINKBUG_141025')
- PASS | AT-4 bug status validation | invalid status blocked
- PASS | AT-5 execution bug foreign key | invalid bug_id blocked
- PASS | AT-6 translation cache uniqueness | duplicate key blocked
- PASS | AT-7 kanban card reorder | order updated

**OVERALL:** PASS

## Notes

- Tests executed against project `database.sqlite`.
- Created entities were rolled back and not persisted.
