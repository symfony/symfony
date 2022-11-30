Telnyx Notifier
===============

Provides [Telnyx](https://telnyx.com/) integration for Symfony Notifier.

DSN example
-----------

```
TELNYX_DSN=telnyx://API_KEY@default?from=FROM&messaging_profile_id=MESSAGING_PROFILE_ID
```

where:
 - `API_KEY` is your telnyx API key.
 - `FROM` is your sender.
 - `MESSAGING_PROFILE_ID` identifier of your messaging profile at Telnyx. You need this in order to show a name to the recipient (e.g. "Symfony") instead of just the phone number.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
