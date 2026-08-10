Facebook Page Notifier
======================

Provides [Facebook Pages API](https://developers.facebook.com/docs/pages-api/posts/)
integration for Symfony Notifier, as a `Chatter` transport.

> **Note**
> This bridge posts to a **Facebook Page** via Graph API `POST /{page_id}/feed`
> using a page access token. Personal-profile publishing is not available through
> the Graph API (`publish_actions` was removed in 2018). Use Meta's Share Dialog
> for user-timeline sharing.

DSN example
-----------

```
FACEBOOK_PAGE_DSN=facebook-page://PAGE_ACCESS_TOKEN@default?page_id=PAGE_ID&api_version=v26.0
```

where:

- `PAGE_ACCESS_TOKEN` is a Page access token with `pages_manage_posts`
- `PAGE_ID` is the numeric Facebook Page id
- `api_version` is optional (defaults to `v26.0`)

Sending a Page post
-------------------

```php
use Symfony\Component\Notifier\Message\ChatMessage;

$chatter->send(new ChatMessage('Hello from the Facebook Page!'));
```

Optional link attachment:

```php
use Symfony\Component\Notifier\Bridge\FacebookPage\FacebookPageOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new FacebookPageOptions())->link('https://example.com/article');

$chatter->send((new ChatMessage('Read our latest article'))->options($options));
```

Resources
---------

- [Contributing](https://symfony.com/doc/current/contributing/index.html)
- [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
