OvhCloud Notifier
=================

Provides [OvhCloud](https://docs.ovh.com/gb/en/sms/) integration for Symfony Notifier.

DSN example
-----------

```
OVHCLOUD_DSN=ovhcloud://APPLICATION_KEY:APPLICATION_SECRET@default?consumer_key=CONSUMER_KEY&service_name=SERVICE_NAME&sender=SENDER&no_stop_clause=NO_STOP_CLAUSE
```

where:
 - `APPLICATION_KEY` is your OvhCloud application key
 - `APPLICATION_SECRET` is your OvhCloud application secret
 - `CONSUMER_KEY` is your OvhCloud consumer key
 - `SERVICE_NAME` is your OvhCloud service name
 - `SENDER` is your sender (optional)
 - `NO_STOP_CLAUSE` setting this parameter to "1" (default "0") allow removing "STOP clause" at the end of the message for non-commercial use (optional)


Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
