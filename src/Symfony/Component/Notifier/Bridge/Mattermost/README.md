Mattermost Notifier
===================

Provides [Mattermost](https://mattermost.com) integration for Symfony Notifier.

DSN example
-----------

```
MATTERMOST_DSN=mattermost://ACCESS_TOKEN@HOST/PATH?channel=CHANNEL_ID
```

where:
 - `ACCESS_TOKEN` is your Mattermost access token
 - `HOST` is your Mattermost host
 - `PATH` is your Mattermost sub-path (optional)
 - `CHANNEL_ID` is your Mattermost default channel id

Usage
-----

```
// to post to another channel
$options = new MattermostOptions();
$options->recipient('{channel_id}');

$message = (new ChatMessage($text))->options($options);

$chatter->send($message);
```

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
