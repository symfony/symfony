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

Adding Interactions to a Message
--------------------------------

With a Slack message, you can use the `SlackOptions` class to add some 
interactive options called [Block elements](https://api.slack.com/reference/block-kit/block-elements).

```php
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackActionsBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackImageBlockElement;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('Contribute To Symfony');

// Create Slack Actions Block and add some buttons
$contributeToSymfonyBlocks = (new SlackActionsBlock())
    ->button(
        'Improve Documentation',
        'https://symfony.com/doc/current/contributing/documentation/standards.html',
        'primary'
    )
    ->button(
        'Report bugs',
        'https://symfony.com/doc/current/contributing/code/bugs.html',
        'danger'
    );

$slackOptions = (new SlackOptions())
    ->block((new SlackSectionBlock())
        ->text('The Symfony Community')
        ->accessory(
            new SlackImageBlockElement(
                'https://symfony.com/favicons/apple-touch-icon.png',
                'Symfony'
            )
        )
    )
    ->block(new SlackDividerBlock())
    ->block($contributeToSymfonyBlocks);

// Add the custom options to the chat message and send the message
$chatMessage->options($slackOptions);

$chatter->send($chatMessage);
```

Adding Fields and Values to a Message
-------------------------------------

To add fields and values to your message you can use the `field()` method.

```php
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('Symfony Feature');

$options = (new SlackOptions())
    ->block((new SlackSectionBlock())->text('My message'))
    ->block(new SlackDividerBlock())
    ->block(
        (new SlackSectionBlock())
            ->field('*Max Rating*')
            ->field('5.0')
            ->field('*Min Rating*')
            ->field('1.0')
    );

// Add the custom options to the chat message and send the message
$chatMessage->options($options);

$chatter->send($chatMessage);
```

Adding a Header to a Message
----------------------------

To add a header to your message use the `SlackHeaderBlock` class.

```php
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackHeaderBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('Symfony Feature');

$options = (new SlackOptions())
    ->block((new SlackHeaderBlock('My Header')))
    ->block((new SlackSectionBlock())->text('My message'))
    ->block(new SlackDividerBlock())
    ->block(
        (new SlackSectionBlock())
            ->field('*Max Rating*')
            ->field('5.0')
            ->field('*Min Rating*')
            ->field('1.0')
    );

// Add the custom options to the chat message and send the message
$chatMessage->options($options);

$chatter->send($chatMessage);
```

Adding a Footer to a Message
----------------------------

To add a header to your message use the `SlackContextBlock` class.

```php
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackContextBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackDividerBlock;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('Symfony Feature');

$contextBlock = (new SlackContextBlock())
    ->text('My Context')
    ->image('https://symfony.com/logos/symfony_white_03.png', 'Symfony Logo')
;

$options = (new SlackOptions())
    ->block((new SlackSectionBlock())->text('My message'))
    ->block(new SlackDividerBlock())
    ->block(
        (new SlackSectionBlock())
            ->field('*Max Rating*')
            ->field('5.0')
            ->field('*Min Rating*')
            ->field('1.0')
    )
    ->block($contextBlock)
;

// Add the custom options to the chat message and send the message
$chatMessage->options($options);

$chatter->send($chatMessage);
```

Sending a Message as a Reply
----------------------------

To send your slack message as a reply in a thread use the `threadTs()` method.

```php
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$chatMessage = new ChatMessage('Symfony Feature');

$options = (new SlackOptions())
    ->block((new SlackSectionBlock())->text('My reply'))
    ->threadTs('1621592155.003100')
;

// Add the custom options to the chat message and send the message
$chatMessage->options($options);

$chatter->send($chatMessage);
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
