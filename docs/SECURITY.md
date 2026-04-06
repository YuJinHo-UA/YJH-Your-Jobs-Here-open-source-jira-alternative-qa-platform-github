# Security in YJH

## Protected Components
- SQL queries use prepared statements (`PDO::prepare` + bound params).
- XSS protection via output escaping helper `h()` / `e()`.
- CSRF token checks for form submissions (`includes/csrf.php`).
- HTTP security headers via `.htaccess` and runtime fallback (`includes/security_headers.php`).
- Rate limiting for login and 2FA attempts (`includes/rate_limit.php`).
- Security event logging in `security_log` (`includes/security.php`).
- Google Authenticator compatible 2FA (TOTP) (`2fa-setup.php`, `2fa-verify.php`).
- Encryption helpers for sensitive values (`includes/encryption.php`).

## Data Protection
- User emails are migrated to encrypted storage (`users.email_encrypted`).
- Search/login uses `users.email_hash`.
- Integration/API secrets should be saved encrypted with `encrypt_value()`.

## Security Tables
- `security_log`: suspicious and security-relevant events.
- `rate_limit_entries`: attempt tracking for brute-force defense.

## 2FA Workflow
1. User enters login/password.
2. If 2FA enabled, user is redirected to `/2fa-verify.php`.
3. Valid 6-digit TOTP code completes login.

## Admin Recommendations
1. Use HTTPS in production.
2. Set `YJH_ENCRYPTION_KEY` in environment (do not use fallback key).
3. Review `security_log` regularly.
4. Run scheduled backups (`php backup/backup.php`).
5. Rotate credentials and integration secrets periodically.

## Manual Security Checks
1. SQL injection: try payloads like `' OR '1'='1` in text fields.
2. XSS: try `<script>alert(1)</script>` in user-generated fields.
3. CSRF: submit form without token and verify rejection.
4. Rate limiting: exceed 5 login/2FA attempts in 15 minutes.
5. 2FA: setup with Google Authenticator and verify login challenge.

