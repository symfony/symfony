Discord Notifier
================

Provides [Discord](https://discord.com) integration for Symfony Notifier.

DSN example
-----------

```
DISCORD_DSN=discord://TOKEN@default?webhook_id=ID
```

where:
 - `TOKEN` the secure token of the webhook (returned for Incoming Webhooks)
 - `ID` the id of the webhook

Adding Interactions to a Message
--------------------------------

With a Discord message, you can use the `DiscordOptions` class to add some
interactive options called Embed `elements`.

```php
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFieldEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFooterEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordMediaEmbedObject;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('');

// Create Discord Embed
$discordOptions = (new DiscordOptions())
    ->username('connor bot')
    ->addEmbed((new DiscordEmbed())
        ->color(2021216)
        ->title('New song added!')
        ->thumbnail((new DiscordMediaEmbedObject())
        ->url('https://i.scdn.co/image/ab67616d0000b2735eb27502aa5cb1b4c9db426b'))
        ->addField((new DiscordFieldEmbedObject())
            ->name('Track')
            ->value('[Common Ground](https://open.spotify.com/track/36TYfGWUhIRlVjM8TxGUK6)')
            ->inline(true)
        )
        ->addField((new DiscordFieldEmbedObject())
            ->name('Artist')
            ->value('Alasdair Fraser')
            ->inline(true)
        )
        ->addField((new DiscordFieldEmbedObject())
            ->name('Album')
            ->value('Dawn Dance')
            ->inline(true)
        )
        ->footer((new DiscordFooterEmbedObject())
            ->text('Added ...')
            ->iconUrl('https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Spotify_logo_without_text.svg/200px-Spotify_logo_without_text.svg.png')
        )
    )
;

    // Add the custom options to the chat message and send the message
    $chatMessage->options($discordOptions);

    $chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
