Spot-Hit Notifier
=================

Provides [Spot-Hit](https://www.spot-hit.fr/) integration for Symfony Notifier.

#### DSN example

```
SPOTHIT_DSN=spothit://TOKEN@default?from=FROM&smslong=SMSLONG&smslongnbr=SMSLONGNBR
```

where:
 - `TOKEN` is your Spot-Hit API key
 - `FROM` is the custom sender (3-11 letters, default is a 5 digits phone number)
 - `SMSLONG` (optional) 0 or 1 : allows SMS messages longer than 160 characters
 - `SMSLONGNBR` (optional) integer : allows to check the size of the long SMS sent. You must send the number of concatenated SMS as a value. If our counter indicates a different number, your message will be rejected.

Resources
---------

 * [Spot-Hit API doc](https://www.spot-hit.fr/documentation-api).
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
