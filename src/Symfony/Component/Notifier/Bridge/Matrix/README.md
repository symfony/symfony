Matrix Notifier
===============

Provides [Matrix](https://matrix.org) integration for Symfony Notifier.
It uses the [Matrix Client-Server API](https://spec.matrix.org/v1.13/client-server-api/).

```
Note:
This Bridge was tested with the official Matrix Synapse Server and the Client-Server API v3 (version v1.13).
But it should work with every Matrix Client-Server API v3 compliant homeserver.
```

DSN example
-----------

```
MATRIX_DSN=matrix://HOST:PORT/?accessToken=ACCESS_TOKEN&ssl=true
```
To get started you need an access token. The simplest way to get that is to open Element in a private (incognito) window in your webbrowser or just use your currently open Element. Go to Settings > Help & About > Advanced > Access Token `click to reveal` and copy your access token.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
 * [Matrix Playground](https://playground.matrix.org)
 * [Matrix Client-Server API](https://spec.matrix.org/latest/client-server-api/)
