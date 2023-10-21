CHANGELOG
=========

6.4
---

 * Add support for webhooks

5.4
---

 * Add support for `TagHeader` and `MetadataHeader` to the Sendgrid API transport

4.4.0
-----

 * [BC BREAK] Renamed and moved `Symfony\Component\Mailer\Bridge\Sendgrid\Http\Api\SendgridTransport`
   to `Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridApiTransport`, `Symfony\Component\Mailer\Bridge\Sendgrid\Smtp\SendgridTransport`
   to `Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridSmtpTransport`.

4.3.0
-----

 * Added the bridge
