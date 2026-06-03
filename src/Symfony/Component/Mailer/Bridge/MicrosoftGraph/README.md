Microsoft Graph API Mailer
==========================

Provides [Microsoft Graph API Email](https://learn.microsoft.com/en-us/graph/api/user-sendmail) integration for Symfony Mailer.

Prerequisites
-------------

You will need to:
* Register an application in your Microsoft Azure portal,
* Grant this application the Microsoft Graph `Mail.Send` permission,
* Create a secret for that app.

Configuration example
---------------------

```env
# MAILER
MAILER_DSN=microsoftgraph+api://CLIENT_APP_ID:CLIENT_APP_SECRET@default?tenantId=TENANT_ID
```

This will default to `graph.microsoft.com` for the Graph API and `login.microsoftonline.com` for authentication.

If you need to use third parties operated or specific regions Microsoft services (China, US Government, etc.), you can specify the Graph Endpoint and the Auth Endpoint explicitly.

```env
# MAILER e.g. for China
MAILER_DSN=microsoftgraph+api://CLIENT_APP_ID:CLIENT_APP_SECRET@microsoftgraph.chinacloudapi.cn?tenantId=TENANT_ID&authEndpoint=login.partner.microsoftonline.cn
```

The exact URLs can be found in the Microsoft documentation:
* [Graph Endpoints](https://learn.microsoft.com/en-us/graph/deployments#microsoft-graph-and-graph-explorer-service-root-endpoints)
* [Auth Endpoints](https://learn.microsoft.com/en-us/entra/identity-platform/authentication-national-cloud#microsoft-entra-authentication-endpoints)

You can also specify to not save the messages to sent items using the `noSave` parameter:

```env
# MAILER
MAILER_DSN=microsoftgraph+api://CLIENT_APP_ID:CLIENT_APP_SECRET@default?tenantId=TENANT_ID&noSave=true
```

Troubleshooting
---------------

Beware that the sender email address needs to be an address of an account inside your tenant.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
