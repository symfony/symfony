Instagram Notifier
==================

Provides [Instagram content publishing](https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/content-publishing)
via Instagram API with Instagram Login, as a Symfony Notifier `Chatter` transport.

DSN example
-----------

```
INSTAGRAM_DSN=instagram://ACCESS_TOKEN@default?user_id=IG_USER_ID&api_version=v22.0
```

where:

- `ACCESS_TOKEN` is an Instagram user access token (Instagram Login)
- `IG_USER_ID` is the Instagram user id
- `api_version` is optional (defaults to `v22.0`)
- `poll_attempts` is optional (defaults to `45`): how many times the media
  container is checked before giving up
- `poll_delay` is optional (defaults to `2`): seconds between two checks

Sending an image feed post
--------------------------

```php
use Symfony\Component\Notifier\Bridge\Instagram\InstagramOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new InstagramOptions())->imageUrl('https://example.com/photo.jpg');

$chatter->send((new ChatMessage('Caption'))->options($options));
```

Reel (public HTTPS `video_url` required):

```php
use Symfony\Component\Notifier\Bridge\Instagram\InstagramOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new InstagramOptions())
    ->videoUrl('https://example.com/reel.mp4')
    ->shareToFeed(true);

$chatter->send((new ChatMessage('Reel caption'))->options($options));
```

Resources
---------

- [Contributing](https://symfony.com/doc/current/contributing/index.html)
- [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
