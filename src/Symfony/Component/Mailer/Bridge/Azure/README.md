Microsoft Azure Mailer
======================

Provides [Azure Communication Services Email](https://learn.microsoft.com/en-us/azure/communication-services/concepts/email/email-overview) integration for Symfony Mailer.

Configuration example:

```env
# API
MAILER_DSN=azure+api://ACS_RESOURCE_NAME:KEY@default

#API with options

MAILER_DSN=azure+api://ACS_RESOURCE_NAME:KEY@default?api_version=2023-03-31&disable_tracking=false
```

where:
 - `ACS_RESOURCE_NAME` is your Azure Communication Services endpoint resource name (https://ACS_RESOURCE_NAME.communication.azure.com)
 - `KEY` is your Azure Communication Services Email API Key

Webhook
-------

Azure Event Grid does not sign the events it delivers. It authenticates itself either with a
Microsoft Entra bearer token, or by repeating the query parameters of the subscription URL on
every delivery. This bridge uses the second mechanism, so give the subscription URL a `secret`
query parameter holding the same value as the webhook secret:

```
https://example.com/webhook/azure?secret=THE_WEBHOOK_SECRET
```

Requests whose `secret` parameter does not match are rejected with a 401.

Event Grid's automatic subscription handshake is not supported, as it requires echoing a
validation code in the response body. Register the endpoint with Event Grid's manual
validation flow instead.

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Microsoft Azure (ACS) Email API Docs](https://learn.microsoft.com/en-us/rest/api/communication/email/email/send)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
