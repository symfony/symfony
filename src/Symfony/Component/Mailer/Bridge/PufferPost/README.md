PufferPost Bridge
=================

Provides PufferPost integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=pufferpost+api://API_KEY@default

# same thing, shorter
MAILER_DSN=pufferpost://API_KEY@default
```

where `API_KEY` is your PufferPost API key.

PufferPost has no SMTP relay, so the bridge is API-only and `pufferpost` is an alias for
`pufferpost+api`. Sends go over HTTPS and report the error message returned by the API on a
failed send.

The transport carries what the send endpoint accepts: sender, recipients, subject, text and HTML
bodies, a single reply-to address, attachments and custom `X-` headers. Inline (`cid:`) attachments
are rejected, because the API has no `Content-ID` support and the reference would silently break.

The API addresses one recipient per message, so an email with several recipients is submitted as a
batch: one request, one message per envelope recipient. Cc and Bcc are attached to the first
message only, because each item is delivered as an independent email.

## Templates

A template stored in PufferPost is rendered server side, through the component's
`RemoteTemplateEmail`:

```php
use Symfony\Component\Mailer\RemoteTemplateEmail;

$email = (new RemoteTemplateEmail())
    ->from('shop@example.com')
    ->to('jane@example.com')
    ->template('tpl_welcome', ['name' => 'Jane']);
```

Such an email carries no body of its own, and the subject belongs to the template, so setting one
is refused rather than silently dropped.

## Provider options

The remaining options are set as headers, so a plain `Email` carries them and `$mailer->send()`
keeps working: `X-PufferPost-Metadata` (JSON), `X-PufferPost-Unsubscribe-Group`,
`X-PufferPost-Locale` and `X-PufferPost-Timezone`. The transport reads them into their own payload
fields and never sends them on as headers.

Only other `X-` headers are forwarded; the API allow-lists the headers map to those names. An email
with several reply-to addresses keeps the first, since the others cannot be passed as a raw header.

A batch is one request, so if some messages are accepted and others refused the transport reports
the failure while the accepted ones are already queued. A Messenger retry of that send would
deliver those again; `pufferpost/sdk` sends per recipient with idempotency keys where that matters.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
