# Security and Privacy Guide

## Authentication

- Passwords are hashed with PHP `password_hash()`.
- Login attempts are rate-limited and lockouts are configurable.
- Password reset tokens expire, are single-use, and include `expires_at` and `used_at` state.
- Password changes revoke active sessions for the affected account.
- CSRF protection is applied to state-changing web requests.
- SSO uses the configured OpenID Connect endpoints when enabled.

## Authorization and tenant isolation

- Every authenticated request is evaluated through middleware and controller permissions.
- Company-owned queries must use the authenticated tenant context.
- Managers and HR users receive only the company and employee scopes granted to them.
- Private files are returned through authorization-checked file routes.
- Audit events record sensitive administration and approval actions.

## Monitoring privacy boundaries

Website monitoring records domain-level activity, duration, work state, category, policy action, and device/session context. It does not intentionally store full URLs, query strings, page contents, keystrokes, webcam images, or microphone audio.

Collection should be limited to scheduled work windows and configured breaks. Monitoring policies should be visible to employees, include a business purpose, define retention, and provide an explanation/dispute path.

## Required operational controls

- Use HTTPS in production.
- Keep `.env`, backups, logs, uploaded files, and monitoring evidence outside public access.
- Use least-privilege database credentials.
- Review admin and monitoring access logs regularly.
- Limit screenshots and activity retention to the approved policy period.
- Encrypt backups and restrict restore-test databases.
- Rotate compromised passwords, device tokens, and SSO secrets immediately.

## Security review checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_KEY` is set and secret
- [ ] Secure cookies and HTTPS are enabled
- [ ] Default administrator password changed
- [ ] Storage directories are not publicly browsable
- [ ] Database and backup access is restricted
- [ ] Tenant isolation tests pass
- [ ] Password reset and session revocation tests pass
- [ ] Audit and monitoring access logs are retained according to policy
