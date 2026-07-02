CHANGELOG
=========

8.2
---

* Allow configuring the SMTP port through the DSN
* Add support for `RemoteTemplateEmail` to `BrevoApiTransport`
* Deprecate the "templateid" and "params" email headers, use a `RemoteTemplateEmail` instead

6.4
---

* Add support for `RemoteEvent` and `Webhook`
* Add the bridge as a replacement of the deprecated Sendinblue one
