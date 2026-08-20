Cloudflare Bridge
=================

Provides Cloudflare integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=cloudflare+api://ACCOUNT_ID:API_TOKEN@default

# SMTP
MAILER_DSN=cloudflare+smtp://api_token:API_TOKEN@default
```

where:
 - `ACCOUNT_ID` is your Cloudflare Account ID
 - `API_TOKEN` is your Cloudflare API token (requires write permissions to `Email Sending`)

The SMTP transport does not use the account ID. Cloudflare requires the literal
`api_token` as the user name.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
