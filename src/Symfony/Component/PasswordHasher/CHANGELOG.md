CHANGELOG
=========

8.1
---

 * Emit a warning on stderr in `security:hash-password` when the password is passed as a command argument (suppressed under `--no-interaction`), listing the leakage channels: shell history, `ps`, container audit logs
 * Support reading the password from standard input by passing `-` as the password argument in `security:hash-password`

6.2
---

 * Use `SensitiveParameter` attribute to redact sensitive values in back traces

5.3
---

 * Add the component
 * Use `bcrypt` as default algorithm in `NativePasswordHasher`
