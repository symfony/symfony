Groundmail Bridge
=================

Provides [Groundmail](https://groundmail.dev) integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=groundmail+api://API_KEY@default

# same thing, shorter
MAILER_DSN=groundmail://API_KEY@default

# SMTP, port 587 with STARTTLS
MAILER_DSN=groundmail+smtp://groundmail:SMTP_PASSWORD@default

# SMTP on port 2525, for hosts that block port 587
MAILER_DSN=groundmail+smtp://groundmail:SMTP_PASSWORD@default:2525
```

where:
 - `API_KEY` is your Groundmail API key (`gm_...`), with the `emails:send` scope
 - `SMTP_PASSWORD` is an SMTP credential (`gmsmtp_...`), created in the dashboard under SMTP;
   the SMTP username is always `groundmail`

Use the API transport when your host blocks outbound SMTP.

The `From` address must be on a domain verified in your Groundmail account. The API transport
carries the sender with its display name, recipients, reply-to addresses, subject, text and HTML
bodies, attachments, inline images (referenced by their `cid`) and custom headers. It uses the
Groundmail email id (`msg_...`) as the message id, and a failed send reports the error code of the
API, such as `forbidden` for a sandbox send to an outside address.

Groundmail needs at least one `To` recipient, so the API transport refuses a message with only
`Cc` or `Bcc` recipients.

Remote templates
----------------

The API transport sends a `RemoteTemplateEmail` with a published Groundmail template, named by its
id or alias:

```php
use Symfony\Component\Mailer\RemoteTemplateEmail;

$email = (new RemoteTemplateEmail())
    ->from('hello@example.com')
    ->to('jane@example.com')
    ->template('welcome', ['name' => 'Jane']);
```

The subject of the template is used unless the email sets one.

Groundmail headers
------------------

These headers work on both transports: the API transport turns them into fields of the API, and
the SMTP relay reads them itself.

| Header                         | Effect                                                         |
|--------------------------------|----------------------------------------------------------------|
| `X-Groundmail-Category`        | `transactional` (default), `product` or `newsletter` stream    |
| `X-Groundmail-Scheduled-At`    | ISO 8601 time to send the email later                          |
| `X-Groundmail-Idempotency-Key` | sends the email once, even when a send is retried              |
| `X-Groundmail-Tag`             | a `name=value` tag stored with the email, can be repeated      |

A `MetadataHeader` is sent as a Groundmail tag, and tags come back as the metadata of the webhook
events. `TagHeader` and `TrackingHeader` are not supported: open and click tracking are settings of
the sending domain.

Webhook
-------

Create a webhook endpoint in the Groundmail dashboard (Webhooks) pointing to your application, and
use the secret it shows once (`whsec_...`) as the route secret:

```yaml
framework:
    webhook:
        routing:
            groundmail:
                service: mailer.webhook.request_parser.groundmail
                secret: '%env(GROUNDMAIL_WEBHOOK_SECRET)%'
```

The request parser checks the `Groundmail-Signature` header and refuses requests signed more than
five minutes away from the server clock. It converts these events:

 * `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.held`, `email.bounced`,
   `email.failed` and `email.suppressed` to a `MailerDeliveryEvent`
 * `email.opened`, `email.clicked`, `email.complained` and `contact.unsubscribed` to a
   `MailerEngagementEvent`

Other events, such as inbound email and automation events, are refused, so subscribe the endpoint
to the events above only.

Resources
---------

 * [Groundmail API reference](https://docs.groundmail.dev/api/emails)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
