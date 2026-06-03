Amazon Mailer
=============

Provides Amazon SES integration for Symfony Mailer.

Configuration example:

```env
# SMTP
MAILER_DSN=ses+smtp://USERNAME:PASSWORD@default:PORT?region=REGION&session_token=SESSION_TOKEN

# HTTP
MAILER_DSN=ses+https://ACCESS_KEY:SECRET_KEY@default?region=REGION&session_token=SESSION_TOKEN

# API
MAILER_DSN=ses+api://ACCESS_KEY:SECRET_KEY@default?region=REGION&session_token=SESSION_TOKEN
```

where:
 - `ACCESS_KEY` is your Amazon SES access key id
 - `SECRET_KEY` is your Amazon SES access key secret
 - `REGION` is Amazon SES selected region (optional, default `eu-west-1`)
 - `SESSION_TOKEN` is your Amazon SES session token (optional)
 - `PORT` is the port you want to communicate to SES with (optional, default `465`)

For the `ses+smtp` / `ses+smtps` schemes, the `PORT` value selects the TLS mode:

 - `465` and `2465` use implicit TLS (TLS wrapper mode);
 - any other port (typically `587` or `2587`) starts in clear text and upgrades via STARTTLS. The
   transport enforces `setRequireTls(true)` on those ports by default, so the SMTP session aborts if
   the server does not advertise `STARTTLS`.

Set `require_tls=0` on the DSN to opt out of the STARTTLS requirement (not recommended; only useful
when an operator-controlled middlebox strips the `STARTTLS` extension and the network path is
trusted). Set `require_tls=1` to enforce STARTTLS even on the implicit-TLS ports.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
