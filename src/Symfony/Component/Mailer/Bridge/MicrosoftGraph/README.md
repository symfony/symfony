Microsoft Graph API Mailer
=============

Provides Microsoft Graph API integration for Symfony Mailer.


Prerequisites
---------
You will need to:
 * Register an application in your Microsoft Azure portal,
 * Grant this application the Microsoft Graph `Mail.Send` permission,
 * Create a secret for that app.


Configuration example
---------

```env
# MAILER
MAILER_DSN=microsoft+graph://CLIENT_APP_ID:SECRET@default?tenant=TENANT_ID
```

If you need to use third parties operated or specific regions Microsoft services (China, US Government, etc.), you can specify Auth Endpoint and Graph Endpoint.

```env
# MAILER e.g. for China
MAILER_DSN=microsoft+graph://CLIENT_APP_ID:SECRET@login.partner.microsoftonline.cn?tenant=TENANT_ID&graphEndpoint=https://microsoftgraph.chinacloudapi.cn
```

|                        | Authentication endpoint                  | Graph Endpoint                          |
|------------------------|------------------------------------------|-----------------------------------------|
| Global (default)       | https://login.microsoftonline.com        | https://graph.microsoft.com             |
| US Government L4       | https://login.microsoftonline.us         | https://graph.microsoft.us              |
| US Government L5 (DOD) | https://login.microsoftonline.us         | https://dod-graph.microsoft.us          |
| China                  | https://login.partner.microsoftonline.cn | https://microsoftgraph.chinacloudapi.cn |

More details can be found in the Microsoft documentation :
 * [Auth Endpoints](https://learn.microsoft.com/en-us/entra/identity-platform/authentication-national-cloud#microsoft-entra-authentication-endpoints)
 * [Grpah Endpoints](https://learn.microsoft.com/en-us/graph/deployments#microsoft-graph-and-graph-explorer-service-root-endpoints)


Troubleshooting
--------
//TODO : erreur stack trace
Beware that the sender email address needs to be an address of an account inside your tenant.


Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
