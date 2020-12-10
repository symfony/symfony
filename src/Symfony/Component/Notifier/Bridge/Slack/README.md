Slack Notifier
==============

Provides [Slack](https://slack.com) integration for Symfony Notifier.

DSN example
-----------

```
SLACK_DSN=slack://default/ID
```

where:
- `ID` is your webhook id (e.g. `/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX`)

in this case:
```
SLACK_DSN=slack://default/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX
```

DSN example
-----------

```
// .env file
SLACK_DSN=slack://TOKEN@default?channel=CHANNEL
```

where:
- `TOKEN` is your Bot User OAuth Access Token
- `CHANNEL` is a Channel, private group, or IM channel to send message to. Can be an encoded ID, or a name

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
