iSendPro Notifier
=================

Provides [iSendPro](https://www.isendpro.com/) integration for Symfony Notifier.

DSN example
-----------

```
ISENDPRO_DSN=isendpro://ACCOUNT_KEY_ID@default?from=FROM&no_stop=NO_STOP&sandbox=SANDBOX
```

where:
 - `ACCOUNT_KEY_ID` is your iSendPro API Key ID
 - `FROM` is the alphanumeric originator for the message to appear to originate from  (optional)
 - `NO_STOP` setting this parameter to "1" (default "0") allows removing "STOP clause" at the end of the message for non-commercial use (optional)
 - `SANDBOX` setting this parameter to "1" (default "0") allows to use the notifier in sandbox mode (optional)

See iSendPro documentation at https://www.isendpro.com/docs/#prerequis

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
