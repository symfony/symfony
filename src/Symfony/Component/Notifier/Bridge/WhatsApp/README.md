WhatsApp Notifier
==================

Provides [Meta WhatsApp Cloud API](https://developers.facebook.com/docs/whatsapp/cloud-api)
integration for Symfony Notifier, as a `Chatter` transport.

> **Note**
> This bridge talks directly to Meta's own Graph API `POST /{phone_number_id}/messages`
> endpoint. If you're looking for WhatsApp support through a third-party gateway instead,
> the `Twilio` bridge can also send WhatsApp messages, by prefixing its `From` number with
> `whatsapp:`.

DSN example
-----------

```
WHATSAPP_DSN=whatsapp://TOKEN@default?phone_number_id=PHONE_NUMBER_ID&api_version=v26.0
```

where:

- `TOKEN` is a WhatsApp Business permanent access token (system user token recommended)
- `PHONE_NUMBER_ID` is the sending number's `phone_number_id`, from the WhatsApp Business
  Account (WABA) configuration
- `api_version` is optional (defaults to `v26.0`)

The DSN carries no default recipient, so every message must set one through
`WhatsAppOptions::recipientPhoneNumber()`. The transport does not claim messages without
it, which leaves them to any other chatter transport that can deliver them.

Sending a template message
---------------------------

Business-initiated messages (reminders, confirmations) require a pre-approved message
template (Meta calls these HSM, for Highly Structured Messages). Use `WhatsAppOptions` to
select one and set its body parameters:

```php
use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new WhatsAppOptions())
    ->recipientPhoneNumber('5491112345678')
    ->template('recordatorio_turno', 'es_AR', ['Ana', 'Corte de pelo', '10/07/2026', '15:00']);

$chatMessage = new ChatMessage('Recordatorio de tu turno', $options);

$chatter->send($chatMessage);
```

Sending a free-form session message
------------------------------------

Inside WhatsApp's 24h customer service window (i.e. after the recipient has messaged the
business), free-form text is allowed without a template:

```php
use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppOptions;
use Symfony\Component\Notifier\Message\ChatMessage;

$options = (new WhatsAppOptions())->recipientPhoneNumber('5491112345678');

$chatMessage = new ChatMessage('Tu turno para mañana sigue en pie, ¡te esperamos!', $options);

$chatter->send($chatMessage);
```

Quick-reply buttons on a template are configured on the template itself in Meta's WhatsApp
Manager (Message Templates), not via this bridge. The API call only supplies the template
name, language, and body parameters.

Resources
---------

- [Contributing](https://symfony.com/doc/current/contributing/index.html)
- [Report issues](https://github.com/symfony/symfony/issues) and
  [send Pull Requests](https://github.com/symfony/symfony/pulls)
  in the [main Symfony repository](https://github.com/symfony/symfony)
