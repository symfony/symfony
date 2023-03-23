Telegram Notifier
=================

Provides [Telegram](https://telegram.org) integration for Symfony Notifier.

DSN example
-----------

```
TELEGRAM_DSN=telegram://TOKEN@default?channel=CHAT_ID
```

where:
 - `TOKEN` is your Telegram token
 - `CHAT_ID` is your Telegram chat id

Adding Interactions to a Message
--------------------------------

With a Telegram message, you can use the `TelegramOptions` class to add
[message options](https://core.telegram.org/bots/api).

```php
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\Button\InlineKeyboardButton;
use Symfony\Component\Notifier\Bridge\Telegram\Reply\Markup\InlineKeyboardMarkup;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('');

// Create Telegram options
$telegramOptions = (new TelegramOptions())
    ->chatId('@symfonynotifierdev')
    ->parseMode('MarkdownV2')
    ->disableWebPagePreview(true)
    ->disableNotification(true)
    ->replyMarkup((new InlineKeyboardMarkup())
        ->inlineKeyboard([
            (new InlineKeyboardButton('Visit symfony.com'))
                ->url('https://symfony.com/'),
        ])
    );

// Add the custom options to the chat message and send the message
$chatMessage->options($telegramOptions);

$chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
