Infobip Bridge
==============

Provides Infobip integration for Symfony Mailer.

Configuration examples:

```dotenv
# API
MAILER_DSN=infobip+api://KEY@BASE_URL
# SMTP
MAILER_DSN=infobip+smtp://KEY@default
```

Custom Headers
--------------

This transport supports the following custom headers:

| Header                         | Type    | Description                                                                             |
|--------------------------------|---------|-----------------------------------------------------------------------------------------|
| `X-Infobip-IntermediateReport` | boolean | The real-time Intermediate delivery report that will be sent on your callback server.   |
| `X-Infobip-NotifyUrl`          | string  | The URL on your callback server on which the Delivery report will be sent.              |
| `X-Infobip-NotifyContentType`  | string  | Preferred Delivery report content type. Can be application/json or application/xml.     |
| `X-Infobip-MessageId`          | string  | The ID that uniquely identifies the message sent to a recipient.                        |
| `X-Infobip-Track`              | boolean | Enable or disable open and click tracking.                                              |
| `X-Infobip-TrackingUrl`        | string  | The URL on your callback server on which the open and click notifications will be sent. |
| `X-Infobip-TrackClicks`        | boolean | Enable or disable track click feature..                                                 |
| `X-Infobip-TrackOpens`         | boolean | Enable or disable open click feature.                                                   |

Resources
---------

 * [Infobip Api Docs](https://www.infobip.com/docs/api#channels/email)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
