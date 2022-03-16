Mercure Notifier
================

Provides [Mercure](https://github.com/symfony/mercure) integration for Symfony Notifier.

DSN example
-----------

```
MERCURE_DSN=mercure://HUB_ID?topic=TOPIC
```

where:
 - `HUB_ID` is the Mercure hub id
 - `TOPIC` is the topic IRI (optional, default: `https://symfony.com/notifier`. Could be either a single topic: `topic=https://foo` or multiple topics: `topic[]=/foo/1&topic[]=https://bar`)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
