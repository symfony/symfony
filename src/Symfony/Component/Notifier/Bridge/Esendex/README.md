Esendex Notifier
================

Provides [Esendex](https://esendex.com) integration for Symfony Notifier.

DSN example
-----------

```
ESENDEX_DSN=esendex://EMAIL:PASSWORD@default?accountreference=ACCOUNT_REFERENCE&from=FROM
```

where:
 - `EMAIL` is your Esendex account email
 - `PASSWORD` is the Esendex API password
 - `ACCOUNT_REFERENCE` is the Esendex account reference that the messages should be sent from
 - `FROM` is the alphanumeric originator for the message to appear to originate from

See Esendex documentation at https://developers.esendex.com/api-reference#smsapis

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
