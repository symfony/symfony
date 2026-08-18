Cloudflare Bridge
=================

Provides Cloudflare integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=cloudflare+api://ACCOUNT_ID:API_TOKEN@default
```

where:
 - `ACCOUNT_ID` is your Cloudflare Account ID
 - `API_TOKEN` is your Cloudflare API token (requires write permissions to `Email Sending`)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
