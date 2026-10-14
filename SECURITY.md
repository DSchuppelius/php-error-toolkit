# Security Policy

## Supported Versions

Security fixes are made for the latest release (the newest tag) and for `main`.
Older releases are not patched - please update to the latest version.

## Reporting a Vulnerability

Please do **not** open a public issue. Report vulnerabilities by e-mail to
**info@schuppelius.org** and include

- the affected class or method,
- the version (tag) or commit,
- the steps to reproduce (a minimal, self-made sample file is enough) and
- your assessment of what an attacker can achieve.

Of particular interest are secrets or personal data leaking into log output (redaction,
log injection via newlines or control characters), the file permissions of log and fallback
files, and how helper programs are started (`ProcessHelper`: argument lists, time limits).

Do not send real customer documents, credentials or other personal data.

We will get back to you within five working days. Please give us the opportunity to
fix the issue before you publish details.

Deutsch: Bitte melden Sie Sicherheitslücken nicht über öffentliche Issues, sondern per
E-Mail an **info@schuppelius.org**. Wir antworten innerhalb von fünf Werktagen.
