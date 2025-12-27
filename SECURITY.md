# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.0.x   | :white_check_mark: |
| 1.9.x   | :white_check_mark: |
| 1.8.x   | :x:                |
| < 1.8   | :x:                |

## Reporting a Vulnerability

If you discover a security vulnerability in Content Freshness Monitor, please report it responsibly.

### How to Report

1. **Do not** open a public GitHub issue for security vulnerabilities
2. Email your findings to the plugin maintainer (see plugin author information)
3. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Any suggested fixes (optional)

### What to Expect

- **Acknowledgment**: We will acknowledge receipt within 48 hours
- **Investigation**: We will investigate and validate the report within 7 days
- **Fix Timeline**: Critical vulnerabilities will be patched within 14 days
- **Disclosure**: We will coordinate disclosure timing with you
- **Credit**: We will credit you in the changelog (unless you prefer anonymity)

## Security Measures

This plugin implements the following security best practices:

### Input Validation & Sanitization
- All user inputs are sanitized using WordPress functions (`absint()`, `sanitize_key()`, `sanitize_email()`, etc.)
- Database queries use `WP_Query` and prepared statements to prevent SQL injection

### Output Escaping
- All output is escaped using `esc_html()`, `esc_attr()`, `esc_url()`, etc.
- JavaScript data is properly JSON-encoded

### Access Control
- AJAX handlers verify nonces for CSRF protection
- Capability checks (`edit_posts`, `manage_options`) enforce authorization
- REST API endpoints have proper permission callbacks

### Direct Access Prevention
- All PHP files check for `ABSPATH` constant
- Prevents direct file access from web browsers

### Data Privacy
- No external API calls or data transmission
- All data stays within your WordPress installation
- Clean uninstall removes all plugin data

## Security Audit Checklist

When reviewing code changes, verify:

- [ ] Nonces on all form submissions and AJAX requests
- [ ] Capability checks before privileged operations
- [ ] Input sanitization on all user data
- [ ] Output escaping in all templates
- [ ] ABSPATH check at top of PHP files
- [ ] No direct database queries (use WP_Query/wpdb prepared statements)
- [ ] No eval(), shell_exec(), or similar dangerous functions
- [ ] No hardcoded credentials or API keys
