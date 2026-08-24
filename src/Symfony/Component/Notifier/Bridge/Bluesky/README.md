Bluesky Notifier
================

Provides [Bluesky](https://bsky.app/) integration for Symfony Notifier.

DSN example
-----------

```
BLUESKY_DSN=bluesky://nyholm.bsky.social:p4ssw0rd@bsky.social
```

Adding Options to a Message
---------------------------

Use a `BlueskyOptions` object to add options to the message:

```php
use Symfony\Component\Notifier\Bridge\Bluesky\BlueskyOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$message = new ChatMessage('My message');

// Add website preview card to the message
$options = (new BlueskyOptions())
    ->attachCard('https://example.com', new File('image.jpg'))
    // You can also add media to the message
    //->attachMedia(new File($command->fileName), 'description')
    ;

// Add the custom options to the Bluesky message and send the message
$message->options($options);

$chatter->send($message);
```

Automatic Rich Text Detection
-----------------------------

Bluesky posts have no markup: rich text is declared through "facets", byte
ranges of the text that clients render as links. The transport detects them
automatically in the message, no option is needed:

 * mentions, e.g. `@nyholm.bsky.social` (the handle must resolve on the
   configured endpoint);
 * URLs, e.g. `https://symfony.com`;
 * hashtags, e.g. `#symfony` (also with the fullwidth `＃` marker), which makes
   them clickable and attaches the post to the tag feed.

A hashtag needs at least one character that is neither a digit nor
punctuation (so `#123` is left as plain text), trailing punctuation is not
part of the tag, and a `#` inside a URL is part of the link, not a tag.
Following the lexicon limits, a tag longer than 64 graphemes or 640 bytes is
left as plain text instead of making the whole post fail.

Hashtag facets require version 8.2 or higher of the bridge.

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
