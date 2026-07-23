Orange Sms Notifier
-------------------

Provides [Orange Sms](https://developer.orange.com/apis/sms) integration for Symfony Notifier.

DSN example
-----------

```
ORANGE_SMS_DSN=orange-sms://CLIENT_ID:CLIENT_SECRET@default?from=FROM&sender_name=SENDER_NAME
```

where:

 - `CLIENT_ID` is your Orange App client ID
 - `CLIENT_SECRET` is the Orange App client secret
 - `FROM` is the sender phone number
 - `SENDER_NAME` is the sender name

example:

```
ORANGE_SMS_DSN=orange-sms://RbttXve8o2y3IglAqJXlXTzZywyyjqKo:iNpfgVeHusPEKrrp@default?from=+243000000&sender_name=platformXYZ
```

See Orange Sms documentation at https://developer.orange.com/apis/sms-cd/api-reference

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
