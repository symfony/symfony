SendPulse Mailer
===============

Provides SendPulse integration for Symfony Mailer.

DSN examples
------------

```dotenv
# SMTP credentials from the SMTP service settings
MAILER_DSN=sendpulse+smtp://USERNAME:PASSWORD@default

# API client credentials from the account API settings
MAILER_DSN=sendpulse+api://CLIENT_ID:CLIENT_SECRET@default
```

URL-encode credentials containing reserved URI characters. The `sendpulse://`
alias selects SMTP. API authentication is lazy; OAuth tokens are retained only
in the transport instance until shortly before expiry. One explicit HTTP 401
triggers token renewal and one retry. Other send failures are not retried.

The API transport supports HTML/text, envelope sender and recipients, Cc, Bcc,
one Reply-To address, and binary file attachments with distinct filenames.
It requires at least one To recipient. Inline attachments and multiple Reply-To
addresses are rejected before HTTP requests; use SMTP for these messages or for
custom MIME headers, signed/encrypted messages and full MIME fidelity.
Automation 360 and webhooks are outside this bridge's scope.

Resources
---------

 * [SendPulse SMTP API](https://sendpulse.com/integrations/api/smtp)
 * [Symfony Mailer documentation](https://symfony.com/doc/current/mailer.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
