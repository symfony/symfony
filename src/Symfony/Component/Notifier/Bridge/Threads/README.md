Threads Notifier
================

Provides [Threads API](https://developers.facebook.com/docs/threads/posts/)
integration for Symfony Notifier, as a `Chatter` transport.

DSN example
-----------

```
THREADS_DSN=threads://ACCESS_TOKEN@default?user_id=THREADS_USER_ID&api_version=v1.0
```

where:

- `ACCESS_TOKEN` is a Threads user access token
- `THREADS_USER_ID` is the Threads user id
- `api_version` is optional (defaults to `v1.0`)
- `poll_attempts` is optional (defaults to `30`): how many times an image or video
  container is checked before giving up
- `poll_delay` is optional (defaults to `2`): seconds between two checks

Sending a text post
-------------------

```php
use Symfony\Component\Notifier\Message\ChatMessage;

$chatter->send(new ChatMessage('Hello from Threads'));
```

Image / video (public HTTPS URL required):

```php
use Symfony\Component\Notifier\Bridge\Threads\ThreadsOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new ThreadsOptions())->imageUrl('https://example.com/photo.jpg');

$chatter->send((new ChatMessage('Caption'))->options($options));
```

Resources
---------

- [Contributing](https://symfony.com/doc/current/contributing/index.html)
- [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
