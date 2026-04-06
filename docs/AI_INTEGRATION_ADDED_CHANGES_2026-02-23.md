# AI Integration: Added Changes

Date: 2026-02-23

## Added AI backend
- `ai/ai_helper.php` - core helper for AI requests, cache usage, provider routing (`mock`/`openai`).
- `ai/ai_config.php` - AI provider/model/timeouts configuration via env vars.
- `api/ai.php` - API endpoint for AI actions:
  - `assist_bug`
  - `check_duplicates`
  - `generate_test_cases`
  - `generate_report`
- `includes/ai_integration.php` - helper accessor `ai_helper()`.

## Added prompts
- `ai/prompts/test_case.txt`
- `ai/prompts/bug_analysis.txt`
- `ai/prompts/duplicate_check.txt`
- `ai/prompts/report.txt`

## Database migrations added
Updated `config/db.php` with `apply_ai_migrations()` and startup call.
New tables:
- `ai_cache`
- `ai_logs`
- `ai_templates`

Added indexes for AI tables:
- `idx_ai_cache_hash`
- `idx_ai_logs_user_created`
- `idx_ai_logs_action_created`
- `idx_ai_templates_user_type`

## Frontend integration
- `assets/js/ai-assistant.js` - client logic for AI buttons and rendering results.
- `includes/footer.php` - connected `ai-assistant.js`.

### UI updates
- `bug.php`:
  - button `?? Допомогти з описом`
  - button `?? Перевірити дублікати`
  - result blocks for bug assist and duplicates
- `testcase.php`:
  - button `?? Згенерувати тест-кейс`
  - result block for generated cases
- `reports.php`:
  - button `?? Згенерувати AI-звіт`
  - result block for AI report

## Added image attachments to project
Copied to `assets/img/ai/`:
- `assets/img/ai/ai-chat-attachment-4980585251587164067.png`
- `assets/img/ai/ai-chat-attachment-11448513745172740766.png`

## Notes
- Default provider is `mock` (works without API key).
- To enable OpenAI, set env vars:
  - `YJH_AI_PROVIDER=openai`
  - `OPENAI_API_KEY=...`
  - optional: `YJH_AI_MODEL`, `YJH_OPENAI_URL`, `YJH_AI_CACHE_TTL`, `YJH_AI_TIMEOUT`

## Dark theme defect fix (2026-02-23)
- Fixed AI chat modal contrast/styling for dark theme.
- Added bug report: docs/bugs/BUG-2026-02-23-01-ai-chat-dark-theme.md
- Added screenshot: assets/img/ai/ai-chat-attachment-15133509951240967712.png
