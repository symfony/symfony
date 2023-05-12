ClickSend Notifier
==================

Provides [ClickSend](https://www.clicksend.com/) integration for Symfony Notifier.

DSN example
-----------

```
CLICKSEND_DSN=clicksend://API_USERNAME:API_KEY@default?from=FROM&source=SOURCE&list_id=LIST_ID&from_email=FROM_EMAIL
```

where:

 - `API_USERNAME` is your ClickSend API username
 - `API_KEY` is your ClickSend API key
 - `FROM` is your sender (optional)
 - `SOURCE` is your source method of sending (optional)
 - `LIST_ID` is your recipient list ID (optional)
 - `FROM_EMAIL` is your from email where replies must be emailed (optional)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
