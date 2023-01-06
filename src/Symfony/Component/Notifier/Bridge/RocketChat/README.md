RocketChat Notifier
===================

Provides [RocketChat](https://rocket.chat) integration for Symfony Notifier.

DSN example
-----------

```
ROCKETCHAT_DSN=rocketchat://ACCESS_TOKEN@default?channel=CHANNEL
```

where:
 - `ACCESS_TOKEN` is your RocketChat webhook token
 - `CHANNEL` is your RocketChat channel, it may be overridden in the payload

Example (be sure to escape the middle slash with %2F):

```
# Webhook URL: https://rocketchathost/hooks/a847c392165c41f7bc5bbf273dd701f3/9343289d1c33464bb15ef132b5a7628d
ROCKETCHAT_DSN=rocketchat://a847c392165c41f7bc5bbf273dd701f3%2F9343289d1c33464bb15ef132b5a7628d@rocketchathost?channel=channel
```

Attachments and Payload
-----------------------

When creating a `ChatMessage`, you can add payload and multiple attachments to
`RocketChatOptions`. These enable you to customize the name or the avatar of the
bot posting the message, and to add files to it.

The payload can contain any data you want; its data is processed by a
Rocket.Chat Incoming Webhook Script which you can write to best suit your needs.
For example, you can use this script to send the raw payload to Rocket.Chat:

```javascript
 class Script {
     process_incoming_request({ request }) {
         return {
             request.content
         };
     }
}
```

When using this script, the Payload must be indexed following Rocket.Chat
Payload convention:

```php
$payload = [
   'alias' => 'Bot Name',
   'emoji' => ':joy:', // Emoji used as avatar
   'avatar' => 'http://site.com/logo.png', // Overridden by emoji if provided
   'channel' => '#myChannel', // Overrides the DSN's channel setting
];

$attachement1 = [
    'color' => '#ff0000',
    'title' => 'My title',
    'text' => 'My text',
    // ...
];

$attachement2 = [
    'color' => '#ff0000',
    'title' => 'My title',
    'text' => 'My text',
    // ...
];

// For backward compatibility reasons, both usages are valid
$rocketChatOptions = new RocketChatOptions($attachement1, $payload);
$rocketChatOptions = new RocketChatOptions([$attachement1, $attachement2], $payload);
```

**Note:** the `text` and `attachments` keys of the payload will be overridden
respectively by the ChatMessage's subject and the attachments provided in
RocketChatOptions' constructor.

See Also
--------

 * [Rocket.Chat Webhook Integration](https://docs.rocket.chat/guides/administration/admin-panel/integrations)
 * [Rocket.Chat Message Payload](https://developer.rocket.chat/reference/api/rest-api/endpoints/core-endpoints/chat-endpoints/postmessage#payload)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
