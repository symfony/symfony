MailKite Bridge
===============

Provides MailKite integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=mailkite+smtp://API_KEY@default

# same thing, shorter
MAILER_DSN=mailkite://API_KEY@default

# SMTP over implicit TLS (port 465)
MAILER_DSN=mailkite+smtps://API_KEY@default

# API
MAILER_DSN=mailkite+api://API_KEY@default
```

where `API_KEY` is your MailKite API key.

`mailkite` is an alias for `mailkite+smtp`, which submits to
`smtp.mailkite.dev:587` with STARTTLS. Use `mailkite+api` instead when outbound
SMTP is blocked, which is common on PaaS hosts: it sends over HTTPS and reports
the error message returned by the API on a failed send.

The API transport carries what the send endpoint accepts: sender, recipients,
subject, text and HTML bodies, a single reply-to address (several are sent as a
raw `Reply-To` header), attachments and custom headers. Inline (`cid:`)
attachments are rejected, because the API has no `Content-ID` support and the
reference would silently break; use the SMTP transport for those.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
