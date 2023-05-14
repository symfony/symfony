Redlink Bridge
=================

Provides [Redlink](https://redlink.pl) integration for Symfony Mailer.

Configuration example:

```env
MAILER_DSN=redlink+api://API_TOKEN:APP_TOKEN@default?fromSmtp=SMTP_ACCOUNT&version=VERSION
```

where:
- `API_TOKEN` is your user API token, you can get it from the user dashboard
- `APP_TOKEN` is your application's API token
- `SMTP_ACCOUNT` is subaccount that will be used to send email, required 
- `VERSION` is API version that you want to use, ex. v2.1, optional

For more informations, you can refer to [Redlink API documentation](https://docs.redlink.pl).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
