# Load Test Report (50 virtual users)

Date: 2026-03-29
Tables covered: 43
Total read operations: 2150
Passed: 2150
Failed: 0
Avg response (ms): 41.29
P95 response (ms): 63.23
Total elapsed (ms): 2362.86

## Data safety

- Write simulation in rollback scope: PASS
- Details: 50x inserts into bugs and test_cases executed inside rollback scope
- No inserted load-test records were persisted to database.

## Covered tables

achievements, activity_log, ai_cache, ai_logs, ai_templates, attachments, board_cards, board_columns, boards, bug_comments, bug_history, bug_mentions, bug_similarity_cache, bug_templates, bug_watchers, bugs, card_attachments, card_comments, git_commits, git_integrations, notifications, projects, public_links, rate_limit_entries, releases, saved_filters, security_log, test_cases, test_executions, test_plans, test_runs, test_suites, testcase_templates, translation_cache, user_achievements, user_availability, user_settings, user_shortcuts, users, webhooks, wiki_attachments, wiki_history, wiki_pages
