# Prelude Notifier

The `prelude-notifier` package provides a [Prelude](https://prelude.dev/) bridge for Symfony Notifier.

## Installation

```bash
composer require symfony/prelude-notifier
```

## Configuration

1.  Register the bundle in your application (if not using Symfony Flex).
2.  Configure the DSN in your `.env` file:

    ```dotenv
    # API Key is required
    # Sender ID is optional (can be set in options or DSN)
    PRELUDE_DSN=prelude://YOUR_API_KEY@default?sender=YOUR_SENDER_ID
    ```

## Usage

The Prelude Notify API **requires** a `template_id`. You must use `PreludeOptions` to provide it.

```php
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Bridge\Prelude\PreludeOptions;

$options = (new PreludeOptions())
    ->templateId('template_01k8xxxxxxxxxxxxx') // Required
    ->variables([
        'order_id' => '12345',
        'amount' => '$49.99',
    ])
    // Optional parameters
    // ->from('MySenderID')
    // ->locale('fr-FR')
    // ->callbackUrl('https://example.com/webhook')
    // ->preferredChannel('whatsapp')
;

$message = (new SmsMessage('+33612345678', 'Subject (ignored)'))
    ->options($options);

$notifier->send($message);
```

### Options

* `templateId` (string, required): The template identifier.
* `variables` (array): Key-value pairs for template variables.
* `from` (string): The Sender ID.
* `locale` (string): BCP-47 formatted locale string.
* `expiresAt` (string): Message expiration date (RFC3339).
* `scheduleAt` (string): Schedule delivery time (RFC3339).
* `callbackUrl` (string): URL for delivery events.
* `correlationId` (string): User-defined identifier.
* `preferredChannel` (string): 'sms' or 'whatsapp'.

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
