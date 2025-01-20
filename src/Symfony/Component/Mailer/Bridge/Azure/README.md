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

Resources
---------

 * [Microsoft Azure (ACS) Email API Docs](https://learn.microsoft.com/en-us/rest/api/communication/dataplane/email/send)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)