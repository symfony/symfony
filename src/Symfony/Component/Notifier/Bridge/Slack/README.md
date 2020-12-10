Slack Notifier
==============

Provides [Slack](https://slack.com) integration for Symfony Notifier.

DSN example
-----------

```
SLACK_DSN=slack://TOKEN@default?channel=CHANNEL
```

where:
- `TOKEN` is your Bot User OAuth Access Token (they begin with `xoxb-`)
- `CHANNEL` is a channel, private group, or IM channel to send message to, it can be an encoded ID, or a name.

valid DSN's are:
```
SLACK_DSN=slack://xoxb-......@default?channel=my-channel-name
SLACK_DSN=slack://xoxb-......@default?channel=@fabien
```

invalid DSN's are:
```
SLACK_DSN=slack://xoxb-......@default?channel=#my-channel-name
SLACK_DSN=slack://xoxb-......@default?channel=fabien
```

Resources
---------

  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
