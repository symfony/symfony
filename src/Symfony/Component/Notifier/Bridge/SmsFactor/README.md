SMSFactor Notifier
-------------------

Provides [SMSFactor](https://www.smsfactor.com/) integration for Symfony Notifier.

DSN example
-----------

```
SMS_FACTOR_DSN=sms-factor://TOKEN@default?sender=SENDER&push_type=PUSH_TYPE
```

where:

 - `TOKEN` is your SMSFactor api token
 - `SENDER` is the sender name
 - `PUSH_TYPE` is the sms type (`alert` or `marketing`)

See SMSFactor documentation at https://dev.smsfactor.com/
