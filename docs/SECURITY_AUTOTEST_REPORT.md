# Security Autotest Report

Date: 2026-03-29
Mode: read/static checks + transactional DB checks

## Results

- PASS | ST-1 required security tables | ai_logs,rate_limit_entries,security_log
- PASS | ST-2 ai rate limit wired | rate limit calls found
- PASS | ST-3 ai exception details hidden | details not exposed
- PASS | ST-4 ai log encryption enabled | enc marker write found
- PASS | ST-5 no hardcoded fallback key | fallback key absent
- PASS | ST-6 db status constraint | blocked

**OVERALL:** PASS

## Notes

- DB checks run under savepoint and rolled back.
- Static checks validate presence of security controls in source code.
